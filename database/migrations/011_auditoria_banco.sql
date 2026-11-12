-- 011: registro de toda alteração no banco (INSERT, UPDATE, DELETE) para a tela "Banco de dados" do gestor.
-- Guarda quem, quando, tabela, operação, id do registro e QUAIS colunas mudaram — nunca os valores
-- (não duplica dados de pacientes nem hashes de senha). Não altera dados existentes. Faça backup antes.
-- Aplicar:  psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/011_auditoria_banco.sql
-- Reverter: psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/011_reverter.sql
BEGIN;

CREATE TABLE IF NOT EXISTS auditoria_banco (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    momento TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
    tabela TEXT NOT NULL,
    operacao TEXT NOT NULL CHECK (operacao IN ('INSERT', 'UPDATE', 'DELETE')),
    id_registro TEXT,
    colunas TEXT[],          -- colunas alteradas (só no UPDATE)
    usuario TEXT             -- "perfil:e-mail" de quem estava logado no sistema; vazio = script/sistema
);
CREATE INDEX IF NOT EXISTS auditoria_banco_momento ON auditoria_banco (momento DESC);
CREATE INDEX IF NOT EXISTS auditoria_banco_tabela ON auditoria_banco (tabela, momento DESC);

CREATE OR REPLACE FUNCTION registrar_auditoria() RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE novo JSONB; velho JSONB; mudou TEXT[];
BEGIN
    IF TG_OP <> 'DELETE' THEN novo := to_jsonb(NEW); END IF;
    IF TG_OP <> 'INSERT' THEN velho := to_jsonb(OLD); END IF;
    IF TG_OP = 'UPDATE' THEN
        SELECT array_agg(k ORDER BY k) INTO mudou
        FROM jsonb_object_keys(novo) AS k WHERE novo -> k IS DISTINCT FROM velho -> k;
        IF mudou IS NULL THEN RETURN NULL; END IF; -- UPDATE que não mudou nada não entra no registro
    END IF;
    INSERT INTO auditoria_banco (tabela, operacao, id_registro, colunas, usuario)
    VALUES (TG_TABLE_NAME, TG_OP, COALESCE(novo, velho) ->> 'id', mudou,
            NULLIF(current_setting('dentia.usuario', true), ''));
    RETURN NULL;
END $$;

-- Um trigger em cada tabela do sistema (menos o próprio registro).
DO $$
DECLARE t TEXT;
BEGIN
    FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'auditoria_banco' LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS auditoria_banco_registro ON %I', t);
        EXECUTE format('CREATE TRIGGER auditoria_banco_registro AFTER INSERT OR UPDATE OR DELETE ON %I
            FOR EACH ROW EXECUTE FUNCTION registrar_auditoria()', t);
    END LOOP;
END $$;

COMMIT;
