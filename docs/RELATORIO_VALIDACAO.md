# Relatório de validação — DENT IA / PostgreSQL Railway

## Verificações executadas no ambiente de preparação

- **59 arquivos PHP:** análise sintática `php -l`, sem erros.
- JavaScript de disponibilidade e acessibilidade: `node --check`, sem erros.
- Script de inicialização do Apache: `sh -n`, sem erros.
- `database/seed.sql`: 17 CPFs válidos, 26 telefones válidos conforme CHECKs.
- 24 procedimentos e 18 pares habilitados `dentista_procedimentos`.
- 159 agendamentos fictícios examinados: zero conflitos de duração por dentista/paciente/sala e zero procedimentos sem vínculo com dentista.
- 10 hashes de login do seed testados via `password_verify` com a senha de demonstração `123456`.
- O nome do dono no seed é `Arthur Sousa`.
- O instalador só permite operações destrutivas com dois parâmetros explícitos e variável de ambiente.

## Verificações que precisam do Railway

**Não foram executados** SQL `schema.sql`/`seed.sql` em um PostgreSQL real nem testes HTTP integrados neste ambiente, pois não há servidor PostgreSQL nem extensão PHP `pdo_pgsql` disponíveis; também não há acesso autorizado ao seu serviço Railway.

A instalação real no Railway requer rodar `php scripts/instalar_demo_railway.php --confirmar-reset-demo --apagar-mesmo`, e depois `php scripts/testar_fluxos_railway.php`. Este segundo script exercita as principais escritas e consultas e termina com `ROLLBACK` sem manter dados de teste. Só declare o sistema pronto após obter `[OK]` em todos os testes e validar os fluxos no navegador.
