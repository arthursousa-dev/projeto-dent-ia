# DENT IA — reconstrução do PostgreSQL de demonstração

**Dono preservado no seed: Arthur Sousa (`dono@dentai.com`).**

Este pacote destina-se apenas à base fictícia. Faça um backup antes de qualquer exclusão.

1. No Railway, confira se o serviço web aponta para o PostgreSQL correto e execute um backup.
2. Execute `database/RECONSTRUIR_RAILWAY.sql` **manualmente** usando o editor SQL do banco ou `psql` (destrutivo).
3. Execute `database/schema.sql` inteiro, em seguida `database/seed.sql` inteiro. Não rode o reset em cada deploy.
4. No serviço PHP, configure `DATABASE_URL` ou os campos `PGHOST`, `PGDATABASE`, `PGUSER`, `PGPASSWORD` e `PGPORT`. Certifique-se de que `pdo_pgsql` está instalado.
5. Execute `php scripts/verificar_reconstrucao.php` no mesmo ambiente do projeto.
6. Entre na recepção, crie consulta em dia futuro, confira o filtro por dia, marque check-in e pagamento; valide o financeiro.

**Limitação:** sintaxe PHP validada, mas este ZIP não foi executado no PostgreSQL do usuário. Nem todos os módulos foram testados ponta a ponta. Se houver erros, use o log do Railway como fonte de diagnóstico. Não publique essa demonstração como sistema de produção com dados de pacientes.
