-- 012: a aplicação deixa de usar o superusuário do PostgreSQL (P0.2).
--   dentia_app      -> usado pelo site (.env DB_USER). Lê e grava dados; não muda estrutura; auditoria só leitura.
--   dentia_migracao -> dono das tabelas. Usado por scripts/aplicar_migracoes.php, limpeza e manutenção.
-- Não altera nem apaga dados. Os papéis nascem SEM senha (ninguém entra com eles até
-- scripts/configurar_papeis.php gerar as senhas e gravar no .env).
-- Aplicar: php scripts/aplicar_migracoes.php  (precisa de superusuário só nesta, por criar papéis).
-- Sem BEGIN/COMMIT: o aplicador roda o arquivo e o registro em schema_migrations numa transação só.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'dentia_app') THEN
        CREATE ROLE dentia_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'dentia_migracao') THEN
        -- CREATEDB: só para criar e apagar o banco temporário do teste de restauração (scripts/backup.php).
        CREATE ROLE dentia_migracao LOGIN NOSUPERUSER CREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
    END IF;
END $$;

-- dentia_migracao vira dono de tudo no schema public (tabelas levam junto as sequences de identidade).
GRANT USAGE, CREATE ON SCHEMA public TO dentia_migracao;
DO $$
DECLARE o RECORD;
BEGIN
    FOR o IN SELECT c.oid::regclass AS nome, c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm')
             UNION ALL
             SELECT c.oid::regclass, c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind = 'S'
               AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = c.oid AND d.deptype IN ('a', 'i')) LOOP
        EXECUTE format('ALTER %s %s OWNER TO dentia_migracao',
            CASE o.relkind WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW' WHEN 'S' THEN 'SEQUENCE' ELSE 'TABLE' END, o.nome);
    END LOOP;
    FOR o IN SELECT p.oid::regprocedure AS nome FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public' LOOP
        EXECUTE format('ALTER ROUTINE %s OWNER TO dentia_migracao', o.nome);
    END LOOP;
    FOR o IN SELECT t.oid::regtype AS nome FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
             WHERE n.nspname = 'public' AND t.typtype IN ('e', 'd') LOOP
        EXECUTE format('ALTER TYPE %s OWNER TO dentia_migracao', o.nome);
    END LOOP;
END $$;

-- O trigger grava a auditoria com o privilégio do dono; a aplicação não escreve nela direto.
ALTER FUNCTION registrar_auditoria() SECURITY DEFINER SET search_path = public, pg_temp;

-- dentia_app: dados sim, estrutura não.
GRANT USAGE ON SCHEMA public TO dentia_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO dentia_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO dentia_app;
REVOKE INSERT, UPDATE, DELETE ON auditoria_banco, schema_migrations FROM dentia_app;

-- Tabelas criadas por migrações futuras já nascem com o mesmo acesso.
ALTER DEFAULT PRIVILEGES FOR ROLE dentia_migracao IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO dentia_app;
ALTER DEFAULT PRIVILEGES FOR ROLE dentia_migracao IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO dentia_app;
