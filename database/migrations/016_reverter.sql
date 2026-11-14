-- Reverte a 016. ATENÇÃO: apaga a situação das faces dos dentes e as faces anotadas nas evoluções clínicas.
-- Faça backup antes (php scripts/backup.php) e só rode com decisão do responsável: é dado de prontuário.
-- Rodar como dentia_migracao: psql -v ON_ERROR_STOP=1 -U dentia_migracao -d dentia -f database/migrations/016_reverter.sql
BEGIN;
DROP TABLE IF EXISTS evolucao_dente_face;
DROP TABLE IF EXISTS odontograma_faces;
DELETE FROM schema_migrations WHERE nome = '016_faces_do_dente.sql';
COMMIT;
