-- Self-check for validar_sobreposicao_agenda; everything rolls back.
BEGIN;
DO $$
DECLARE dp record; s1 int; s2 int; longo int; ok boolean;
BEGIN
  SELECT id INTO longo FROM procedimentos ORDER BY duracao_min DESC LIMIT 1;
  SELECT d.id_dentista INTO dp FROM dentista_procedimentos d LIMIT 1;
  INSERT INTO dentista_procedimentos VALUES (dp.id_dentista, longo) ON CONFLICT DO NOTHING;
  SELECT min(id), max(id) INTO s1, s2 FROM sala;
  -- 23:00 de D com o procedimento mais longo (150 min) atravessa a meia-noite.
  INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor) VALUES (1,dp.id_dentista,longo,s1,'2031-01-06','23:00',1);
  BEGIN
    INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor) VALUES (2,dp.id_dentista,longo,s2,'2031-01-07','00:30',1);
    RAISE EXCEPTION 'FALHOU: sobreposição pela meia-noite aceita';
  EXCEPTION WHEN unique_violation THEN RAISE NOTICE 'ok: sobreposição pela meia-noite rejeitada';
  END;
  BEGIN
    INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor) VALUES (1,dp.id_dentista,longo,s2,'2031-01-06','22:00',1);
    RAISE EXCEPTION 'FALHOU: mesmo dia aceita';
  EXCEPTION WHEN unique_violation THEN RAISE NOTICE 'ok: sobreposição no mesmo dia rejeitada';
  END;
  -- Começa exatamente quando o anterior termina (01:30): deve passar.
  INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor) VALUES (2,dp.id_dentista,longo,s2,'2031-01-07','01:30',1);
  RAISE NOTICE 'ok: horário adjacente aceito';
END $$;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF)
INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor)
SELECT 3, id_dentista, id_procedimento, (SELECT min(id) FROM sala), date '2030-01-07', '09:00', 10 FROM dentista_procedimentos LIMIT 1;
ROLLBACK;
