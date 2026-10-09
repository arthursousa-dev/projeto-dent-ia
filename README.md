# DENT IA

Sistema de gestão para clínicas odontológicas, com quatro perfis de acesso (proprietário, dentista, recepcionista e paciente), agenda, prontuário eletrônico com odontograma e controle financeiro.

Projeto de portfólio construído em PHP puro, sem framework, com foco em fundamentos: modelagem de banco de dados, autenticação segura e organização de código.

## Funcionalidades

- **Dono** — dashboard com indicadores reais, relatórios, faturamento, gestão de dentistas e pacientes
- **Dentista** — agenda própria, prontuário do paciente com odontograma (FDI) em SVG
- **Recepcionista** — agenda da clínica, cadastro de pacientes, novos agendamentos
- **Paciente** — consultas, histórico e agendamento próprio

## Tecnologias

- PHP 8 (orientado a objetos onde faz sentido, funções puras no restante)
- **PostgreSQL** via PDO, com prepared statements em toda consulta
- HTML/CSS sem framework de front-end
- Bootstrap Icons

## Arquitetura de dados

O banco PostgreSQL usa 25 tabelas normalizadas e 7 views de compatibilidade. `pessoa` centraliza os dados civis; telefone, endereço, disponibilidade, histórico clínico e pagamento têm tabelas próprias. Especialidades, categorias, planos, salas e formas de pagamento são referências. `faturamento_mensal` representa um fechamento consolidado com chave `(ano, mes_num)`.

`includes/db.php` preserva `lerJson()`, `lerJsonObjeto()` e `salvarJson()`: lê pelas views e grava nas tabelas base em transações. Cargo e pagamento confirmado são derivados. Os e-mails de login e de contato podem ser diferentes; os perfis usam o vínculo `id_usuario`.

- `database/schema.sql`: estrutura, domínios, funções, índices, triggers, views e referências iniciais.
- `database/seed.sql`: demonstração reproduzível a partir dos JSONs legados.
- `database/dados_legado/`: originais preservados.
- [Modelo de dados](docs/modelo-de-dados.md): diagrama ER e análise de 1FN, 2FN e 3FN.
- [Validação executada](docs/validacao.md): compatibilidade, testes e contagens.

Para regenerar o seed: `python3 scripts/gerar_seed_do_json_legado.py`. Inicialize um banco vazio com schema e seed; estes arquivos não fazem migração destrutiva de bancos existentes.

## Segurança

- Senhas com `password_hash()`/`password_verify()` (bcrypt) — inclusive na troca de senha, onde uma auditoria encontrou e corrigiu uma comparação em texto puro que tinha sobrevivido à correção inicial do login
- **Bloqueio de conta por força bruta**: 5 tentativas de login incorretas seguidas bloqueiam a conta por 15 minutos
- Proteção CSRF em todos os formulários que alteram dado, token validado com `hash_equals()`
- Cookies de sessão com `httponly`, `samesite=Lax` e `secure` (quando em HTTPS)
- `.htaccess` bloqueando acesso direto a `.env`/`.sql`/`.log` e às pastas `app/`, `database/`, `includes/`
- Cabeçalhos HTTP: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- Saída sempre escapada com `htmlspecialchars()` antes de ir pro HTML

## Como rodar localmente

```bash
createdb dentia
psql -v ON_ERROR_STOP=1 dentia < database/schema.sql
psql -v ON_ERROR_STOP=1 dentia < database/seed.sql

cp .env.example .env
set -a
. ./.env
set +a

php -S localhost:8000
```

Acesse `http://localhost:8000`.

### Credenciais de demonstração

| Perfil | E-mail | Senha |
|---|---|---|
| Dono | dono@dentai.com | 123456 |
| Dentista | dentista@dentai.com | 123456 |
| Recepcionista | recepcao@dentai.com | 123456 |
| Paciente | cliente@gmail.com | 123456 |

> O seed tem 1 dono, 4 dentistas legados, 2 recepcionistas, 10 pacientes e 20 agendamentos. Os novos logins (`recepcao2@dentai.com`, `paciente9@dentia.local` e `paciente10@dentia.local`) também usam `123456`. Dados fictícios, gerados só para demonstração do sistema. Os agendamentos de exemplo têm datas fixas (mar/abr/set de 2026) herdadas dos dados originais — por isso os contadores de "atendimentos no mês" no dashboard podem aparecer zerados fora desse período; é o comportamento esperado pra dados de demonstração estáticos.

## Estrutura

```
dentia/
├── app/Config/Database.php   # conexão PDO em Singleton (PostgreSQL)
├── database/                  # schema, seed e dados legados (ver acima)
├── includes/
│   ├── db.php                  # adaptador de persistência (JSON → PostgreSQL)
│   ├── bootstrap_sessao.php     # cookie de sessão hardenizado + session_start()
│   ├── functions.php             # autenticação (com bloqueio por tentativas), formatação
│   ├── seguranca.php              # CSRF
│   └── head.php / sidebar.php / a11y_bar.php
├── scripts/
│   └── gerar_seed_do_json_legado.py   # gerador do seed normalizado
├── login_dono.php / login_dentista.php / login_paciente.php / login_recepcionista.php
├── dono_*.php / dentista_*.php / recepcionista_*.php / cliente_*.php
└── index.php
```

## Roadmap

- [x] Migrar as páginas mais acessadas pra query direta (`dono_pacientes.php`)
- [x] Testes automatizados para autenticação e para o adaptador de persistência
- [x] Odontograma extraído para um componente reutilizável (`includes/odontograma_svg.php`)
- [ ] Migrar as demais páginas de listagem (dentistas, agendamentos) pro mesmo padrão de query direta
- [ ] Paginação nas listagens grandes

## Testes

```bash
composer install
createdb dentia_test
psql -v ON_ERROR_STOP=1 dentia_test < database/schema.sql
psql -v ON_ERROR_STOP=1 dentia_test < database/seed.sql
DB_NAME=dentia_test vendor/bin/phpunit
```

---

Feito por Arthur Sousa — [LinkedIn](https://www.linkedin.com/in/arthur-sousa-ads/) · [GitHub](https://github.com/arthursousa-dev)
