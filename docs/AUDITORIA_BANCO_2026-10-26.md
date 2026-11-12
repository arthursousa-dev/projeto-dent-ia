# Auditoria do banco DENT IA — 26/10/2026

## 1. Diagnóstico

- **Banco:** PostgreSQL 18.6 local (`dentia`, serviço Windows, 11 MB, ~1.100 linhas em 31 tabelas). Lido de verdade via `pg_dump -s`, `pg_stat_*` e `pg_catalog`, não só pelos scripts.
- **Esquema real = `database/schema.sql`**, sem diferença (comparado com `pg_dump -s` de um banco recriado do zero).
- **Conexão PHP:** PDO singleton em `app/Config/Database.php`, `.env` → `DB_*`, prepared statements reais (`ATTR_EMULATE_PREPARES=false`). Senhas com bcrypt.
- **Entidades existentes:** pessoa, usuarios, pacientes, dentistas, clinica, endereco/cidade/estado, telefone, procedimentos/categorias, dentista_procedimentos, disponibilidade_dentista, funcionamento_clinica, sala, agendamentos, pagamento, prontuarios, odontograma_dentes/procedimentos, evolucao_clinica/evolucao_dente, historico_clinico, lancamento_financeiro, faturamento_mensal, recuperacao_senha. Não existem tabelas de anexos/radiografias nem de permissões além de `usuarios.tipo`.
- **Integridade:** todas as FKs são `ON DELETE RESTRICT`, CPF validado por função, conflitos de agenda impedidos por índices únicos parciais + trigger com advisory lock. Nenhum órfão possível pelas FKs. 0 locks esperando, 0 transações longas, cache hit 99%, 83 tuplas mortas (autovacuum ativo).

## 2. Normalização

| Forma | Resultado |
|---|---|
| 1FN | Já atendida: alergias/medicamentos/condições estão em `historico_clinico`; horários em `disponibilidade_dentista`; telefones em `telefone`. **Exceção corrigida:** `clinica.horario` guardava em texto livre a lista de dias e horários, que já existe em `funcionamento_clinica`. |
| 2FN | Já atendida. Chaves compostas verificadas: `disponibilidade_dentista`, `dentista_procedimentos`, `evolucao_dente` (só chave), `funcionamento_clinica` (abertura/fechamento dependem de clínica **e** dia), `faturamento_mensal` (ano **e** mês). Sem dependências parciais. |
| 3FN | **Corrigido:** `clinica.horario` dependia de `funcionamento_clinica` (redundância/anomalia de atualização). Agora `vw_clinica` deriva o texto; a aplicação não grava mais a coluna. |

Mantidos de propósito (não são violações):
- `usuarios.email` × `pessoa.email`: login × contato; diferem em 2 de 10 registros reais, logo não há dependência funcional.
- `agendamentos.valor` × `procedimentos.valor_base`: preço praticado na data, histórico.
- `pagamento.valor` × `agendamentos.valor`: valor pago; hoje iguais nos 97 pagamentos, mas são fatos diferentes.
- `evolucao_clinica.id_dentista/id_procedimento` × `id_agendamento`: redundantes quando há agendamento, mas `id_agendamento` é opcional (evolução avulsa) e o trigger `validar_evolucao` garante consistência.
- `faturamento_mensal`: fechamentos históricos, documentado como independente. Observação: hoje nenhuma tela lê essa tabela (só `salvarJson('faturamento.json')` grava).

## 3. Otimizações (medidas)

Medição: PHP CLI chamando o mesmo código das páginas, mediana de 5 execuções, Windows 10, 15,9 GB RAM. "Sintético" = cópia do esquema + seed + 2.000 pacientes e 9.168 agendamentos fictícios (sem dados reais).

| Gargalo encontrado | Correção | Sintético antes → depois | Banco real antes → depois |
|---|---|---|---|
| Prontuário: N+1 (1 consulta por dente, todos os prontuários carregados para achar 1) | filtro por paciente + procedimentos agregados em JSON | 4.219 ms / 8.309 consultas → 204 ms / 10 | 214 ms / 309 → 119 ms / 10 |
| `functions.php` carregava 4 views inteiras em **toda** página autenticada, inclusive no AJAX `api_horarios.php` | virou `carregarListas()`, chamada só pelas 12 páginas que usam as listas | ~470 ms → 5 ms (páginas que não usam) | ~145 ms → 5 ms (sem contar conexão) |
| Cliente/dentista carregavam todos os pacientes e agendamentos e filtravam no PHP | `lerJson($fonte, $filtro)` filtra no SQL | cliente 462 → 237 ms; dentista 462 → 180 ms | dentro do ruído da conexão |
| Busca de um único paciente/dentista/usuário carregava a tabela toda (`pacienteSessao`, perfis, `salvarCadastroPaciente`) | filtro no SQL | — | — |
| Resumo financeiro avaliava a view mensal 8 vezes | 1 avaliação para os 6 meses | +480 ms → +~120 ms | — |
| Trigger de sobreposição varria **todos** os agendamentos a cada INSERT/UPDATE | janela de datas que permite usar os índices `agenda_*` | 1.142 → 38 buffers; 88 → 1,7 ms | — |

- **Índices:** nenhum criado nem removido. Após as mudanças os filtros caem em índices existentes (PKs, `idx_agendamentos_id_paciente`, `(id_dentista,id_procedimento)`, parciais `agenda_*`). Vários índices têm 0 leituras, mas as tabelas são minúsculas e eles sustentam a checagem das FKs `RESTRICT`; removê-los não tem ganho medível.
- **Configuração:** não alterada. O banco inteiro (11 MB) cabe em `shared_buffers=128MB`; 9 conexões de 100.
- **Maior custo restante:** abrir a conexão (67–200 ms por requisição, medido). No Windows o PostgreSQL cria um processo por conexão, e a autenticação SCRAM soma ao custo. Não é resolução de `localhost` (127.0.0.1 deu o mesmo tempo). Ver seção 7.

## 4. Compatibilidade com o PHP

Arquivos alterados: `includes/db.php`, `includes/functions.php`, `includes/operacoes.php`, `includes/financeiro.php`, `dono_configuracoes.php`, `dentista_prontuario.php`, os 4 `*_perfil.php`, `cliente_dashboard.php`, `cliente_consultas.php` e uma linha `carregarListas();` nas 12 páginas que usam as listas.

**Bug anterior encontrado no teste e corrigido:** `dentista_prontuario.php?paciente=N` dava *Fatal error* (`emblema()` recebia `null`), porque `localizarPaciente()` só devolve id/nome/CPF. Além disso, o nome exibido era o id. A página agora usa o registro completo do paciente.

## 5. Segurança e migração

- **Backup:** `backups/dentia_20261026_213845_antes_005.dump`, verificado com restauração num banco temporário e contagem idêntica nas 31 tabelas.
- **Migration:** `database/migrations/005_desempenho_e_horario_derivado.sql` (não apaga nada). `schema.sql` foi atualizado e é equivalente ao banco migrado (diff de `pg_dump -s` vazio).
- **Reversão:** `database/migrations/005_reverter.sql`, testada (aplicar → reverter → reaplicar).
- **Aplicada em:** banco local `dentia`. Contagens de linhas idênticas antes e depois. **Não aplicada no Railway.**
- Senhas não aparecem em nenhum arquivo ou saída; os scripts leem o `.env`.

## 6. Testes

| Teste | Resultado |
|---|---|
| `scripts/testar_fluxos_railway.php` (transacional, com ROLLBACK) | passou no banco de desenvolvimento e no local migrado |
| `database/teste_trigger_agenda.sql` (sobreposição na virada da meia-noite, mesmo dia, horário adjacente) | 3/3 |
| Equivalência resultado novo × comportamento antigo (6 usuários de 4 perfis, prontuário, financeiro) | 92/92 |
| HTTP: login de 4 perfis + 24 páginas + `api_horarios.php` via `php -S` | 25/25 com HTTP 200, sem warning/fatal (dev e local) |
| PHPUnit (`tests/`) | **não executado**: PHPUnit/Composer não estão instalados |
| Teste de carga concorrente | não executado |

## 7. Próximos passos

```bash
# aplicar em outro banco (ex.: Railway), depois de um backup:
psql -v ON_ERROR_STOP=1 -d "$DATABASE_URL" -f database/migrations/005_desempenho_e_horario_derivado.sql
psql -v ON_ERROR_STOP=1 -d "$DATABASE_URL" -f database/teste_trigger_agenda.sql
# reverter:
psql -v ON_ERROR_STOP=1 -d "$DATABASE_URL" -f database/migrations/005_reverter.sql
```

Pendências que dependem de decisão:
1. Aplicar a 005 no Railway (produção).
2. `ALTER TABLE clinica DROP COLUMN horario` para concluir a 3FN (destrutivo; a coluna não é mais lida nem gravada).
3. Custo de conexão: avaliar `PDO::ATTR_PERSISTENT` ou PgBouncer. Ambos mudam o comportamento de conexão e exigem teste próprio.
4. Instalar dependências de dev (`composer install`) para rodar o PHPUnit.
5. `vw_pacientes` ainda é carregada inteira nas telas de recepção (lista de pacientes) e na seleção do prontuário; com milhares de pacientes, vale paginar essas telas.
