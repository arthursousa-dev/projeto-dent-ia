-- Reverte a 012: devolve a posse dos objetos a quem roda este arquivo (o superusuário) e tira o acesso do dentia_app.
-- Antes de rodar, volte o .env para o usuário antigo, senão o site para de abrir.
-- Os papéis dentia_app e dentia_migracao continuam existindo (valem para o servidor inteiro e podem
-- ter acesso a outro banco); para removê-los depois de reverter em todos os bancos: DROP ROLE.
-- Rodar: psql -v ON_ERROR_STOP=1 -U postgres -d dentia -f database/migrations/012_reverter.sql
BEGIN;
ALTER DEFAULT PRIVILEGES FOR ROLE dentia_migracao IN SCHEMA public REVOKE ALL ON TABLES FROM dentia_app;
ALTER DEFAULT PRIVILEGES FOR ROLE dentia_migracao IN SCHEMA public REVOKE ALL ON SEQUENCES FROM dentia_app;
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM dentia_app;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM dentia_app;
REVOKE ALL ON SCHEMA public FROM dentia_app, dentia_migracao;
ALTER FUNCTION registrar_auditoria() SECURITY INVOKER RESET search_path;
DO $$
DECLARE o RECORD;
BEGIN
    FOR o IN SELECT c.oid::regclass AS nome, c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm')
             UNION ALL
             SELECT c.oid::regclass, c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind = 'S'
               AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = c.oid AND d.deptype IN ('a', 'i')) LOOP
        EXECUTE format('ALTER %s %s OWNER TO CURRENT_USER',
            CASE o.relkind WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW' WHEN 'S' THEN 'SEQUENCE' ELSE 'TABLE' END, o.nome);
    END LOOP;
    FOR o IN SELECT p.oid::regprocedure AS nome FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public' LOOP
        EXECUTE format('ALTER ROUTINE %s OWNER TO CURRENT_USER', o.nome);
    END LOOP;
    FOR o IN SELECT t.oid::regtype AS nome FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
             WHERE n.nspname = 'public' AND t.typtype IN ('e', 'd') LOOP
        EXECUTE format('ALTER TYPE %s OWNER TO CURRENT_USER', o.nome);
    END LOOP;
END $$;
DELETE FROM schema_migrations WHERE nome = '012_papeis_aplicacao.sql';
COMMIT;
