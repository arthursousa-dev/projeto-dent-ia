-- Reverte a 014. ATENÇÃO: apaga os pedidos dos titulares e as respostas (prova de atendimento da LGPD).
-- Faça backup antes (php scripts/backup.php). As exclusões já atendidas NÃO voltam: os dados de contato foram apagados.
-- Rodar como dentia_migracao: psql -v ON_ERROR_STOP=1 -U dentia_migracao -d dentia -f database/migrations/014_reverter.sql
BEGIN;
DROP TABLE IF EXISTS solicitacao_titular;
DELETE FROM schema_migrations WHERE nome = '014_solicitacao_titular.sql';
COMMIT;
