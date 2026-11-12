-- 013: consentimento LGPD guardado (P1.1), prontuário rastreável (P1.3) e registro de leitura (P1.4).
-- Não altera nem apaga dados existentes. Aplicar: php scripts/aplicar_migracoes.php
-- Sem BEGIN/COMMIT: o aplicador usa uma transação só.

-- P1.1: aceite do termo, com versão, hash do texto, origem, IP e data. Só acréscimo para a aplicação.
CREATE TABLE consentimento (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_paciente INT NOT NULL REFERENCES pacientes ON DELETE CASCADE,
    versao_termo TEXT NOT NULL,
    hash_termo CHAR(64) NOT NULL,              -- sha256 do texto aceito (o texto fica no código, por versão)
    origem TEXT NOT NULL CHECK (origem IN ('site', 'recepcao')),
    ip TEXT,
    registrado_por TEXT DEFAULT NULLIF(current_setting('dentia.usuario', true), ''),
    aceito_em TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX consentimento_paciente ON consentimento (id_paciente, aceito_em DESC);

-- P1.4: quem abriu os dados clínicos de qual paciente. Sem chave estrangeira: o registro não depende do cadastro.
CREATE TABLE acesso_prontuario (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    momento TIMESTAMPTZ NOT NULL DEFAULT now(),
    usuario TEXT NOT NULL,                     -- "perfil:e-mail" de quem leu
    id_paciente INT NOT NULL,
    pagina TEXT NOT NULL
);
CREATE INDEX acesso_prontuario_paciente ON acesso_prontuario (id_paciente, momento DESC);

-- P1.3: alergias, medicamentos e condições. A lista atual continua em historico_clinico;
-- tudo o que sai ou muda é copiado antes para historico_clinico_retificado, com quem e quando.
-- Linhas antigas ficam com registrado_em vazio (= já constava antes desta migração).
ALTER TABLE historico_clinico ADD COLUMN registrado_em TIMESTAMPTZ, ADD COLUMN registrado_por TEXT;
ALTER TABLE historico_clinico ALTER COLUMN registrado_em SET DEFAULT now(),
    ALTER COLUMN registrado_por SET DEFAULT NULLIF(current_setting('dentia.usuario', true), '');

CREATE TABLE historico_clinico_retificado (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_original INT NOT NULL,
    id_paciente INT NOT NULL,
    tipo tipo_historico NOT NULL,
    descricao TEXT NOT NULL,
    registrado_em TIMESTAMPTZ,
    registrado_por TEXT,
    operacao TEXT NOT NULL CHECK (operacao IN ('UPDATE', 'DELETE')),
    retificado_em TIMESTAMPTZ NOT NULL DEFAULT now(),
    retificado_por TEXT
);
CREATE INDEX historico_clinico_retificado_paciente ON historico_clinico_retificado (id_paciente, retificado_em DESC);

CREATE FUNCTION arquivar_historico_clinico() RETURNS TRIGGER LANGUAGE plpgsql SECURITY DEFINER SET search_path = public, pg_temp AS $$
BEGIN
    IF TG_OP = 'UPDATE' AND NEW.tipo = OLD.tipo AND NEW.descricao = OLD.descricao AND NEW.id_paciente = OLD.id_paciente THEN
        RETURN NEW;
    END IF;
    INSERT INTO historico_clinico_retificado (id_original, id_paciente, tipo, descricao, registrado_em, registrado_por, operacao, retificado_por)
    VALUES (OLD.id, OLD.id_paciente, OLD.tipo, OLD.descricao, OLD.registrado_em, OLD.registrado_por, TG_OP,
            NULLIF(current_setting('dentia.usuario', true), ''));
    RETURN COALESCE(NEW, OLD);
END $$;
CREATE TRIGGER historico_clinico_arquivo BEFORE UPDATE OR DELETE ON historico_clinico
    FOR EACH ROW EXECUTE FUNCTION arquivar_historico_clinico();

-- P1.3: evolução clínica é só acréscimo. Correção = nova evolução; a anterior fica.
CREATE FUNCTION bloquear_alteracao_clinica() RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Registro clínico (%) não pode ser alterado nem apagado. Registre uma nova evolução com a correção.', TG_TABLE_NAME;
END $$;
CREATE TRIGGER evolucao_clinica_so_acrescimo BEFORE UPDATE OR DELETE ON evolucao_clinica
    FOR EACH ROW EXECUTE FUNCTION bloquear_alteracao_clinica();
CREATE TRIGGER evolucao_dente_so_acrescimo BEFORE UPDATE OR DELETE ON evolucao_dente
    FOR EACH ROW EXECUTE FUNCTION bloquear_alteracao_clinica();

-- O consentimento entra no registro geral de alterações, como as outras tabelas.
CREATE TRIGGER auditoria_banco_registro AFTER INSERT OR UPDATE OR DELETE ON consentimento
    FOR EACH ROW EXECUTE FUNCTION registrar_auditoria();

-- A aplicação só acrescenta nesses registros (a 012 dá acesso completo por padrão às tabelas novas).
REVOKE UPDATE, DELETE ON consentimento, acesso_prontuario FROM dentia_app;
REVOKE INSERT, UPDATE, DELETE ON historico_clinico_retificado FROM dentia_app;
