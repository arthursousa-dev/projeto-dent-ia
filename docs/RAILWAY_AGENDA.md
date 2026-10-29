# Railway e agendamentos

1. No serviço web do Railway, defina `DATABASE_URL=${{Postgres.DATABASE_URL}}` em Variables (troque `Postgres` pelo nome exato do serviço de banco). Alternativamente use `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD` e `PGDATABASE` ou `DB_*`.
2. Se definir `DB_HOST` como localhost por engano, ele **tem precedência** sobre a URL. Remova DB_* conflitantes da aplicação.
3. Certifique-se de que o build PHP habilite `pdo_pgsql` e `pgsql`. Execute `php -m` e procure por `pdo_pgsql`.
4. Em Shell do serviço WEB do Railway, execute `php scripts/testar_agendamentos_railway.php` para conferir tabelas e simular gravação com rollback.
5. Veja Deploy Logs para erros com código `[DENTIA][xxxxxxxx]` e SQLSTATE.
6. Em uma implantação temporária, `APP_DEBUG=true` pode mostrar detalhes de SQL para diagnóstico. **Desative após testar**. Não compartilhe a DATABASE_URL nem senhas.
7. Confirme a implantação do código atualizado (deploy bem-sucedido). As consultas já são filtradas diretamente por data e dentista na agenda.
8. NÃO rode `seed.sql` ou `schema.sql` em um banco já populado: eles são destinados à instalação inicial.

Nota: testes automatizados no container sem PostgreSQL não demonstram a implantação em produção.
