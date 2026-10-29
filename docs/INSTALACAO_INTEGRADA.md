# Instalação integrada: DENT IA + PostgreSQL

## Requisitos
- PHP 8.1+, com `pdo_pgsql`/`php-pgsql` habilitado; PostgreSQL 14+; terminal (ou pgAdmin para executar SQL).
- Coloque o projeto numa pasta servida pelo PHP (por exemplo, `htdocs/dentia-pro`).

## Banco NOVO (atenção: não execute schema.sql sobre banco já existente)
1. Crie o banco `dentia` no PostgreSQL.
2. Execute `database/schema.sql` nesse banco; aguarde terminar sem erro.
3. Execute `database/seed.sql` **uma vez** para carregar os dados fictícios, logins e movimentações de demonstração.
4. Copie `.env.example` para `.env`, preencha `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS`. O sistema carrega `.env` automaticamente; nunca coloque essas credenciais no Git.
5. Execute `php scripts/diagnostico.php` e corrija qualquer item marcado `[ERRO]`.
6. Rode `php -S 127.0.0.1:8000` na pasta raiz e abra `http://127.0.0.1:8000`.

## Teste completo de integração pelo navegador
1. Recepcionista (`recepcao@dentai.com`, `123456`): cadastrar paciente válido, abrir novo agendamento e escolher o ID do paciente, dentista/procedimento, data e horário disponíveis.
2. Conferir na agenda da data escolhida se o paciente aparece com o procedimento correto; fazer check-in e alterar status conforme permitido.
3. Entrar como dentista (`dentista@dentai.com`, `123456`): verificar agenda e registrar evolução no prontuário de paciente vinculado.
4. Entrar como paciente (`cliente@gmail.com`, `123456`): visualizar **suas próprias** consultas e criar agendamento se houver disponibilidade.
5. Entrar como dono (`dono@dentai.com`, `123456`): verificar dashboards, contas a receber, confirmar pagamento e comparar o faturamento com o recebimento efetivamente lançado.
6. Execute o diagnóstico novamente; caso a interface apresente erro, consulte o log do PHP e preserve os dados para investigação.

## Importante
Apenas o código PHP foi verificado sintaticamente no ambiente de preparação deste pacote. Não havia servidor PostgreSQL nem `pdo_pgsql` disponível para realizar teste de ponta a ponta: **nenhum resultado deve ser tratado como homologação funcional real**. A verificação de diagnóstico também não faz gravações para não alterar os dados da clínica.

O banco de demonstração é destinado a testes. Não utilize CPF, ficha médica nem dados pessoais reais nessa instalação de demonstração até realizar uma auditoria de segurança, backups e revisão LGPD.
