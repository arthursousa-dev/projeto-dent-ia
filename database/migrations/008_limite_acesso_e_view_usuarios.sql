-- 008: limite de tentativas por origem (login e cadastro do paciente) e vw_usuarios sem o hash da senha.
-- Não altera dados existentes. Faça backup antes (backup_banco.bat).
-- Aplicar:  psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/008_limite_acesso_e_view_usuarios.sql
-- Reverter: psql -v ON_ERROR_STOP=1 -d dentia -f database/migrations/008_reverter.sql
BEGIN;

CREATE TABLE IF NOT EXISTS tentativa_acesso (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    chave TEXT NOT NULL,            -- ação + IP de origem, ex.: "login:192.168.0.10"
    momento TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS tentativa_acesso_chave_momento ON tentativa_acesso (chave, momento);

-- As telas não precisam do hash da senha; tirá-lo da view impede que ele circule nos arrays e seja regravado.
DROP VIEW vw_usuarios;
CREATE VIEW vw_usuarios AS
SELECT u.id, u.email, u.tipo, p.nome, formatar_cpf(p.cpf) AS cpf,
       (SELECT formatar_telefone(t.numero) FROM telefone t WHERE t.id_pessoa=p.id AND t.finalidade='principal') AS telefone,
       to_char(p.nascimento,'YYYY-MM-DD') AS nascimento,
       CASE u.tipo WHEN 'dono' THEN 'Proprietário' WHEN 'dentista' THEN 'Dentista'
       WHEN 'recepcionista' THEN 'Recepcionista' ELSE 'Paciente' END AS cargo,
       u.status, u.criado_em::timestamp AS criado_em
FROM usuarios u JOIN pessoa p ON p.id=u.id_pessoa;

COMMIT;
