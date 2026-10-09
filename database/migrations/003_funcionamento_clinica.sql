BEGIN;
CREATE TABLE funcionamento_clinica (
    id_clinica SMALLINT NOT NULL REFERENCES clinica ON DELETE RESTRICT,
    id_dia_semana SMALLINT NOT NULL REFERENCES dia_semana ON DELETE RESTRICT,
    abertura TIME NOT NULL, fechamento TIME NOT NULL,
    PRIMARY KEY (id_clinica,id_dia_semana), CHECK (abertura<fechamento)
);
CREATE INDEX idx_funcionamento_dia ON funcionamento_clinica(id_dia_semana);
INSERT INTO funcionamento_clinica SELECT c.id,d.id,'08:00'::time,
    CASE WHEN d.id=6 THEN '13:00'::time ELSE '18:00'::time END
FROM clinica c CROSS JOIN dia_semana d WHERE d.id BETWEEN 1 AND 6;
COMMIT;
