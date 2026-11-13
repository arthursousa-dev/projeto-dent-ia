# Executar o DENT IA no computador (sem Railway)

Tudo roda nesta máquina, de graça: PHP 8.5 (`C:\Users\Arthur\Desktop\PHP`) e o serviço do Windows `postgresql-x64-18` (porta 5432).
O Railway não é necessário para rodar localmente. `Dockerfile`, `deploy/start.sh` e os guias `docs/*RAILWAY*` continuam no projeto, caso você queira publicar de novo.

## 1. Primeira instalação

1. Copie `.env.example` para `.env` e preencha `DB_PASS` com a senha do usuário `postgres`.
2. Dê um duplo clique em `instalar_banco.bat`. Ele cria o banco `dentia` e carrega `database/schema.sql` e `database/seed.sql`.
   Se o banco `dentia` já existir, o script para sem alterar nada.
3. Rode `php scripts\aplicar_migracoes.php`. Ele aplica as migrações a partir da 012 (o `schema.sql` já traz até a 011), cada uma em transação, e anota em `schema_migrations`. Em banco que não é de teste, ele faz um backup em `backups\` antes; se o backup falhar, nada é aplicado.
4. Rode `php scripts\configurar_papeis.php` (uma vez). Ele tira o superusuário do `.env`: o site passa a conectar como `dentia_app` e a manutenção como `dentia_migracao`, com senhas aleatórias que não aparecem na tela. O acesso antigo fica em `.env.superusuario`: guarde a senha num gerenciador de senhas e apague esse arquivo.
5. Para mostrar as credenciais de demonstração nas telas de login, acrescente `APP_DEMO=true` ao `.env` (deixe `false` em produção).

Migrações novas: crie `database\migrations\NNN_nome.sql` **sem** `BEGIN`/`COMMIT` e um `NNN_reverter.sql`, e rode `php scripts\aplicar_migracoes.php`. Para só ver o que falta: `php scripts\aplicar_migracoes.php --listar`.

**Banco que já existia antes de 27/10/2026:** aplique, com backup antes (`backup_banco.bat`), as migrações que faltarem em `database\migrations\`, em ordem: `006_status_faltou.sql`, `007_historico_status.sql`, `008_limite_acesso_e_view_usuarios.sql`, `009_estorno_pagamento.sql`, `010_estorno_contabil_e_cnpj.sql`, `011_auditoria_banco.sql`. Exemplo (PowerShell, com `PGPASSWORD` definido):
`psql -U postgres -v ON_ERROR_STOP=1 -d dentia -f database\migrations\008_limite_acesso_e_view_usuarios.sql`

## 2. Iniciar, parar e reiniciar

| Ação | Como fazer |
|---|---|
| Iniciar | duplo clique em `iniciar_dentia.bat` (deixe a janela aberta) |
| Parar | feche a janela, aperte Ctrl+C nela ou rode `parar_dentia.bat` |
| Reiniciar | pare e inicie de novo |
| Banco | é um serviço do Windows e já inicia com o computador. Para iniciar à mão: `services.msc` → postgresql-x64-18 → Iniciar |

Neste computador, acesse **`https://localhost`** (HTTPS). O endereço antigo, `http://localhost:8000`, continua funcionando, sem criptografia.

### HTTPS neste computador (desde 13/11/2026)

O `iniciar_dentia.bat` sobe junto o Caddy (`ferramentas\caddy.exe`, versão 2.11.9), que fica na frente do PHP e atende em `https://localhost` (porta 443, só neste computador). A configuração está no arquivo `Caddyfile`, na raiz do projeto. O `parar_dentia.bat` encerra os dois.

- **Primeira vez, para o navegador não mostrar "site não seguro":** rode uma vez, na pasta do projeto, `ferramentas\caddy.exe trust` e aceite a pergunta do Windows. Isso põe o certificado local do Caddy na lista de confiança deste computador. Sem esse passo o HTTPS funciona, mas o navegador avisa e você precisa clicar em "avançar".
- O certificado é local (emitido pelo próprio Caddy nesta máquina). Ele vale para `localhost` e `127.0.0.1`, e é renovado sozinho.
- No `.env`, `APP_PROXY_CONFIAVEL=true` faz o PHP confiar no que o Caddy informa: o cookie de sessão só trafega em HTTPS e a tela "Banco de dados" continua abrindo só neste computador.
- A porta 80 é usada pelo Windows nesta máquina, por isso não há redirecionamento automático de `http://localhost` para `https://localhost`.
- O `ferramentas\caddy.exe` não vai para o Git. Em outra máquina, baixe o Caddy em caddyserver.com/download e ponha o `caddy.exe` nessa pasta.
- **Rede da clínica:** o HTTPS está fechado para este computador (`bind 127.0.0.1` no `Caddyfile`). Abrir para outros aparelhos pede três coisas antes: ligar o Firewall do Windows com regra só para a porta 443, trocar o `bind` e o endereço no `Caddyfile` pelo nome ou IP da máquina, e instalar o certificado raiz do Caddy em cada aparelho.

## 3. Acesso por outros dispositivos da rede

**Padrão desde 12/11/2026: o sistema abre só neste computador** (`APP_HOST=127.0.0.1`). Não mude para `0.0.0.0` enquanto não houver HTTPS na frente do PHP e o Firewall do Windows estiver desligado: na rede, senha e sessão trafegam abertas. O restante desta seção vale só para quem decidir abrir a rede.

Com `APP_HOST=0.0.0.0`, o `iniciar_dentia.bat` mostra os endereços IPv4 da máquina. Nos outros dispositivos, use o endereço da rede Wi-Fi/Ethernet de casa (normalmente `192.168.x.x`) com a porta 8000, por exemplo `http://192.168.100.118:8000`.
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

- Backup automático: `php scripts\backup.php` gera `backups\auto_dentia_AAAAMMDD_HHMMSS.dump` e aplica a rotação (ficam 7 diários e 4 semanais). A rotação só apaga arquivos `auto_*` do mesmo banco; backups manuais nunca são apagados por ela.
- Teste de restauração: `php scripts\backup.php --testar-restauracao` faz o backup, restaura no banco temporário `dentia_restore_teste`, compara as linhas de cada tabela com o banco original, apaga o temporário e anota o resultado em `backups\restauracoes.log`. Rode uma vez por mês, com a clínica fechada.
- Limpeza dos registros técnicos: `php scripts\limpar_registros.php` (tentativas de acesso com mais de 1 dia e auditoria de tabelas técnicas com mais de 180 dias). A auditoria de pacientes, agenda e dinheiro não é apagada.
- Para agendar no Windows (Agendador de Tarefas), rode uma vez no PowerShell, na pasta do projeto:
  ```
  schtasks /Create /TN "DENT IA backup diario" /SC DAILY /ST 02:00 /TR "C:\Users\Arthur\Desktop\PHP\php.exe C:\Users\Arthur\Downloads\DENTIA\scripts\backup.php"
  schtasks /Create /TN "DENT IA teste de restauracao" /SC MONTHLY /D 1 /ST 03:00 /TR "C:\Users\Arthur\Desktop\PHP\php.exe C:\Users\Arthur\Downloads\DENTIA\scripts\backup.php --testar-restauracao"
  schtasks /Create /TN "DENT IA limpeza" /SC DAILY /ST 02:30 /TR "C:\Users\Arthur\Desktop\PHP\php.exe C:\Users\Arthur\Downloads\DENTIA\scripts\limpar_registros.php"
  ```
- **Ainda não feito:** os backups ficam sem criptografia e no mesmo disco do banco. Falta decidir a cópia para fora da máquina.
- Backup manual: `backup_banco.bat` gera `backups\dentia_AAAAMMDD_HHMMSS.dump`.
- Restaurar num banco novo, sem tocar no atual, pelo terminal (PowerShell), com a senha do `postgres`:
  ```
  $env:PGPASSWORD = "<senha>"
  createdb -U postgres dentia_restaurado
  pg_restore -U postgres --no-owner -d dentia_restaurado backups\dentia_AAAAMMDD_HHMMSS.dump
  ```
  Confira os dados e, se estiver tudo certo, aponte `DB_NAME` no `.env` para `dentia_restaurado`.

## 5. Testes

**Um comando para tudo** (Git Bash, na pasta do projeto): `bash scripts/verificar_tudo.sh`. Ele roda a sintaxe (`php -l`), a análise estática (PHPStan nível 5), o PHPUnit e os três scripts, e sai com código diferente de zero se algo falhar. Usa o banco `dentia_limpo_teste`; para outro banco de teste, passe o nome: `bash scripts/verificar_tudo.sh dentia_teste`. Recusa banco que não termine em `_teste`.

**Banco de teste sem dados de backup:** `php scripts/criar_banco_teste.php` cria `dentia_limpo_teste` só com `schema.sql`, `seed.sql` e as migrações. Use este para os testes: o `dentia_teste` antigo é cópia de um backup do banco principal. Para refazer, apague antes com `dropdb -U dentia_migracao dentia_limpo_teste`.

**Análise estática:** `php vendor/bin/phpstan analyse --memory-limit=1G` (PHPStan, só desenvolvimento). Os 50 apontamentos que já existiam em 12/11/2026 estão em `phpstan-baseline.neon`: quase todos são variáveis criadas por `carregarListas()` ou pela página que inclui o arquivo, que a ferramenta não enxerga. Erro novo aparece e faz a análise falhar.

Cada parte em separado, sempre contra um banco de teste, nunca contra o `dentia` (Git Bash):
```
DB_NAME=dentia_teste php vendor/bin/phpunit
DB_NAME=dentia_teste php scripts/testar_fluxos_railway.php
DB_NAME=dentia_teste php scripts/testar_melhorias.php
DB_NAME=dentia_teste php scripts/testar_seguranca.php
```
Em 12/11/2026: PHPUnit com 81 testes (1 pulado de propósito: só o dono do schema consegue apagá-lo) e os três scripts verdes, conectando como `dentia_app`.

**Atenção:** `testar_seguranca.php` precisa de consultas de hoje que já começaram. Na primeira hora do dia (00:00 a 01:00) ele não roda: avisa `[NÃO RODOU]` e sai com código 2. Confira sempre a linha final `TODOS OS TESTES DE SEGURANÇA E CADASTRO PASSARAM` ou o código de saída.
O primeiro script faz agendamento, check-in, pagamento, prontuário e despesa. O segundo testa remarcação, limite diário, antecedência mínima, registro de faltas, contas novas sem senha padrão e expiração da sessão. O terceiro testa os itens da banca de revisão (dados de outra pessoa, datas e valores absurdos, pagamento repetido, dentista inativo, mensagens sem detalhe técnico, bloqueio de login) e o **Cadastre-se** do paciente. Os três rodam dentro de uma transação e desfazem tudo no final (ROLLBACK); o terceiro confere no fim que pessoas, usuários e consultas ficaram idênticos.

Para rodar contra a cópia de teste em vez do banco principal (PowerShell): `$env:DB_NAME="dentia_teste"; php scripts\testar_seguranca.php`.
PHPUnit 13 (`tests/`, só desenvolvimento; exige PHP 8.4+), sempre contra a cópia de teste (Git Bash): `DB_NAME=dentia_teste php vendor/bin/phpunit`.

### Testar pela tela sem mexer no banco principal
Existe uma cópia fictícia, `dentia_teste`, feita a partir do backup. Para usá-la numa segunda instância, só neste computador (Git Bash):
```
DB_NAME=dentia_teste php -S 127.0.0.1:8001 router.php
```
Abra `http://127.0.0.1:8001/login_recepcionista.php` (recepcao@dentai.com / 123456). Como essa é a senha de demonstração, o sistema leva direto para "Crie uma nova senha" (seção 9); trocar ali muda a senha só no `dentia_teste`, e os scripts de teste que usam `123456` para essa conta deixam de passar até você restaurar a cópia. Tudo o que for feito ali grava só no `dentia_teste`. Para recriar a cópia, apague-a (`dropdb dentia_teste`) e restaure um backup com `createdb` + `pg_restore` (seção 4).

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
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexão do site com o PostgreSQL. Depois de `configurar_papeis.php`, o usuário é `dentia_app` (sem poder de administrador) |
| `DB_MIGRACAO_USER`, `DB_MIGRACAO_PASS` | Papel `dentia_migracao`, dono das tabelas. Usado só por `aplicar_migracoes.php`, `backup.php` e `limpar_registros.php` |
| `APP_HOST`, `APP_PORT` | Onde o `iniciar_dentia.bat` escuta (`127.0.0.1` = só este computador, o padrão; `0.0.0.0` = rede local) |
| `APP_DEBUG` | `true` mostra detalhes de erro SQL na tela. Só para depurar |
| `APP_DEMO` | `true` mostra as credenciais de demonstração nas telas de login |
| `APP_PROXY_CONFIAVEL` | `true` só quando o PHP roda atrás de um proxy (Railway, nginx): o limite de tentativas usa o IP real do visitante (último item do `X-Forwarded-For`). Sem proxy, deixe `false` |
| `APP_MONITOR_REDE` | `true` libera a tela "Banco de dados" para o gestor em outros aparelhos da rede. Padrão: só neste computador |

## 8. Ver o banco de dados ao vivo (só o gestor)
Entre como **gestor** neste computador (`http://localhost:8000/login_dono.php`) e abra **Banco de dados** no menu (`dono_banco.php`).
- **Mapa das tabelas (estilo graph view do Obsidian):** cada tabela é um nó, colorido pela área (agenda, clínico, financeiro, pessoas e acesso, sistema) e com tamanho proporcional aos registros; as linhas são as chaves estrangeiras reais. Cada alteração dispara uma partícula do núcleo DENT IA até a tabela, que acende. Arraste nós, role para zoom, arraste o fundo para mover e clique num nó para abrir o **Inspetor** (registros, última alteração, inclusões/alterações/exclusões, tamanho, colunas e tabelas ligadas). Para teclado e leitor de tela, use a **Lista de tabelas**.
- **Pipeline:** o fluxo de eventos no estilo terminal (INSERT, UPDATE, DELETE), com as métricas de tamanho, registros, alterações de hoje e por minuto no topo.
- **O que cada evento mostra:** cada inclusão, alteração e exclusão em qualquer tabela, com hora, tabela, id do registro, **quais colunas** mudaram e quem fez (perfil:e-mail; "login:…" nas tentativas de login; "cadastro-publico:…" no Cadastre-se; "sistema" para scripts). Atualiza sozinho a cada 5 segundos (pausa com a aba oculta); o botão Pausar congela a tela.
- **Conteúdo da tabela:** clique numa tabela para ver os registros, 50 por página, os mais recentes primeiro.

Segurança da tela:
- Só o perfil gestor e só a partir deste computador. Outro aparelho recebe "página não encontrada", e o link nem aparece no menu. Para liberar a rede, defina `APP_MONITOR_REDE=true` (os dados de pacientes passam a ser visíveis de qualquer aparelho em que o gestor entrar).
- Só leitura: não há como alterar nem apagar nada por ela.
- Senhas, hashes e tokens aparecem como "•••• (oculto)". O registro guarda quais colunas mudaram, nunca os valores.
- O registro fica na tabela `auditoria_banco` (migração 011). O site não consegue alterar nem apagar esse registro. Só `scripts\limpar_registros.php` remove linhas, e só as de tabelas técnicas com mais de 180 dias.

## 9. Segurança e LGPD (desde 12/11/2026)

**Senhas.** Quem entra com senha de demonstração (`123456`) ou fraca é levado a "Crie uma nova senha" e não abre nenhuma outra página antes de trocar. A senha nova precisa de 10 caracteres ou mais e não pode ser uma das comuns (lista em `SENHAS_PROIBIDAS`, `includes/operacoes.php`).

**Antes de cadastrar pacientes reais**, rode `php scripts\verificar_producao.php`. Ele recusa (código de saída 1) enquanto houver conta ativa com senha de demonstração, conexão como superusuário ou `APP_DEMO=true`. Em 12/11/2026 ele ainda recusa o `dentia`: as 10 contas de demonstração precisam trocar a senha ou ser desativadas.

**Papéis do banco** (migração 012):
| Papel | Quem usa | O que pode |
|---|---|---|
| `dentia_app` | o site | ler e gravar dados; não cria, altera nem apaga tabelas; auditoria só leitura |
| `dentia_migracao` | scripts de manutenção | dono das tabelas; aplica migrações, faz backup, cria o banco temporário do teste de restauração |
| `postgres` | só você, à mão | superusuário; a senha não fica mais no `.env` |

**Consentimento** (migração 013). O aceite do Cadastre-se fica na tabela `consentimento` com versão do termo, hash do texto, origem, IP e data, e aparece em "Meu perfil" do paciente. Ao mudar o texto do termo (`TERMO_LGPD_TEXTO`), mude também `TERMO_LGPD_VERSAO`. No cadastro feito pela recepção, o aceite só é gravado quando a recepcionista marca a caixa "O paciente leu e assinou o termo"; guarde o termo assinado em papel.

**Direitos do paciente** (migração 014, `includes/privacidade.php`).
- O paciente abre **Privacidade** no portal (`cliente_privacidade.php`): vê para que serve cada dado, baixa uma cópia (cadastro, consultas e prontuário, em JSON) e pede correção ou exclusão.
- O gestor responde em **Pedidos LGPD** (`dono_privacidade.php`). Toda resposta fica gravada com data e autor; o site não apaga pedidos.
- Atender uma exclusão apaga telefone, e-mail, endereço, RG, profissão, estado civil, carteirinha e observações, e encerra o acesso ao portal. Nome, CPF, nascimento, prontuário, consultas e pagamentos ficam (Lei 13.787/2018). **Não tem como desfazer**, a não ser restaurando um backup.
- A exclusão é recusada pelo sistema se o paciente tem consulta marcada ou se a mesma pessoa é da equipe.
- A tela mostra como encarregado dos dados o nome, o e-mail e o telefone da clínica (Configurações). Falta definir quem é a pessoa encarregada e revisar o texto do termo e das finalidades com ela.

**Prontuário** (migração 013).
- Alergias, medicamentos e condições: o que é retirado ou corrigido vai para `historico_clinico_retificado`, com quem e quando, e aparece na ficha em "que já constaram".
- Evolução clínica: não pode ser alterada nem apagada. A correção é uma nova evolução.
- Leitura: cada abertura da ficha em `dentista_prontuario.php` grava uma linha em `acesso_prontuario` (quem, quando, qual paciente). O site só acrescenta nessa tabela.

**JavaScript e CSP** (P2.1). A política de segurança de conteúdo não tem mais `'unsafe-inline'` para scripts. O navegador só roda `js/*.js` e blocos `<script nonce="<?= cspNonce() ?>">` escritos pelo servidor; `onclick=`, `href="javascript:"` e qualquer script injetado numa página são bloqueados.
- Ao criar ou mudar uma tela: nada de `onclick=`/`onchange=` no HTML. Ligue o evento com `addEventListener`, num bloco `<script nonce="<?= cspNonce() ?>">` ou em `js/*.js`. O teste `tests/CspTest.php` falha se isso voltar.
- A CSP das páginas sai de `includes/bootstrap_sessao.php`. `router.php` e `.htaccess` trazem o padrão sem inline para o resto. No Apache/Docker o `.htaccess` usa `Header setifempty` para não trocar a CSP com nonce; isso **não foi testado** (o Dockerfile nunca foi construído nesta máquina).
- Conferido em 12/11/2026 no navegador, servidor de teste (porta 8001): 4 perfis e páginas públicas, sem erro de CSP no console.

**Limite de tentativas de login** (P2.13). Conta por computador+conta: 20 erros em 15 minutos travam só aquela conta naquele computador, e 100 erros do mesmo computador travam o login dali (`LOGIN_MAX_POR_CONTA`, `LOGIN_MAX_POR_ORIGEM`). Continua valendo o bloqueio da conta após 5 senhas erradas.

**Tela do banco atrás de proxy** (P2.2). Com `APP_PROXY_CONFIAVEL=true`, a tela "Banco de dados" usa o endereço que o proxy informa (`X-Forwarded-For`); proxy que não informa o endereço deixa a tela fechada. Antes, com um proxy na mesma máquina, qualquer visitante parecia local.

**Link de nova senha** (P2.6). O link que o gestor gera em Usuários tem a forma `nova_senha.php#t=...`. O trecho depois do `#` não é enviado ao servidor: não entra em registro de acessos (Apache, proxy) nem no cabeçalho `Referer`. A página entrega o token por POST, guarda só na sessão e limpa a barra de endereço. Precisa de JavaScript ligado.
- Como entregar o link: pessoalmente ou por um canal que só o titular lê (mensagem direta para o próprio número ou e-mail dele). Nunca em grupo. Vale 30 minutos e uma única vez.
- O servidor embutido (`php -S` com `router.php`) não grava as URLs pedidas, só conexões abertas e fechadas (conferido em 12/11/2026). O cuidado vale para Apache/Docker e proxies.

**Fuso horário** (P2.9, migração 015). O gestor escolhe o fuso da clínica em Configurações (lista dos fusos do Brasil). O PHP e o banco usam o mesmo: "hoje" e a hora da agenda, das faltas e do financeiro seguem esse fuso. Padrão: `America/Cuiaba`.

**Autor das alterações e pool de conexões** (P2.8). O nome gravado na auditoria vale para a conexão inteira, e cada requisição abre e fecha a sua. Não ponha um pool em modo transação (PgBouncer `transaction`) na frente do banco: o nome de um usuário poderia aparecer na alteração de outro. Com pool, use modo sessão.

**Script perigoso.** `database\RECONSTRUIR_RAILWAY.sql` aborta em qualquer banco que não termine em `_demo`, a menos que a mesma sessão rode antes `SET dentia.permitir_reset = 'SIM';`. No banco `dentia` ele aborta sempre.
