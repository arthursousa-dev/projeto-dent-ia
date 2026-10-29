# Executar o DENT IA no computador (sem Railway)

Tudo roda nesta máquina, de graça: PHP 8.5 (`C:\Users\Arthur\Desktop\PHP`) e o serviço do Windows `postgresql-x64-18` (porta 5432).
O Railway não é necessário para rodar localmente. `Dockerfile`, `deploy/start.sh` e os guias `docs/*RAILWAY*` continuam no projeto, caso você queira publicar de novo.

## 1. Primeira instalação

1. Copie `.env.example` para `.env` e preencha `DB_PASS` com a senha do usuário `postgres`.
2. Dê um duplo clique em `instalar_banco.bat`. Ele cria o banco `dentia` e carrega `database/schema.sql` e `database/seed.sql`.
   Se o banco `dentia` já existir, o script para sem alterar nada.
3. Para mostrar as credenciais de demonstração nas telas de login, acrescente `APP_DEMO=true` ao `.env` (deixe `false` em produção).

**Banco que já existia antes de 27/10/2026:** aplique, com backup antes (`backup_banco.bat`), as migrações que faltarem em `database\migrations\`, em ordem: `006_status_faltou.sql`, `007_historico_status.sql`, `008_limite_acesso_e_view_usuarios.sql`, `009_estorno_pagamento.sql`, `010_estorno_contabil_e_cnpj.sql`, `011_auditoria_banco.sql`. Exemplo (PowerShell, com `PGPASSWORD` definido):
`psql -U postgres -v ON_ERROR_STOP=1 -d dentia -f database\migrations\008_limite_acesso_e_view_usuarios.sql`

## 2. Iniciar, parar e reiniciar

| Ação | Como fazer |
|---|---|
| Iniciar | duplo clique em `iniciar_dentia.bat` (deixe a janela aberta) |
| Parar | feche a janela, aperte Ctrl+C nela ou rode `parar_dentia.bat` |
| Reiniciar | pare e inicie de novo |
| Banco | é um serviço do Windows e já inicia com o computador. Para iniciar à mão: `services.msc` → postgresql-x64-18 → Iniciar |

Neste computador, acesse `http://localhost:8000`.

## 3. Acesso por outros dispositivos da rede

O `iniciar_dentia.bat` mostra os endereços IPv4 da máquina. Nos outros dispositivos, use o endereço da rede Wi-Fi/Ethernet de casa (normalmente `192.168.x.x`) com a porta 8000, por exemplo `http://192.168.100.118:8000`.
Endereços que começam com `26.` (Radmin VPN) ou `192.168.137.1` (hotspot do Windows) servem só para essas redes.

- O IP pode mudar quando o roteador reinicia. Para fixá-lo, crie uma reserva de DHCP no roteador.
- Hoje o Firewall do Windows está **desligado** (perfis Particular e Público). Se você ligá-lo, libere apenas a porta do DENT IA na rede particular. Rode como administrador:
  `netsh advfirewall firewall add rule name="DENT IA 8000" dir=in action=allow protocol=TCP localport=8000 profile=private`
- O PostgreSQL só aceita logins de 127.0.0.1/::1 (`pg_hba.conf`), então o banco não fica acessível pela rede. Você também pode trocar `listen_addresses = '*'` por `'localhost'` em `postgresql.conf` e reiniciar o serviço.
- Para usar só neste computador, defina `APP_HOST=127.0.0.1` no `.env`.

O `php -S` atende uma requisição por vez. Isso basta para uma clínica pequena na rede local, mas não é um servidor de produção para a internet.
O `router.php` faz o papel do `.htaccess`: serve **só** as páginas PHP da raiz, `css/` e `js/` (lista do que é permitido, não do que é proibido). `.env`, `backups/`, `database/`, `includes/`, `scripts/`, `docs/` e os `.bat` respondem 404, inclusive com truques como `%5c` (barra invertida do Windows). Ele também desliga a exibição de erros e envia os cabeçalhos de segurança (CSP, nosniff, frame).

**Atenção:** pela rede local o sistema usa HTTP sem criptografia; senha e sessão trafegam abertas no Wi-Fi da clínica. Use uma rede confiável (com senha, sem convidados). Para uso só neste computador, `APP_HOST=127.0.0.1`. Para acesso pela internet, é preciso HTTPS (um proxy com certificado na frente do PHP).

## 4. Backup e restauração

- Backup: `backup_banco.bat` gera `backups\dentia_AAAAMMDD_HHMMSS.dump`.
- Restaurar num banco novo, sem tocar no atual, pelo terminal (PowerShell):
  ```
  $env:PGPASSWORD = "<senha>"
  createdb -U postgres dentia_restaurado
  pg_restore -U postgres -d dentia_restaurado backups\dentia_AAAAMMDD_HHMMSS.dump
  ```
  Confira os dados e, se estiver tudo certo, aponte `DB_NAME` no `.env` para `dentia_restaurado`.

## 5. Testes

```
php scripts\testar_fluxos_railway.php
php scripts\testar_melhorias.php
php scripts\testar_seguranca.php
```
O primeiro script faz agendamento, check-in, pagamento, prontuário e despesa. O segundo testa remarcação, limite diário, antecedência mínima, registro de faltas, contas novas sem senha padrão e expiração da sessão. O terceiro testa os itens da banca de revisão (dados de outra pessoa, datas e valores absurdos, pagamento repetido, dentista inativo, mensagens sem detalhe técnico, bloqueio de login) e o **Cadastre-se** do paciente. Os três rodam dentro de uma transação e desfazem tudo no final (ROLLBACK); o terceiro confere no fim que pessoas, usuários e consultas ficaram idênticos.

Para rodar contra a cópia de teste em vez do banco principal (PowerShell): `$env:DB_NAME="dentia_teste"; php scripts\testar_seguranca.php`.
O PHPUnit (`tests/`) depende do Composer, que não está instalado nesta máquina.

### Testar pela tela sem mexer no banco principal
Existe uma cópia fictícia, `dentia_teste`, feita a partir do backup. Para usá-la numa segunda instância, só neste computador (Git Bash):
```
DB_NAME=dentia_teste php -S 127.0.0.1:8001 router.php
```
Abra `http://127.0.0.1:8001/login_recepcionista.php` (recepcao@dentai.com / 123456). Tudo o que for feito ali grava só no `dentia_teste`. Para recriar a cópia, apague-a (`dropdb dentia_teste`) e restaure um backup com `createdb` + `pg_restore` (seção 4).

## 6. Trazer dados do Railway (opcional)

O banco local começa com os dados de demonstração. Se houver dados reais no Railway, pegue a `DATABASE_URL` no painel do Railway e rode no PowerShell:
```
pg_dump -Fc "<DATABASE_URL>" -f backups\railway.dump
createdb -U postgres dentia_railway
pg_restore -U postgres --no-owner -d dentia_railway backups\railway.dump
```
Depois troque `DB_NAME=dentia_railway` no `.env`. O banco do Railway não é alterado por esses comandos.

## 7. Variáveis do `.env` (resumo)
| Variável | Para que serve |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexão com o PostgreSQL |
| `APP_HOST`, `APP_PORT` | Onde o `iniciar_dentia.bat` escuta (`0.0.0.0` = rede local; `127.0.0.1` = só este computador) |
| `APP_DEBUG` | `true` mostra detalhes de erro SQL na tela. Só para depurar |
| `APP_DEMO` | `true` mostra as credenciais de demonstração nas telas de login |
| `APP_PROXY_CONFIAVEL` | `true` só quando o PHP roda atrás de um proxy (Railway, nginx): o limite de tentativas usa o IP real do visitante (último item do `X-Forwarded-For`). Sem proxy, deixe `false` |
| `APP_MONITOR_REDE` | `true` libera a tela "Banco de dados" para o gestor em outros aparelhos da rede. Padrão: só neste computador |

## 8. Ver o banco de dados ao vivo (só o gestor)
Entre como **gestor** neste computador (`http://localhost:8000/login_dono.php`) e abra **Banco de dados** no menu (`dono_banco.php`).
- **Mapa das tabelas (estilo graph view do Obsidian):** cada tabela é um nó, colorido pela área (agenda, clínico, financeiro, pessoas e acesso, sistema) e com tamanho proporcional aos registros; as linhas são as chaves estrangeiras reais. Cada alteração dispara uma partícula do núcleo DENT IA até a tabela, que acende. Arraste nós, role para zoom, arraste o fundo para mover e clique num nó para abrir o **Inspetor** (registros, última alteração, inclusões/alterações/exclusões, tamanho, colunas e tabelas ligadas). Para teclado e leitor de tela, use a **Lista de tabelas**.
- **Pipeline:** o fluxo de eventos no estilo terminal (INSERT, UPDATE, DELETE), com as métricas de tamanho, registros, alterações de hoje e por minuto no topo.
- **O que cada evento mostra:** cada inclusão, alteração e exclusão em qualquer tabela, com hora, tabela, id do registro, **quais colunas** mudaram e quem fez (perfil:e-mail; "login:…" nas tentativas de login; "cadastro-publico:…" no Cadastre-se; "sistema" para scripts). Atualiza sozinho a cada 3 segundos; o botão Pausar congela a tela.
- **Conteúdo da tabela:** clique numa tabela para ver os registros, 50 por página, os mais recentes primeiro.

Segurança da tela:
- Só o perfil gestor e só a partir deste computador. Outro aparelho recebe "página não encontrada", e o link nem aparece no menu. Para liberar a rede, defina `APP_MONITOR_REDE=true` (os dados de pacientes passam a ser visíveis de qualquer aparelho em que o gestor entrar).
- Só leitura: não há como alterar nem apagar nada por ela.
- Senhas, hashes e tokens aparecem como "•••• (oculto)". O registro guarda quais colunas mudaram, nunca os valores.
- O registro (tabela `auditoria_banco`, migração 011) guarda 180 dias; o mais antigo sai sozinho.
