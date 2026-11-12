-- Reverte a 015: a clínica volta a usar o fuso padrão do código (America/Cuiaba). Perde o fuso escolhido em Configurações.
-- Rodar como dentia_migracao: psql -v ON_ERROR_STOP=1 -U dentia_migracao -d dentia -f database/migrations/015_reverter.sql
BEGIN;
ALTER TABLE clinica DROP COLUMN IF EXISTS fuso_horario;
DELETE FROM schema_migrations WHERE nome = '015_fuso_clinica.sql';
COMMIT;
