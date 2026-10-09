BEGIN;
CREATE OR REPLACE VIEW vw_dentistas AS
SELECT d.id, u.id AS id_usuario, p.nome, p.email,
       (SELECT formatar_telefone(t.numero) FROM telefone t WHERE t.id_pessoa=p.id AND t.finalidade='principal') AS telefone,
       formatar_cpf(p.cpf) AS cpf, to_char(p.nascimento,'YYYY-MM-DD') AS nascimento,
       'CRO-' || d.cro_uf || ' ' || d.cro_numero AS cro, esp.nome AS especialidade,
       d.titulacao, d.faculdade, d.ano_formatura, to_char(d.admissao,'YYYY-MM-DD') AS admissao,
       d.cor, s.nome AS sala, initcap(d.status::text) AS status,
       ARRAY(SELECT DISTINCT to_char(hora,'HH24:MI') FROM disponibilidade_dentista WHERE id_dentista=d.id ORDER BY 1) AS horarios,
       ARRAY(SELECT ds.nome FROM dia_semana ds WHERE EXISTS (SELECT 1 FROM disponibilidade_dentista dd WHERE dd.id_dentista=d.id AND dd.id_dia_semana=ds.id) ORDER BY ds.id) AS dias_atendimento,
       ARRAY(SELECT pr.nome FROM procedimentos pr JOIN dentista_procedimentos dp ON dp.id_procedimento=pr.id WHERE dp.id_dentista=d.id ORDER BY pr.nome) AS procedimentos,
       (SELECT count(*) FROM agendamentos a WHERE a.id_dentista=d.id AND a.status='Concluída' AND date_trunc('month',a.data)=date_trunc('month',CURRENT_DATE)) AS atendimentos_mes
FROM dentistas d JOIN pessoa p ON p.id=d.id_pessoa LEFT JOIN usuarios u ON u.id_pessoa=p.id
JOIN especialidade esp ON esp.id=d.id_especialidade LEFT JOIN sala s ON s.id=d.id_sala;
COMMIT;
