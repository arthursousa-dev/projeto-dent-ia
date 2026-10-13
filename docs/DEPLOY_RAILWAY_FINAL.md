# DENT IA — instalação integrada no Railway (PostgreSQL)

## O que está incluso

- Projeto PHP original com visuais e áreas de dono, recepção, dentista e paciente.
- Esquema normalizado PostgreSQL `database/schema.sql`, dados de exemplo `database/seed.sql`, com proprietário **Arthur Sousa**.
- Agendamento com paciente, dentista, procedimento e sala vinculados por chaves estrangeiras.
- Verificação de expediente, horários do dentista, duração do procedimento e conflitos de paciente/profissional/sala.
- Listagem diária diretamente do PostgreSQL; pagamentos, evolução clínica, prontuários e resumo financeiro.
- API `api_horarios.php` para horários disponíveis; teste transacional que desfaz todas as gravações.
- Dockerfile para incluir `pdo_pgsql` no Railway, Apache e bloqueio HTTP de diretórios internos.

## 1. Código (não mexe no banco)

1. Faça backup dos arquivos atuais. Extraia o ZIP **dentro da pasta existente do repositório**, preservando a pasta `.git` local. Este ZIP não contém `.git`.
2. Se seu prompt mostrar `|MERGING`, rode `git status` e resolva o merge antes de tentar qualquer outro push. Caso queira CANCELAR o merge anterior: `git merge --abort`.
3. No terminal da pasta do repositório execute:

```bash
git status --short
git restore --staged .
git add Dockerfile .dockerignore .htaccess deploy/ api_horarios.php health.php
git add app/ includes/ database/ scripts/ js/ docs/DEPLOY_RAILWAY_FINAL.md README.md
git add cliente_agendamento.php recepcionista_novo_agendamento.php recepcionista_dashboard.php
git add login_dono.php login_dentista.php login_recepcionista.php login_paciente.php
git diff --cached --stat
git commit -m "Integra fluxo completo PostgreSQL Railway"
git push origin feat/normalizacao-banco
```

4. Se o Railway usa `master`, abra um **Pull Request** no GitHub de `feat/normalizacao-banco` para `master`, resolva eventuais conflitos pela interface e faça o merge. Não use `push --force` nem sobrescreva `master` sem incorporar os commits remotos.
5. Confirme no Railway o deploy dessa versão. Em Settings, a compilação deve detectar `Dockerfile`. O driver `pdo_pgsql` é instalado na imagem.
6. Em **Variables do serviço PHP**, configure `DATABASE_URL` apontando ao serviço PostgreSQL (Railway geralmente aceita referência `${{Postgres.DATABASE_URL}}`). Não exponha a URL publicamente. Se houver `DB_HOST=localhost`/`DB_*` antigos no serviço PHP, remova-os ou configure todos para o mesmo PostgreSQL: `DB_*` têm precedência.

## 2. Recriação do banco fictício — destrutivo, apenas uma vez

**ATENÇÃO:** como todos os dados são de exemplo e você autorizou reconstruí-los, esta etapa remove todas as tabelas do schema `public` do **banco selecionado**, não apenas as tabelas DENT IA. Não execute se o banco tiver informações reais ou de outros projetos. Antes, faça um backup/snapshot no Railway e confira as informações de conexão.

No terminal do **serviço PHP** do Railway, defina temporariamente a variável `DENTIA_PERMITIR_RESET=SIM`; ou execute **no seu Git Bash local**, se o PHP local já está conectado ao PostgreSQL do Railway (como no diagnóstico anterior), usando o prefixo abaixo. **Primeiro, verifique o banco sem apagar nada:**

```bash
DENTIA_PERMITIR_RESET=SIM php scripts/instalar_demo_railway.php --confirmar-reset-demo
```

Ele deve apresentar a identidade do banco e interromper ANTES de apagar qualquer coisa. **Somente após conferir** o banco de destino e confirmar que tudo é fictício, execute:

```bash
DENTIA_PERMITIR_RESET=SIM php scripts/instalar_demo_railway.php --confirmar-reset-demo --apagar-mesmo
```

Em um terminal do Railway onde a variável já esteja definida, omita o prefixo `DENTIA_PERMITIR_RESET=SIM` das duas linhas. Isso recria tabelas, views, relacionamentos, pacientes, equipe, agendamentos históricos, lançamentos e **Arthur Sousa** como proprietário. Tudo é transacional; uma falha antes do COMMIT provoca rollback. **Não deixe `DENTIA_PERMITIR_RESET` configurada permanentemente no Railway.**

Não execute `schema.sql` ou `seed.sql` separadamente após usar o instalador. Não execute migrations antigas por cima desse schema: o schema já contém as funcionalidades finais.

## 3. Verificação de integração — sem alterar registros reais

```bash
php scripts/testar_fluxos_railway.php
```

Saída esperada: cada tabela/view `[OK]`, dono Arthur Sousa, INSERT e consulta de agendamento, bloqueio de conflito, check-in, pagamento, prontuário, odontograma, lançamento financeiro, resumo e, ao final, `[OK] ROLLBACK`. Em caso de erro, o script imprime `[ERRO]` e também executa rollback.

Além disso, abra `https://SEU-DOMINIO/health.php`. Esperado: `{"status":"ok"}`. Se mostrar `indisponivel`, veja o log do serviço Railway (sem compartilhar senhas).

## 4. Teste manual de recepção

1. Login: `recepcao@dentai.com` / `123456` (somente na base de demonstração).
2. Entre em Pacientes e cadastre um paciente usando **CPF válido**, ou use um dos pacientes fictícios existentes.
3. Crie consulta com data futura e um horário **disponível para o profissional e procedimento escolhidos**; verifique o ID retornado.
4. Acesse Agenda, filtre a data da consulta: deve aparecer somente nessa data. Faça check-in e receba o pagamento.
5. No dono, entre em Faturamento no mês do recebimento; a receita é contabilizada **pelo dia do pagamento**.
6. Entre como dentista para registrar prontuário/evolução; veja os dados da consulta.

Demais logins de demonstração no `database/seed.sql`: `dono@dentai.com`, `dentista@dentai.com`, `cliente@gmail.com`; senha padrão `123456`. Troque essas senhas antes de usar qualquer dado real.

## Se falhar

- `could not find driver`: deploy não está usando o Dockerfile / falta `pdo_pgsql`.
- `relation ... does not exist`: reinstalação não foi aplicada ao banco que o PHP está usando.
- `SQLSTATE 23505`: restrição de horário, CPF ou outro valor único (o erro aparece nos logs do Railway).
- `Everything up-to-date`: faltou `git commit` com os arquivos extraídos na pasta certa, ou o push está indo para a branch diferente da configurada no Railway.
- `non-fast-forward`: a branch remota divergiu. Abra PR e resolva o merge; nunca use force push para ignorá-la.

**Limite de validação:** o pacote foi checado com PHP 8.4 no ambiente de preparação e a base fictícia foi verificada estaticamente. Para afirmar funcionamento de ponta a ponta, é necessário executar o teste `scripts/testar_fluxos_railway.php` contra o PostgreSQL do seu Railway (este ambiente não tem `pdo_pgsql` nem acesso ao seu serviço).
