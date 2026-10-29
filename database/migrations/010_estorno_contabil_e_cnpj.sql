-- 010: estorno contábil e CNPJ alfanumérico. Não altera dados existentes. Faça backup antes (backup_banco.bat).
-- Aplicar:  psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/010_estorno_contabil_e_cnpj.sql
-- Reverter: recriar vw_movimentos_financeiros sem as duas últimas partes do UNION e voltar o CHECK para '^[0-9]{14}$'.
--
-- Estorno: o pagamento estornado continua como receita no mês em que foi pago (mês fechado não muda)
-- e a devolução entra como saída no mês do estorno. Antes, o estorno apagava a receita do mês original.
-- CNPJ: a Receita Federal passou a emitir CNPJ com letras nas 12 primeiras posições (IN RFB 2.229/2024).
BEGIN;

CREATE OR REPLACE VIEW vw_movimentos_financeiros AS
SELECT -pg.id AS id, pg.pago_em::date AS data, 'Receita'::text AS tipo,
       pr.nome::text || ' — ' || pp.nome::text AS descricao, pg.valor, pg.id_agendamento,
       'pagamento'::text AS origem, fp.nome AS forma_pagamento
FROM pagamento pg
JOIN agendamentos a ON a.id = pg.id_agendamento JOIN procedimentos pr ON pr.id = a.id_procedimento
JOIN pacientes pac ON pac.id = a.id_paciente JOIN pessoa pp ON pp.id = pac.id_pessoa
JOIN forma_pagamento fp ON fp.id = pg.id_forma_pagamento
WHERE pg.pago_em IS NOT NULL
UNION ALL
SELECT l.id, l.data, l.tipo::text, l.descricao, l.valor, NULL::integer, 'manual'::text, NULL::character varying
FROM lancamento_financeiro l
UNION ALL
-- recebimento que depois foi estornado: continua no mês em que entrou
SELECT -(1000000000 + e.id)::int, e.pago_em::date, 'Receita'::text,
       pr.nome::text || ' — ' || pp.nome::text || ' (estornado depois)', e.valor, e.id_agendamento,
       'pagamento_estornado'::text, fp.nome
FROM pagamento_estorno e
JOIN agendamentos a ON a.id = e.id_agendamento JOIN procedimentos pr ON pr.id = a.id_procedimento
JOIN pacientes pac ON pac.id = a.id_paciente JOIN pessoa pp ON pp.id = pac.id_pessoa
JOIN forma_pagamento fp ON fp.id = e.id_forma_pagamento
UNION ALL
-- a devolução: saída no mês do estorno
SELECT -(1500000000 + e.id)::int, e.estornado_em::date, 'Despesa'::text,
       'Estorno — ' || pr.nome::text || ' — ' || pp.nome::text || ': ' || e.motivo, e.valor, e.id_agendamento,
       'estorno'::text, fp.nome
FROM pagamento_estorno e
JOIN agendamentos a ON a.id = e.id_agendamento JOIN procedimentos pr ON pr.id = a.id_procedimento
JOIN pacientes pac ON pac.id = a.id_paciente JOIN pessoa pp ON pp.id = pac.id_pessoa
JOIN forma_pagamento fp ON fp.id = e.id_forma_pagamento;

ALTER TABLE clinica DROP CONSTRAINT clinica_cnpj_check;
ALTER TABLE clinica ADD CONSTRAINT clinica_cnpj_check CHECK (cnpj ~ '^[0-9A-Z]{12}[0-9]{2}$');

COMMIT;
