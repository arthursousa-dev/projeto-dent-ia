-- Reverte 005: volta o trigger e a vw_clinica ao estado de schema.sql anterior. Não apaga dados.
BEGIN;
CREATE OR REPLACE FUNCTION validar_sobreposicao_agenda() RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE duracao INT; chave BIGINT;
BEGIN
    IF NEW.status = 'Cancelado' THEN RETURN NEW; END IF;
    FOR chave IN SELECT k FROM unnest(ARRAY[1000000000::bigint+NEW.id_dentista,
        2000000000::bigint+NEW.id_paciente,3000000000::bigint+NEW.id_sala]) k ORDER BY k LOOP
        PERFORM pg_advisory_xact_lock(chave);
    END LOOP;
    SELECT duracao_min INTO duracao FROM procedimentos WHERE id=NEW.id_procedimento;
    IF EXISTS (
        SELECT 1 FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento
        WHERE a.id <> NEW.id AND a.status <> 'Cancelado'
          AND (a.id_dentista=NEW.id_dentista OR a.id_paciente=NEW.id_paciente OR a.id_sala=NEW.id_sala)
          AND tsrange(a.data+a.hora,a.data+a.hora+make_interval(mins=>p.duracao_min),'[)') &&
              tsrange(NEW.data+NEW.hora,NEW.data+NEW.hora+make_interval(mins=>duracao),'[)')
    ) THEN RAISE EXCEPTION 'Horário sobreposto para dentista, paciente ou sala' USING ERRCODE='23505'; END IF;
    RETURN NEW;
END $$;
-- Recalcula a coluna antiga a partir da tabela, já que a aplicação parou de gravá-la.
UPDATE clinica c SET horario = v.horario FROM vw_clinica v WHERE v.id = c.id AND v.horario <> '';
CREATE OR REPLACE VIEW vw_clinica AS
SELECT c.id, c.nome, c.razao_social,
       regexp_replace(c.cnpj, '^([0-9]{2})([0-9]{3})([0-9]{3})([0-9]{4})([0-9]{2})$', '\1.\2.\3/\4-\5') AS cnpj,
       (SELECT formatar_telefone(t.numero) FROM telefone t WHERE t.id_clinica=c.id AND t.finalidade='principal') AS telefone,
       c.email, e.endereco, e.bairro, e.cidade, e.estado, e.cep, c.horario,
       c.logo_texto, c.cor_primaria, c.cor_destaque, c.limite_agendamentos_dia, c.antecedencia_min_dias,
       c.atualizado_em::timestamp AS atualizado_em
FROM clinica c LEFT JOIN vw_enderecos e ON e.id=c.id_endereco;
COMMENT ON COLUMN clinica.horario IS NULL;
COMMIT;
