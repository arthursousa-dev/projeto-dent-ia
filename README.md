# DENT IA

Sistema de gestão para clínicas odontológicas, com quatro perfis de acesso (proprietário, dentista, recepcionista e paciente), agenda, prontuário eletrônico com odontograma e controle financeiro.

Projeto de portfólio construído em PHP puro, sem framework, com foco em fundamentos: autenticação segura, controle de sessão por perfil e organização de código.

## Funcionalidades

- **Dono** — dashboard geral, relatórios, faturamento, gestão de dentistas e pacientes
- **Dentista** — agenda própria, prontuário do paciente com odontograma (FDI) em SVG
- **Recepcionista** — agenda da clínica, cadastro de pacientes, novos agendamentos
- **Paciente** — consultas, histórico e agendamento próprio

## Tecnologias

- PHP 8 (orientado a objetos onde faz sentido, funções puras no restante)
- Armazenamento em JSON (arquivos em `dados/`, com `flock` para escrita segura) — **em migração para PostgreSQL**
- HTML/CSS sem framework de front-end
- Bootstrap Icons

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt) e verificadas com `password_verify()` — nunca em texto puro
- Proteção CSRF em todos os formulários de login (token por sessão, validado com `hash_equals()`)
- Saída sempre escapada com `htmlspecialchars()` antes de ir pro HTML

## Como rodar localmente

```bash
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

> Dados fictícios, gerados só para demonstração do sistema.

## Estrutura

```
dentia/
├── includes/
│   ├── db.php          # leitura/escrita segura dos arquivos JSON
│   ├── functions.php    # autenticação, sessão, dados normalizados
│   ├── seguranca.php     # hash de senha e CSRF
│   ├── head.php / sidebar.php / a11y_bar.php
├── dados/                # base de dados em JSON (substituindo por PostgreSQL)
├── scripts/
│   └── hash_senhas.php   # migração one-off de senha texto puro -> bcrypt
├── login_dono.php / login_dentista.php / login_paciente.php / login_recepcionista.php
├── dono_*.php / dentista_*.php / recepcionista_*.php / cliente_*.php
└── index.php
```

## Roadmap

- [ ] Migrar armazenamento de JSON para PostgreSQL
- [ ] Testes automatizados para as funções de autenticação
- [ ] Rate limiting no login (proteção contra força bruta)

---

Feito por Arthur Sousa — [LinkedIn](https://www.linkedin.com/in/arthur-sousa-ads/) · [GitHub](https://github.com/arthursousa-dev)
