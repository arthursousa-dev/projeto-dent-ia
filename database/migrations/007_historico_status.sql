-- 007: histórico de mudanças de status das consultas (quem, quando, de/para). Só cria; não altera dados.
-- Aplicar:  psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/007_historico_status.sql
-- Reverter: DROP TABLE historico_status_agendamento;  (apaga o histórico gravado desde a aplicação)
BEGIN;
CREATE TABLE IF NOT EXISTS historico_status_agendamento (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    -- CASCADE só para os testes PHPUnit que limpam agendamentos; o sistema nunca apaga consultas.
    id_agendamento INT NOT NULL REFERENCES agendamentos ON DELETE CASCADE,
    status_anterior status_agendamento NOT NULL,
    status_novo status_agendamento NOT NULL,
    id_usuario INT NOT NULL REFERENCES usuarios ON DELETE RESTRICT,
    alterado_em TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS historico_status_agendamento_consulta ON historico_status_agendamento (id_agendamento, alterado_em);
COMMIT;
