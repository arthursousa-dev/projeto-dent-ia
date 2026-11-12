-- 009: estorno de pagamento com registro (quem, quando, valor, forma, data original e motivo).
-- Antes não havia como desfazer um recebimento: a consulta paga não podia ser cancelada nem receber falta.
-- Só cria tabela; não altera dados existentes. Faça backup antes (backup_banco.bat).
-- Aplicar:  psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/009_estorno_pagamento.sql
-- Reverter: DROP TABLE pagamento_estorno;  (apaga o histórico de estornos gravado desde a aplicação)
BEGIN;
CREATE TABLE IF NOT EXISTS pagamento_estorno (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    -- CASCADE só para os testes PHPUnit que limpam agendamentos; o sistema nunca apaga consultas.
    id_agendamento INT NOT NULL REFERENCES agendamentos ON DELETE CASCADE,
    id_forma_pagamento INT NOT NULL REFERENCES forma_pagamento ON DELETE RESTRICT,
    valor NUMERIC(10,2) NOT NULL CHECK (valor >= 0),
    pago_em TIMESTAMPTZ NOT NULL,
    estornado_em TIMESTAMPTZ NOT NULL DEFAULT now(),
    id_usuario INT NOT NULL REFERENCES usuarios ON DELETE RESTRICT,
    motivo TEXT NOT NULL CHECK (btrim(motivo) <> '')
);
CREATE INDEX IF NOT EXISTS pagamento_estorno_consulta ON pagamento_estorno (id_agendamento);
COMMIT;
