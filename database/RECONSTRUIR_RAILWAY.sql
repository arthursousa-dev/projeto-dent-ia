-- ATENÇÃO: DESTRUTIVO! Execute APENAS no PostgreSQL de DEMONSTRAÇÃO.
-- APAGA TODAS as tabelas, views, funções e dados do schema public atual.
-- NÃO execute em dados reais e não execute automaticamente no deploy.
DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;
GRANT USAGE, CREATE ON SCHEMA public TO CURRENT_USER;
-- Execute database/schema.sql e, em seguida, database/seed.sql separadamente.
