-- Reverte a 013. ATENÇÃO: apaga os consentimentos, o registro de leituras e o histórico de retificações
-- gravados desde a aplicação. Faça backup antes (php scripts/backup.php) e só rode com decisão do responsável.
-- Rodar como dentia_migracao: psql -v ON_ERROR_STOP=1 -U dentia_migracao -d dentia -f database/migrations/013_reverter.sql
BEGIN;
DROP TRIGGER IF EXISTS evolucao_dente_so_acrescimo ON evolucao_dente;
DROP TRIGGER IF EXISTS evolucao_clinica_so_acrescimo ON evolucao_clinica;
DROP FUNCTION IF EXISTS bloquear_alteracao_clinica();
DROP TRIGGER IF EXISTS historico_clinico_arquivo ON historico_clinico;
DROP FUNCTION IF EXISTS arquivar_historico_clinico();
DROP TABLE IF EXISTS historico_clinico_retificado;
ALTER TABLE historico_clinico DROP COLUMN IF EXISTS registrado_em, DROP COLUMN IF EXISTS registrado_por;
DROP TABLE IF EXISTS acesso_prontuario;
DROP TABLE IF EXISTS consentimento;
DELETE FROM schema_migrations WHERE nome = '013_lgpd_prontuario.sql';
COMMIT;
