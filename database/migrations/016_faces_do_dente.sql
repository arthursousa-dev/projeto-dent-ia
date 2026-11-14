-- 016: faces do dente no odontograma e na evolução clínica.
-- Nomenclatura: mesial, distal, vestibular, lingual (dentes inferiores) ou palatina (superiores),
-- oclusal (pré-molares e molares) ou incisal (incisivos e caninos).
-- Não altera dados existentes. Aplicar: php scripts/aplicar_migracoes.php  (sem BEGIN/COMMIT: o aplicador usa uma transação só)

-- Situação atual de cada face de um dente do odontograma.
CREATE TABLE odontograma_faces (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_dente INT NOT NULL REFERENCES odontograma_dentes ON DELETE RESTRICT,
    face TEXT NOT NULL CHECK (face IN ('mesial', 'distal', 'vestibular', 'lingual', 'palatina', 'oclusal', 'incisal')),
    status status_dente NOT NULL CHECK (status <> 'ausente'),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (id_dente, face)
);

-- Faces trabalhadas em cada evolução clínica ("restauração no dente 26, faces mesial e oclusal").
-- Só acréscimo, como o resto da evolução (migração 013).
CREATE TABLE evolucao_dente_face (
    id_evolucao INT NOT NULL,
    id_dente INT NOT NULL,
    face TEXT NOT NULL CHECK (face IN ('mesial', 'distal', 'vestibular', 'lingual', 'palatina', 'oclusal', 'incisal')),
    PRIMARY KEY (id_evolucao, id_dente, face),
    FOREIGN KEY (id_evolucao, id_dente) REFERENCES evolucao_dente ON DELETE RESTRICT
);
CREATE TRIGGER evolucao_dente_face_so_acrescimo BEFORE UPDATE OR DELETE ON evolucao_dente_face
    FOR EACH ROW EXECUTE FUNCTION bloquear_alteracao_clinica();

CREATE TRIGGER auditoria_banco_registro AFTER INSERT OR UPDATE OR DELETE ON odontograma_faces
    FOR EACH ROW EXECUTE FUNCTION registrar_auditoria();
CREATE TRIGGER auditoria_banco_registro AFTER INSERT OR UPDATE OR DELETE ON evolucao_dente_face
    FOR EACH ROW EXECUTE FUNCTION registrar_auditoria();
