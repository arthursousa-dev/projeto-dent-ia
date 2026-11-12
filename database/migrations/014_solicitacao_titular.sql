-- 014: pedidos do titular dos dados (LGPD art. 18) — cópia, correção e exclusão (P1.2).
-- Não altera nem apaga dados existentes. Aplicar: php scripts/aplicar_migracoes.php
-- Sem BEGIN/COMMIT: o aplicador usa uma transação só.

CREATE TABLE solicitacao_titular (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_paciente INT NOT NULL REFERENCES pacientes ON DELETE RESTRICT,
    tipo TEXT NOT NULL CHECK (tipo IN ('copia', 'correcao', 'exclusao')),
    mensagem TEXT,
    status TEXT NOT NULL DEFAULT 'aberta' CHECK (status IN ('aberta', 'atendida', 'recusada')),
    resposta TEXT,
    criada_em TIMESTAMPTZ NOT NULL DEFAULT now(),
    respondida_em TIMESTAMPTZ,
    respondida_por TEXT                        -- "perfil:e-mail" de quem respondeu
);
CREATE INDEX solicitacao_titular_paciente ON solicitacao_titular (id_paciente, criada_em DESC);
-- Um pedido aberto por tipo: o paciente não empilha pedidos iguais.
CREATE UNIQUE INDEX solicitacao_titular_aberta ON solicitacao_titular (id_paciente, tipo) WHERE status = 'aberta';

CREATE TRIGGER auditoria_banco_registro AFTER INSERT OR UPDATE OR DELETE ON solicitacao_titular
    FOR EACH ROW EXECUTE FUNCTION registrar_auditoria();

-- O pedido é prova de que o direito foi exercido e respondido: a aplicação não apaga.
REVOKE DELETE ON solicitacao_titular FROM dentia_app;
