-- 005: desempenho do trigger de agenda + horário da clínica derivado (3FN). Não apaga dados.
-- Aplicar:  psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/005_desempenho_e_horario_derivado.sql
-- Reverter: psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/005_reverter.sql
BEGIN;

-- Antes: o EXISTS comparava com TODOS os agendamentos (seq scan a cada INSERT/UPDATE).
-- Agora só olha os dias que podem sobrepor, o que permite usar os índices agenda_dentista/paciente/sala.
CREATE OR REPLACE FUNCTION validar_sobreposicao_agenda() RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE duracao INT; duracao_max INT; chave BIGINT;
BEGIN
    IF NEW.status = 'Cancelado' THEN RETURN NEW; END IF;
    FOR chave IN SELECT k FROM unnest(ARRAY[1000000000::bigint+NEW.id_dentista,
        2000000000::bigint+NEW.id_paciente,3000000000::bigint+NEW.id_sala]) k ORDER BY k LOOP
        PERFORM pg_advisory_xact_lock(chave);
    END LOOP;
    SELECT duracao_min INTO duracao FROM procedimentos WHERE id=NEW.id_procedimento;
    SELECT max(duracao_min) INTO duracao_max FROM procedimentos;
    IF EXISTS (
        SELECT 1 FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento
        WHERE a.id <> NEW.id AND a.status <> 'Cancelado'
          AND (a.id_dentista=NEW.id_dentista OR a.id_paciente=NEW.id_paciente OR a.id_sala=NEW.id_sala)
          -- Um agendamento que começa antes desta janela termina antes de NEW começar.
          AND a.data BETWEEN NEW.data - (1 + (duracao_max + 1439) / 1440)
                         AND (NEW.data + NEW.hora + make_interval(mins=>duracao))::date
          AND tsrange(a.data+a.hora,a.data+a.hora+make_interval(mins=>p.duracao_min),'[)') &&
              tsrange(NEW.data+NEW.hora,NEW.data+NEW.hora+make_interval(mins=>duracao),'[)')
    ) THEN RAISE EXCEPTION 'Horário sobreposto para dentista, paciente ou sala' USING ERRCODE='23505'; END IF;
    RETURN NEW;
END $$;

-- clinica.horario era um texto calculado a partir de funcionamento_clinica (dependência transitiva/redundante).
-- A view passa a derivar o texto; a coluna fica só para reversão e pode ser removida com autorização.
CREATE OR REPLACE VIEW vw_clinica AS
SELECT c.id, c.nome, c.razao_social,
       regexp_replace(c.cnpj, '^([0-9]{2})([0-9]{3})([0-9]{3})([0-9]{4})([0-9]{2})$', '\1.\2.\3/\4-\5') AS cnpj,
       (SELECT formatar_telefone(t.numero) FROM telefone t WHERE t.id_clinica=c.id AND t.finalidade='principal') AS telefone,
       c.email, e.endereco, e.bairro, e.cidade, e.estado, e.cep,
       COALESCE((SELECT string_agg(ds.nome || ': ' || to_char(f.abertura,'HH24:MI') || ' às ' || to_char(f.fechamento,'HH24:MI'),
                    ' | ' ORDER BY ds.id)
                 FROM funcionamento_clinica f JOIN dia_semana ds ON ds.id=f.id_dia_semana WHERE f.id_clinica=c.id),
                '')::varchar(200) AS horario,
       c.logo_texto, c.cor_primaria, c.cor_destaque, c.limite_agendamentos_dia, c.antecedencia_min_dias,
       c.atualizado_em::timestamp AS atualizado_em
FROM clinica c LEFT JOIN vw_enderecos e ON e.id=c.id_endereco;
COMMENT ON COLUMN clinica.horario IS 'OBSOLETA desde 005: o texto é derivado de funcionamento_clinica em vw_clinica. Não usar.';

COMMIT;
