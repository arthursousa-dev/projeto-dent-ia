# DENT IA — estado de compatibilidade com PostgreSQL Railway

## Descoberta confirmada em 09/10/2026

A aplicação atual foi desenvolvida contra `database/schema.sql` (modelo normalizado: `pessoa`, `pacientes.id_pessoa`, `dentistas.id_sala`, views `vw_*`, pagamentos em `pagamento`). No banco Railway informado pelo usuário, `pacientes` e `dentistas` guardam dados pessoais diretamente, `agendamentos.sala` é texto, e `pessoa`, `sala`, `vw_agendamentos`, `vw_pacientes`, `disponibilidade_dentista`, `funcionamento_clinica` não existem.

**Isso é uma incompatibilidade estrutural entre versões do banco.** Não executar `schema.sql`, `seed.sql` nem migrações normalizadas diretamente no banco existente, nem renomear colunas isoladas: risco de conflitos e inconsistências.

## Para concluir todos os módulos

1. Fazer backup de produção no Railway.
2. Executar `php scripts/auditar_banco_completo_railway.php` no serviço PHP ou no computador conectado ao banco. O arquivo `diagnostico_railway_esquema.json` registra apenas estrutura (nomes de tabelas, colunas e restrições), não dados nem credenciais.
3. Com o inventário, construir uma **migração de legado para normalizado**, com mapeamento explícito de identidade, estrangeiras, pagamentos e prontuários, preservando IDs ou emitindo tabelas de correspondência.
4. Testar a migração primeiro em um clone do banco. Confirmar contagens de pacientes, dentistas, agendamentos e integridade financeira; testar UI dos quatro perfis; promover a migração com plano de rollback.

## Funcionalidade NÃO homologada

Até completar os passos acima e testar via Railway, **não é correto afirmar que todos os módulos estão funcionando**. O script de auditoria não é uma migração nem uma correção de produção.
