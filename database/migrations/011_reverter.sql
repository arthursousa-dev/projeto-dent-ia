-- Reverte a 011: remove os triggers de auditoria, a função e o registro (apaga o histórico gravado desde a aplicação).
BEGIN;
DO $$
DECLARE t TEXT;
BEGIN
    FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS auditoria_banco_registro ON %I', t);
    END LOOP;
END $$;
DROP FUNCTION IF EXISTS registrar_auditoria();
DROP TABLE IF EXISTS auditoria_banco;
COMMIT;
