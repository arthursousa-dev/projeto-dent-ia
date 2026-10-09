# Operação e indicadores

O seed usa seis meses até a data base, com consultas compatíveis com dentista/procedimento, disponibilidade e duração. Há agenda atual e futura, pagamentos, despesas, receitas avulsas e evolução por atendimento concluído. O legado permanece nos JSONs originais.

| Indicador | Cálculo |
|---|---|
| Receita/faturamento | Pagamentos com `pago_em` + lançamentos Receita, na data do recebimento |
| Despesas | Lançamentos Despesa, na data do lançamento |
| Lucro | Receita − despesas |
| Ticket médio | Valor de consultas concluídas ÷ consultas concluídas, no mês da consulta |
| A receber | Valor de consultas não canceladas do mês sem pagamento confirmado |
| Crescimento | Variação da receita frente ao mês anterior; zero quando não há base anterior |
| Por dentista | Produção concluída e pagamentos vinculados ao dentista; receitas avulsas não são atribuídas |

Não há dupla contagem de recebimentos: os pagamentos são lidos diretamente pela view de movimentos e não copiados como receitas avulsas. O modelo representa pagamento integral por consulta; não inclui parcelamento, estorno nem conciliação bancária.

## drawDB

Abra https://drawdb.app/editor, escolha PostgreSQL e importe `database/dentia-drawdb.json` em File → Import from → JSON. A exportação é nativa e foi validada contra o JSON Schema oficial do drawDB.

As 31 tabelas e todos os vínculos são extraídos do catálogo PostgreSQL. FKs compostas aparecem com uma linha por coluna, identificadas pelo mesmo nome de constraint. Índices parciais, CHECKs, triggers, ENUMs e definição completa das FKs ficam nos comentários e em `postgresqlCatalog`. O editor não representa todas as restrições PostgreSQL; `schema.sql` é a definição executável oficial, não o SQL regenerado pelo editor.

## Migração da versão anterior

Com as mesmas variáveis DB configuradas, aplique em ordem:

```bash
psql -v ON_ERROR_STOP=1 -f database/migrations/002_operacao_financeira.sql
psql -v ON_ERROR_STOP=1 -f database/migrations/003_funcionamento_clinica.sql
psql -v ON_ERROR_STOP=1 -f database/migrations/004_atendimentos_concluidos.sql
```

Cada arquivo usa uma transação. Não aplique o schema completo nem o seed sobre uma base com dados. Para demonstração atualizada, crie outro banco vazio e importe schema/seed.

## Conferir faturamento zerado

Escolha o mês na tela. Os indicadores, os seis meses do gráfico, a lista de lançamentos e as consultas a receber usam o mesmo período. Receita por plano soma pagamentos confirmados de pacientes com aquele plano; receitas avulsas aparecem separadamente. A classificação usa o plano atual do cadastro.

O gráfico usa as classes existentes no CSS. Lançamentos têm data explícita, categoria compatível com Receita/Despesa e confirmação seguida de redirecionamento, para que atualizar a página não repita a gravação.

Para consultar a base usada pela aplicação:

```bash
php scripts/verificar_faturamento.php 2026-10
```

Um banco antigo pode ter pagamentos apenas em outros meses. A migração cria a estrutura, preservando os dados, mas não adiciona movimento fictício. Os valores de demonstração são fornecidos por `database/seed.sql`, para carga junto do schema em um banco vazio, conforme README. Não execute o seed em uma base com dados existentes.
