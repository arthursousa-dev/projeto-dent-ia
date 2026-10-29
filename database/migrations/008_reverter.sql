-- Reverte a 008: volta a vw_usuarios anterior (com a coluna senha) e remove o registro de tentativas.
BEGIN;
DROP VIEW vw_usuarios;
CREATE VIEW vw_usuarios AS
SELECT u.id, u.email, u.senha_hash AS senha, u.tipo, p.nome, formatar_cpf(p.cpf) AS cpf,
       (SELECT formatar_telefone(t.numero) FROM telefone t WHERE t.id_pessoa=p.id AND t.finalidade='principal') AS telefone,
       to_char(p.nascimento,'YYYY-MM-DD') AS nascimento,
       CASE u.tipo WHEN 'dono' THEN 'Proprietário' WHEN 'dentista' THEN 'Dentista'
       WHEN 'recepcionista' THEN 'Recepcionista' ELSE 'Paciente' END AS cargo,
       u.status, u.criado_em::timestamp AS criado_em
FROM usuarios u JOIN pessoa p ON p.id=u.id_pessoa;
DROP TABLE IF EXISTS tentativa_acesso;
COMMIT;
