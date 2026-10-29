# Banca de revisão — 27/10/2026

Cinco revisores, cada um com um papel só, tentaram reprovar o código antes de subir. Os quatro primeiros trabalharam separados, só lendo o código e testando no servidor de teste (127.0.0.1:8001, banco `dentia_teste`, cópia fictícia). O banco real não foi usado para escrita.

## Primeira rodada: os quatro relatórios

| Revisor | Papel | Veredito | Destaques |
|---|---|---|---|
| 1. Hacker | Segurança | Reprova | `.env` e dump do banco baixáveis pelo `router.php` (`%5c` e `backups/`); XSS no `confirm()` que leva da conta de dentista à de dono; recepção sobrescreve a pessoa do dono |
| 2. Usuário confuso | Uso errado | Reprova | Mensagem técnica e SQLSTATE na tela; evolução duplicada no F5; faturamento em branco depois de lançamento no ano 0001 |
| 3. Programador sênior | Manutenção | Reprova | CSRF dentro do `onsubmit` quebra 2 botões; CSS inválido esconde o banner; mesma regra escrita de 3 ou 4 jeitos |
| 4. Testador | Casos e testes | Reprova | 17 falhas em 50 casos de regra e 5 em 31 casos HTTP; CSV corrompido; pagamento repetido muda a receita de mês |

## 5. Chefe dos desenvolvedores — veredito da 1ª rodada: **REPROVADO**

Dois vazamentos críticos ativos (senha do banco e dump com dados de pacientes acessíveis na rede), um caminho de XSS que leva da conta de dentista à de dono e uma falha que deixa a recepção reescrever o cadastro do dono. Não sobe.

## Lista única do que corrigir (mais grave primeiro)

| # | Grav. | Arquivo:linha | Problema | Origem |
|---|---|---|---|---|
| 1 | Crítico | `router.php:5-8` | `%5c` contorna o bloqueio: `.env`, `includes/`, `database/` e `scripts/*.php` acessíveis | Hacker |
| 2 | Crítico | `router.php:6`, `.htaccess`, `.dockerignore` | `backups/*.dump` (banco completo) baixável sem login | Hacker, Sênior |
| 3 | Crítico | `includes/db.php:235-238`, `includes/operacoes.php:324`, `recepcionista_pacientes.php:10` | `id_usuario` enviado no POST faz a recepção sobrescrever nome e CPF de outro usuário | Hacker, Testador |
| 4 | Alto | `dono_dentistas.php:154-156`, `recepcionista_dashboard.php:113-115` | Nome dentro do `confirm()` (XSS) e `csrfCampo()` dentro do atributo (botões Inativar e Cancelar sempre dão 400) | Hacker, Sênior |
| 5 | Alto | `dono_dentistas.php:14`, `dono_editar_dentista.php:10` | Dentista inativado continua entrando; as consultas futuras dele ficam ativas | Hacker, Testador |
| 6 | Alto | `includes/banco.json`; `login_*.php:69-73` | Senhas `123456` em texto puro; credenciais de demonstração exibidas na tela | Hacker, Sênior |
| 7 | Alto | `includes/operacoes.php:37-49`; `bootstrap_sessao.php` | Mensagem técnica (TypeError com caminho) e SQLSTATE na tela; `display_errors` ligado | Confuso, Hacker |
| 8 | Alto | Várias páginas (`?busca[]=`, `csrf_token[]`, `data[]`, `mes[]`…) | Parâmetro em forma de lista derruba a página (Fatal/500) | Confuso, Hacker |
| 9 | Alto | `dono_relatorios.php:33` | CSV corrompido por `Deprecated` do `fputcsv` e injeção de fórmula | Testador, Hacker |
| 10 | Alto | `includes/operacoes.php:296-298` | Receber de novo reescreve a data do pagamento (receita muda de mês) | Testador, Confuso |
| 11 | Alto | `includes/operacoes.php:236` | Paciente cancela a consulta do dia depois do horário e foge da falta | Testador, Confuso |
| 12 | Alto | `dentista_prontuario.php:16-21`, `dentista_dashboard.php:9-11` | Evolução clínica duplicada no F5 | Confuso |
| 13 | Alto | `includes/operacoes.php:316`, `includes/financeiro.php:16` | Lançamento com data 0001-01-01 deixa o faturamento em branco | Confuso |
| 14 | Alto | `cliente_dashboard.php:62`, `dentista_dashboard.php:63`, `dentista_prontuario.php:75` | `var(--x))` com parêntese sobrando: banner "Próxima consulta" invisível | Sênior |
| 15 | Alto | `includes/db.php:321-327` | `salvarJson` engole a exceção: mensagens erradas e sem diagnóstico | Sênior, Confuso |
| 16 | Alto | `includes/db.php:124` | Texto livre cria sala, especialidade e plano (fura a trava de sala) | Sênior, Confuso |
| 17 | Alto | `includes/operacoes.php:84-97`, `recepcionista_novo_agendamento.php:170` | "Horário livre" com 3 regras: o quadro mostra horário passado e N+1 | Sênior |
| 18 | Alto | `includes/functions.php:140-150` | "Última consulta" sempre "—" na recepção | Sênior |
| 19 | Alto | `cliente_consultas.php:17`, `cliente_dashboard.php:22,53`, `functions.php:175` | Status "futuro/ativo" com 4 definições diferentes | Sênior |
| 20 | Médio | `recepcionista_pacientes`, `cliente_agendamento`, `cliente_consultas`, `*_perfil`, `dono_dentistas`, `dono_configuracoes`, `dono_usuarios`, `recepcionista_dashboard` | Sem redirecionamento depois de salvar; F5 ou "voltar" reenviam (lançamento duplicado) | Confuso |
| 21 | Médio | `includes/functions.php:10-13` | CSRF vencido ou sessão expirada viram página crua 400 | Confuso, Sênior |
| 22 | Médio | `includes/functions.php:94-102` | Sem limite por IP; depois que o bloqueio vence, 1 erro bloqueia de novo | Hacker, Testador |
| 23 | Médio | `includes/db.php:168-171` | Telefone inválido descartado em silêncio | Confuso |
| 24 | Médio | `includes/operacoes.php:124,316`; cadastros | Datas absurdas aceitas (nascimento 0001/2099, agendamento 9999, antecedência 9999) | Confuso, Testador |
| 25 | Médio | `includes/operacoes.php:240-242` | Check-in e conclusão aceitos em consulta futura | Testador |
| 26 | Médio | `recepcionista_novo_agendamento.php:18-33` | Remarcar id inexistente cria um agendamento novo | Confuso |
| 27 | Médio | `includes/operacoes.php:341`, `dono_editar_dentista.php` | E-mail duplicado ignorado em silêncio; dentista sem procedimentos ou com procedimento inexistente aceito; cadastro sem especialidade dá erro 23502 | Testador, Confuso |
| 28 | Médio | `dono_dentistas.php:12-15` | Inativar id inexistente diz "sucesso" | Confuso |
| 29 | Médio | `dono_configuracoes.php:8-19` | Nome vazio, horário inválido e abertura depois do fechamento sem validação | Confuso, Sênior |
| 30 | Médio | `dono_relatorios.php:248`, `js/app.js` | Segundo "Gerar" travado; tipo inválido baixa CSV vazio | Confuso |
| 31 | Médio | `includes/operacoes.php:469-477`, `schema.sql:245` | Perfil regrava o hash da senha lido antes | Sênior |
| 32 | Médio | `includes/operacoes.php:434,489` | Trocar a senha não derruba as outras sessões | Hacker |
| 33 | Médio | `logout.php`, `includes/sidebar.php` | Logout por GET (logout forçado por CSRF) | Hacker |
| 34 | Médio | `bootstrap_sessao.php` | `session.use_strict_mode` desligado | Hacker |
| 35 | Médio | `includes/operacoes.php:237-243` | Dentista cancela consulta (cancelar é da recepção e do paciente) | Testador |
| 36 | Médio | `dentista_prontuario.php`, `includes/operacoes.php:381-402` | Dente 99, status inválido, retorno 2030-13-45 e valor gigante viram mensagem errada | Testador, Confuso |
| 37 | Médio | `dono_faturamento.php:28`, `includes/financeiro.php:28` | "A receber" calculado em PHP sobre a tabela inteira e em SQL | Sênior |
| 38 | Médio | `login_*.php` (4 cópias), mapa de perfis repetido 6× | Duplicação que já divergiu | Sênior |
| 39 | Baixo | `includes/operacoes.php:68,80` | Dentista descobre se um CPF é paciente da clínica | Hacker |
| 40 | Baixo | `includes/functions.php:84` | Tempo de resposta revela e-mail existente | Hacker |
| 41 | Baixo | `includes/db.php:345-396`, `db.php:308-314` | Código morto | Sênior |
| 42 | Baixo | `includes/operacoes.php:429,487` | Senha mínima conta bytes (2 emojis passam) e está em 3 lugares | Confuso, Sênior |
| 43 | Baixo | `recepcionista_pacientes.php:17` | CPF só com números não é encontrado | Confuso |
| 44 | Baixo | `api_horarios.php`, `js/agenda-horarios.js:35` | Procedimento inexistente responde 200 vazio; o JS esconde o erro do servidor | Confuso |
| 45 | Baixo | `includes/functions.php` (`data_pt('M')`) | Mês em inglês ("Oct") | Confuso |
| 46 | Baixo | `includes/operacoes.php` (`valorMonetario`) | "1.500" recusado | Confuso |
| 47 | Baixo | `dentista_agenda.php` | Data inválida cai em silêncio para hoje | Confuso |
| 48 | Baixo | `dono_dentistas.php:50` | Formulário volta oculto e vazio depois de erro | Confuso |

O resultado das correções e o veredito da segunda rodada estão no fim deste arquivo.

## Correções da 1ª rodada (resumo)
- **Núcleo (`includes/`):** mensagens sem detalhe técnico; erros fora da tela; recusa de parâmetros em forma de lista e de texto inválido; envio único de formulário (F5, "voltar" e duplo clique não repetem nada); mensagens entre páginas; limite de tentativas por IP (tabela `tentativa_acesso`, migração 008); versão da senha na sessão; mapa único de perfis; validação de CPF, datas, valores, telefone, dentes FDI e senha; referências (sala, plano, especialidade) só escolhidas da lista; `salvarJson` lança erro em vez de devolver `false`; código morto removido.
- **Segurança:** `router.php` e `.htaccess` com lista do que é permitido; `backups/` fora da imagem Docker; `includes/banco.json` apagado (cópia em `DENTIACLAUDE_backup_banca`); CSP, SRI no CDN e logout só por POST; `vw_usuarios` sem o hash da senha.
- **Regras:** pagamento não é reescrito; paciente não cancela depois do horário; check-in só a partir do dia; dentista inativado perde o acesso e não pode ter consultas abertas; evolução não conclui consulta antiga sem check-in.
- **Páginas:** XSS e CSRF dos botões de confirmação; padrão POST → redirecionamento → GET; CSV sem lixo e sem injeção de fórmula; busca por CPF só com números; banner invisível; login único (`includes/login_form.php`).
- **Novo:** "Cadastre-se" do paciente (`cadastro_paciente.php`).

## 2ª rodada — veredito: **REPROVADO** (achados novos, todos corrigidos depois)

| Revisor | Bloqueante | Correção |
|---|---|---|
| Hacker | Crítico: `//.env/js/app.js` e `//backups%2f...dump/js/app.js` ainda baixavam o `.env` e o dump no `php -S` (o servidor embutido servia um caminho diferente do validado) | `router.php` valida o caminho cru e entrega ele mesmo css/js; nada é repassado ao servidor embutido |
| Hacker, sênior, testador | Limite por IP atrás de proxy (Railway) bloquearia todos ou seria contornável | `APP_PROXY_CONFIAVEL=true` usa o último IP do `X-Forwarded-For` (o do proxy); sem a variável, o cabeçalho é ignorado |
| Testador | Pagamento adiantado travava a consulta (sem estorno) | `estornarPagamento()` + botão "Estornar" com motivo; o pagamento original fica em `pagamento_estorno` (migração 009) |
| Usuário confuso | Recepção não cadastrava paciente novo (`id=0` virava UPDATE) | id ausente, vazio ou 0 = registro novo |
| Usuário confuso | Paciente não tinha botão para cancelar consultas futuras | "Cancelar" em cada consulta futura de "Minhas Consultas" |
| Sênior | Testes PHPUnit quebrados pelas mudanças de API | `referenciaPorNome(..., true)`, exceção esperada e datas relativas |
| Sênior | Consultas sumiam da tela do paciente; "próxima consulta" ignorava a hora | `consultaFutura()` / `SQL_CONSULTA_FUTURA`: uma regra só, com data e hora |
| Sênior, testador | Envio único dizia "já foi enviado" quando o código só tinha expirado; era opcional | Mensagem neutra, limite maior, campo obrigatório em todo POST |

Também corrigidos: erro de digitação não gasta a cota do Cadastre-se e o limite é conferido depois da validação; campos ausentes no POST do Cadastre-se; dentista "Em atendimento" não pode ser inativado; horários livres respeitam o limite diário; fechar ou encurtar um dia com consultas marcadas é recusado; UF, CNPJ, cidade e bairro validados; dentista com no mínimo 18 anos; id gigante na URL vira "não encontrado"; remarcar consulta cancelada mostra aviso logo; check-in só aparece a partir do dia; "Aguardando" pode ser cancelada; "Receber" pede confirmação; falta na lista de pendentes diz onde desfazer; prontuário e faturamento mantêm o que foi digitado depois de erro; busca sem diferenciar acento; mês vazio no faturamento; e-mail corrigido pela recepção também muda o login do portal; mensagens em URL trocadas pelo padrão único de mensagens; SQL da agenda em uma função (`agendaRecepcao`); POST das 4 páginas de perfil em uma função (`tratarFormularioPerfil`).

**Ficaram como estão, de propósito:**
- Conta bloqueada mostra a mesma mensagem de senha errada (avisar o bloqueio revelaria que o e-mail existe).
- Dois envios simultâneos do login sem JavaScript podem invalidar a sessão do segundo (o `app.js` bloqueia o duplo clique).
- `salvarJson()` continua uma função grande para 4 fontes; dividir não muda comportamento e fica como melhoria.
- 374 estilos inline no HTML e documentos antigos em `docs/` sobre o Railway: só aparência e organização.
- HTTP sem criptografia na rede local: precisa de um proxy com certificado (documentado em `docs/EXECUCAO_LOCAL.md`).

## 3ª rodada — veredito: **REPROVADO** (achados novos, todos corrigidos depois)

O que a 3ª rodada confirmou: nenhum desvio do `router.php` (mais de 400 caminhos testados), proxy/IP, permissão do estorno, XSS, `id_usuario`, as 22 falhas da 1ª rodada e 9 das 10 da 2ª.

| Achado | Gravidade | Correção |
|---|---|---|
| Estorno mudava a receita de um mês já fechado; bloquear o estorno travava a consulta paga | Alto | Migração 010: o recebimento estornado continua como receita no mês em que entrou, e a devolução sai no mês do estorno. Estorno liberado em qualquer mês; relatórios por dentista e por plano seguem a mesma regra; o gestor vê "Estornos do mês" no Faturamento |
| Despesa virava receita depois de um erro no lançamento (tipo e categoria não voltavam) | Alto | Tipo e categoria voltam como escolhidos |
| Salvar a ficha da paciente trocava o login do portal (contato e login diferentes no seed) | Alto (introduzido na 2ª rodada) | O login só acompanha quando o e-mail da ficha é alterado de verdade; teste automático cobre |
| PHPUnit ainda quebrado (exceção errada, plano/sala de teste, teste que validava a falha antiga) | Médio | Testes ajustados; o teste antigo agora prova o contrário (não reaproveita pessoa de outro usuário); `bootstrap` aceita `_test` e `_teste` |
| Horário de funcionamento travado por consultas antigas sem desfecho; não considerava a duração | Médio | Confere só os dias alterados, só consultas que ainda não começaram, pela duração; formato e ordem antes |
| "Aguardando" de dia passado aparecia como próxima consulta | Médio | Só conta no próprio dia |
| Busca do dentista com acento e sem CPF; "%" e "_" como curinga no dono | Médio/Baixo | `semAcento` + CPF; curingas escapados |
| Plano de saúde em texto livre na recepção | Médio | Lista de planos; comparação sem diferenciar maiúsculas |
| Agendamento perdia o que foi escolhido depois de um erro | Médio | Procedimento, dentista, data, valor e observações voltam |
| Id gigante no filtro da agenda dava 500; `id="1e1"` editava o paciente 10 | Baixo | `idParametro` em todos os pontos |
| `envio=livre` aceito em qualquer formulário | Baixo | Só em "Sair" e no relatório CSV |
| Cliente descobria ids de consulta de outros | Baixo | Mesma resposta "Consulta não encontrada" |
| Cancelar aparecia para consulta paga; "Confirmar pagamento" do dono sem confirmação | Baixo | Botão escondido; confirmação adicionada |
| Mensagens: clínica fechada, conflito com o próprio paciente, "peça à recepção" | Baixo | Mensagens próprias; "peça à clínica" |
| CNPJ alfanumérico (IN RFB 2.229/2024) recusado | Médio | Validador e `CHECK` aceitam letras nas 12 primeiras posições |

Testes depois das correções: `testar_fluxos_railway.php` 34 OK, `testar_melhorias.php` 41 OK, `testar_seguranca.php` 74 OK (75 no banco de teste), `php -l` em 64 arquivos, `node --check` nos 3 JS, crawl dos 4 perfis em 44 páginas nos dois servidores, sem falhas.

## 4ª rodada — veredito: **APROVADO PRA SUBIR**

| Revisor | Veredito | Resumo |
|---|---|---|
| Hacker + testador | Aprovaria | As 8 correções da 3ª rodada foram confirmadas por HTTP e no banco de teste; os 3 scripts passaram; nada Crítico nem Alto |
| Programador sênior + usuário confuso | Aprovaria | As correções estão no código e funcionam na tela; instalação nova (`schema.sql` + `seed.sql`) conferida; só achados baixos |

Achados baixos da 4ª rodada, corrigidos em seguida: os últimos ids lidos com `(int)` passaram para `idParametro` (edição de paciente e `criarAgendamento`); a receita por dentista e por plano desconta o estorno no mês da devolução, como o total; a busca por "0" deixou de listar todos; o horário escolhido volta depois de um erro no agendamento.

### Chefe dos desenvolvedores — veredito final: **APROVADO PRA SUBIR**
Não sobrou nada Crítico, Alto ou Médio entre os achados das quatro rodadas. O que ficou está listado acima, em "Ficaram como estão, de propósito", ou é cosmético. Verificação final: `testar_fluxos_railway.php` 34 OK, `testar_melhorias.php` 41 OK, `testar_seguranca.php` 74 OK (75 no banco de teste), `php -l` em 64 arquivos, `node --check` nos 3 JS, crawl dos 4 perfis em 44 páginas nos dois servidores sem falhas, e instalação do zero conferida num banco temporário.

**Condições para colocar no ar (configuração, não código):**
1. `APP_DEMO=false` e troca das senhas `123456` das contas de demonstração (Usuários → gerar link).
2. Na internet: HTTPS na frente do PHP. Atrás do proxy do Railway, `APP_PROXY_CONFIAVEL=true`, e conferir uma vez, no log, se o último IP do `X-Forwarded-For` é o do visitante.
3. Aplicar as migrações 006 a 010, em ordem, com backup antes.

**Não foi possível rodar:** a suíte PHPUnit (`tests/`), porque o Composer e o `vendor/` não existem nesta máquina. Os testes foram corrigidos e conferidos só na leitura do código.
