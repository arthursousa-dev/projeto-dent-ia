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

A aplicação foi escrita originalmente contra arquivos JSON (`lerJson()`/`salvarJson()`). Em vez de reescrever as ~30 páginas de uma vez, a migração pro PostgreSQL manteve essa mesma interface: `includes/db.php` virou um adaptador que por baixo faz tudo em SQL, e por cima devolve exatamente os arrays que o resto do código já esperava. Isso reduziu drasticamente o risco de quebrar alguma tela durante a troca de banco.

```
database/
├── schema.sql          # DDL completo (11 tabelas/tipos)
├── seed.sql             # dados de demonstração, migrados do JSON legado
└── dados_legado/        # JSONs originais, mantidos só como referência histórica
```

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
psql dentia < database/schema.sql
psql dentia < database/seed.sql

cp .env.example .env   # ajuste as credenciais do seu Postgres local

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

> Dados fictícios, gerados só para demonstração do sistema. Os agendamentos de exemplo têm datas fixas (mar/abr de 2026) herdadas dos dados originais — por isso os contadores de "atendimentos no mês" no dashboard podem aparecer zerados fora desse período; é o comportamento esperado pra dados de demonstração estáticos.

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
│   └── gerar_seed_do_json_legado.py   # ferramenta usada na migração (documentação)
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
psql dentia_test < database/schema.sql
DB_NAME=dentia_test vendor/bin/phpunit
```

---

Feito por Arthur Sousa — [LinkedIn](https://www.linkedin.com/in/arthur-sousa-ads/) · [GitHub](https://github.com/arthursousa-dev)
