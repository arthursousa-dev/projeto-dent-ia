# Validação executada

Executada em 09/10/2026 com PostgreSQL 16.15, PHP 8.3.6 e PHPUnit 9.6.17 disponível no ambiente. O projeto mantém a dependência declarada de PHPUnit 10.5; os testes também funcionaram no executável 9.6, usando bootstrap explícito e sem carregar o XML de configuração 10.5.

| Verificação | Resultado |
|---|---|
| Schema antigo + seed antigo em `dentia_old_test` | Carregados sem erro |
| Schema normalizado + seed em `dentia_test` | Carregados sem erro, com `ON_ERROR_STOP=1` |
| Leitura por `lerJson` e `lerJsonObjeto` | Comparação por ID de todas as chaves antigas: zero diferenças inesperadas |
| Diferenças autorizadas | CPF corrigido, cargo derivado e registros novos; registros legados preservados |
| Conexão e fontes legadas | `Database.php`, `.env.example` e oito JSONs idênticos byte a byte aos anexados |
| Bcrypt e autenticação | Dez hashes aceitam `123456`; todos os usuários ativos autenticam no próprio perfil, incluindo os quatro logins do README |
| Escrita pelo adaptador | Usuário/pessoa, paciente, dentista e clínica confirmados em SQL e nas views; rollback integral em erro |
| Domínios | CPF inválido, DDD/número inválidos, XOR, principal único por pessoa e clínica, FDI e FK dentista/procedimento testados |
| Agenda e pagamento | Conflitos isolados de dentista, paciente e sala rejeitados; cancelamento libera horário; `pago_em` deriva `pago` |
| Testes automatizados | **24 testes, 86 assertions, todos aprovados** |
| Sintaxe PHP | **44 arquivos aprovados por `php -l`** |
| Páginas HTTP | **23 acessos autenticados** com resposta 200 e sem Warning/Fatal/Notice, incluindo busca de paciente e prontuário selecionado |
| Formulários HTTP | **5 POSTs**: dono, recepcionista, dentista, paciente e clínica; SQL/adapter confirmam alterações sem duplicar pacientes ou dentistas |
| Gerador | Compilação Python válida e geração determinística; consultas novas em dias úteis |
| Git | Branch `feat/normalizacao-banco`; histórico original preservado; commits semânticos separados |

Os testes de rejeição emitem mensagens esperadas em `error_log` para telefone inválido e rollback por CPF inválido. Os runners de comparação e HTTP foram temporários e não fazem parte do projeto entregue.

## Contagem do seed

| Entidade | Registros |
|---|---:|
| Pessoa | 17 |
| Usuários | 10 |
| Dono | 1 |
| Dentistas | 4 |
| Recepcionistas | 2 |
| Logins de cliente | 3 |
| Pacientes | 10 |
| Telefones | 26 |
| Endereços | 11 |
| Histórico clínico | 31 |
| Agendamentos | 20 |
| Pagamentos confirmados | 13 |
| Prontuários | 10 |
| Dentes no odontograma | 240 |
| Procedimentos no odontograma | 18 |

Os quatro dentistas já existiam no anexo e foram preservados; reduzir para dois impediria a comparação integral dos registros antigos. Pacientes sem login no legado continuam sem credencial; os dois novos pacientes têm login próprio.

## Reproduzir os testes

Em um PostgreSQL local acessível pelas variáveis já existentes:

```bash
createdb dentia_test
psql -v ON_ERROR_STOP=1 dentia_test -f database/schema.sql -f database/seed.sql
composer install
DB_NAME=dentia_test vendor/bin/phpunit
```

Alternativa usada na validação com o PHPUnit instalado no sistema:

```bash
DB_NAME=dentia_test phpunit --no-configuration --bootstrap tests/bootstrap.php tests
```

A proteção no bootstrap exige nome de banco terminado em `_test`. O schema/seed destinam-se a um banco vazio; a aplicação não importa esses arquivos automaticamente.
