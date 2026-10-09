# DENT IA — Etapa 1: Recepção

## Fluxo implementado

1. Entre com `recepcao@dentai.com` / `123456` (somente dados de demonstração).
2. Acesse **Pacientes**, cadastre/edite um paciente com nome e CPF.
3. Clique em **Agendar** no paciente ou acesse **Novo Agendamento**.
4. Selecione o paciente cadastrado (referência por ID), procedimento, dentista, data e horário permitido.
5. Confirme. A transação grava o `id_paciente` em `agendamentos` no PostgreSQL, e redireciona para a agenda filtrada por data.
6. Na **Agenda do Dia**, confira o nome do paciente e execute check-in, confirmação, cancelamento ou pagamento.
7. O formulário de agendamento rápido no dashboard agora grava efetivamente a consulta.

## Inicialização de banco vazio

```bash
createdb dentia
psql -v ON_ERROR_STOP=1 -d dentia -f database/schema.sql
psql -v ON_ERROR_STOP=1 -d dentia -f database/seed.sql
export DB_HOST=localhost DB_PORT=5432 DB_NAME=dentia DB_USER=postgres DB_PASS='sua-senha'
php -S localhost:8000
```

Requer PHP 8.1+, extensão `pdo_pgsql` e PostgreSQL. Não aplique `schema.sql` sobre uma base existente: ele é para base vazia. Os dados do `seed.sql` são **fictícios**. Configure a conexão por variáveis de ambiente (arquivo `.env` por si só não é carregado pelo PHP).

## Observações e limites

Esta é a etapa 1, focada na recepção. As demais áreas seguem o código recebido, sem garantia de integração completa. O agendamento depende de funcionamento da clínica, disponibilidade do dentista e procedimento permitido; não cria reservas em conflito. O ambiente de execução de edição não possui extensão `pdo_pgsql` nem servidor PostgreSQL: verificação real de ponta a ponta e testes SQL precisam ser executados após configurar o PostgreSQL.
