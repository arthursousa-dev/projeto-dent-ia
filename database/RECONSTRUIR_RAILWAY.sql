-- ATENÇÃO: DESTRUTIVO! Execute APENAS no PostgreSQL de DEMONSTRAÇÃO.
-- APAGA TODAS as tabelas, views, funções e dados do schema public atual.
-- NÃO execute em dados reais e não execute automaticamente no deploy.
--
-- Trava: só roda se o banco terminar em "_demo" ou se a MESMA sessão tiver
-- feito antes:  SET dentia.permitir_reset = 'SIM';
-- O banco "dentia" nunca é aceito. Checagem e DROP ficam no mesmo bloco de
-- propósito: no psql sem ON_ERROR_STOP um erro não impede o comando seguinte.
DO $$
BEGIN
    IF current_database() = 'dentia'
       OR (current_database() !~ '_demo$'
           AND coalesce(current_setting('dentia.permitir_reset', true), '') <> 'SIM') THEN
        RAISE EXCEPTION 'BLOQUEADO: RECONSTRUIR_RAILWAY.sql apagaria o banco "%". Use um banco terminado em _demo ou rode antes: SET dentia.permitir_reset = ''SIM'';', current_database();
    END IF;
    EXECUTE 'DROP SCHEMA IF EXISTS public CASCADE';
    EXECUTE 'CREATE SCHEMA public';
    EXECUTE 'GRANT USAGE, CREATE ON SCHEMA public TO CURRENT_USER';
END
$$;
-- Execute database/schema.sql e, em seguida, database/seed.sql separadamente.
