-- ============================================================
-- DENT IA — Schema PostgreSQL
-- Substitui o armazenamento em arquivos JSON por um banco
-- relacional de verdade.
-- ============================================================

CREATE TYPE tipo_usuario AS ENUM ('dono', 'dentista', 'recepcionista', 'cliente');
CREATE TYPE status_usuario AS ENUM ('ativo', 'inativo');
CREATE TYPE status_agendamento AS ENUM ('Agendado', 'Confirmado', 'Aguardando', 'Em atendimento', 'Concluída', 'Cancelado');
CREATE TYPE status_dente AS ENUM ('saudavel', 'tratado', 'precisaTratamento', 'ausente');

-- ------------------------------------------------------------
-- usuarios — autenticação e controle de acesso por perfil
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id          SERIAL PRIMARY KEY,
    email       VARCHAR(150) NOT NULL UNIQUE,
    senha_hash  VARCHAR(255) NOT NULL,
    tipo        tipo_usuario NOT NULL,
    nome        VARCHAR(150) NOT NULL,
    cpf         VARCHAR(14),
    telefone    VARCHAR(20),
    nascimento  DATE,
    cargo       VARCHAR(80),
    status      status_usuario NOT NULL DEFAULT 'ativo',
    tentativas_login  SMALLINT NOT NULL DEFAULT 0,
    bloqueado_ate     TIMESTAMP NULL,
    criado_em   TIMESTAMP NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- clinica — configuração única da clínica (linha singleton)
-- ------------------------------------------------------------
CREATE TABLE clinica (
    id                      SMALLINT PRIMARY KEY DEFAULT 1,
    nome                    VARCHAR(150) NOT NULL,
    razao_social            VARCHAR(200) NOT NULL,
    cnpj                    VARCHAR(20)  NOT NULL,
    telefone                VARCHAR(20)  NOT NULL,
    email                   VARCHAR(150) NOT NULL,
    endereco                VARCHAR(200) NOT NULL,
    bairro                  VARCHAR(100) NOT NULL,
    cidade                  VARCHAR(100) NOT NULL,
    estado                  CHAR(2)      NOT NULL,
    cep                     VARCHAR(10)  NOT NULL,
    horario                 VARCHAR(200) NOT NULL,
    logo_texto              VARCHAR(50)  NOT NULL,
    cor_primaria            VARCHAR(7)   NOT NULL,
    cor_destaque            VARCHAR(7)   NOT NULL,
    limite_agendamentos_dia SMALLINT     NOT NULL DEFAULT 20,
    antecedencia_min_dias   SMALLINT     NOT NULL DEFAULT 1,
    atualizado_em           TIMESTAMP    NOT NULL DEFAULT now(),
    CONSTRAINT unica_linha CHECK (id = 1)
);

-- ------------------------------------------------------------
-- pacientes
-- ------------------------------------------------------------
CREATE TABLE pacientes (
    id                    SERIAL PRIMARY KEY,
    id_usuario            INT REFERENCES usuarios(id) ON DELETE SET NULL,
    nome                  VARCHAR(150) NOT NULL,
    cpf                   VARCHAR(14)  NOT NULL UNIQUE,
    rg                    VARCHAR(20),
    email                 VARCHAR(150) NOT NULL,
    telefone              VARCHAR(20)  NOT NULL,
    telefone_emergencia   VARCHAR(20),
    nascimento            DATE         NOT NULL,
    sexo                  CHAR(1),
    estado_civil          VARCHAR(20),
    profissao             VARCHAR(100),
    plano                 VARCHAR(50)  NOT NULL DEFAULT 'Particular',
    numero_carteirinha    VARCHAR(50),
    endereco              VARCHAR(200),
    bairro                VARCHAR(100),
    cidade                VARCHAR(100),
    estado                CHAR(2),
    cep                   VARCHAR(10),
    alergias              TEXT,
    medicamentos_em_uso   TEXT,
    doencas_preexistentes TEXT,
    observacoes           TEXT,
    status                status_usuario NOT NULL DEFAULT 'ativo',
    criado_em             TIMESTAMP NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- dentistas
-- ------------------------------------------------------------
CREATE TABLE dentistas (
    id               SERIAL PRIMARY KEY,
    id_usuario       INT REFERENCES usuarios(id) ON DELETE SET NULL,
    nome             VARCHAR(150) NOT NULL,
    email            VARCHAR(150) NOT NULL,
    telefone         VARCHAR(20)  NOT NULL,
    cpf              VARCHAR(14)  NOT NULL UNIQUE,
    nascimento       DATE,
    cro              VARCHAR(30)  NOT NULL,
    especialidade    VARCHAR(100) NOT NULL,
    titulacao        VARCHAR(150),
    faculdade        VARCHAR(150),
    ano_formatura    SMALLINT,
    admissao         DATE,
    cor              VARCHAR(7)   NOT NULL DEFAULT '#00c9a7',
    sala             VARCHAR(30),
    status           status_usuario NOT NULL DEFAULT 'ativo',
    horarios         TEXT[] NOT NULL DEFAULT '{}',
    dias_atendimento TEXT[] NOT NULL DEFAULT '{}',
    criado_em        TIMESTAMP NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- procedimentos — catálogo de serviços da clínica
-- ------------------------------------------------------------
CREATE TABLE procedimentos (
    id          SERIAL PRIMARY KEY,
    nome        VARCHAR(150) NOT NULL UNIQUE,
    categoria   VARCHAR(60)  NOT NULL,
    duracao_min SMALLINT     NOT NULL,
    valor_base  NUMERIC(10,2) NOT NULL
);

-- Relação N:N entre dentistas e os procedimentos que realizam
CREATE TABLE dentista_procedimentos (
    id_dentista     INT NOT NULL REFERENCES dentistas(id) ON DELETE CASCADE,
    id_procedimento INT NOT NULL REFERENCES procedimentos(id) ON DELETE CASCADE,
    PRIMARY KEY (id_dentista, id_procedimento)
);

-- ------------------------------------------------------------
-- agendamentos
-- ------------------------------------------------------------
CREATE TABLE agendamentos (
    id              SERIAL PRIMARY KEY,
    id_paciente     INT NOT NULL REFERENCES pacientes(id) ON DELETE CASCADE,
    id_dentista     INT NOT NULL REFERENCES dentistas(id) ON DELETE CASCADE,
    id_procedimento INT REFERENCES procedimentos(id) ON DELETE SET NULL,
    data            DATE NOT NULL,
    hora            TIME NOT NULL,
    sala            VARCHAR(30),
    status          status_agendamento NOT NULL DEFAULT 'Agendado',
    valor           NUMERIC(10,2) NOT NULL,
    pago            BOOLEAN NOT NULL DEFAULT false,
    forma_pagamento VARCHAR(30),
    observacoes     TEXT,
    criado_em       TIMESTAMP NOT NULL DEFAULT now(),

    -- não permite dois agendamentos pro mesmo dentista, no mesmo horário
    CONSTRAINT horario_unico_por_dentista UNIQUE (id_dentista, data, hora)
);

CREATE INDEX idx_agendamentos_paciente ON agendamentos(id_paciente);
CREATE INDEX idx_agendamentos_data     ON agendamentos(data);

-- ------------------------------------------------------------
-- prontuarios — um por paciente
-- ------------------------------------------------------------
CREATE TABLE prontuarios (
    id            SERIAL PRIMARY KEY,
    id_paciente   INT NOT NULL UNIQUE REFERENCES pacientes(id) ON DELETE CASCADE,
    criado_em     TIMESTAMP NOT NULL DEFAULT now(),
    atualizado_em TIMESTAMP NOT NULL DEFAULT now()
);

-- Odontograma: um registro por dente (numeração FDI) por prontuário
CREATE TABLE odontograma_dentes (
    id             SERIAL PRIMARY KEY,
    id_prontuario  INT NOT NULL REFERENCES prontuarios(id) ON DELETE CASCADE,
    numero_dente   SMALLINT NOT NULL,
    status         status_dente NOT NULL DEFAULT 'saudavel',
    notas          TEXT,
    atualizado_em  TIMESTAMP NOT NULL DEFAULT now(),
    UNIQUE (id_prontuario, numero_dente)
);

-- Histórico de procedimentos realizados em cada dente
CREATE TABLE odontograma_procedimentos (
    id                  SERIAL PRIMARY KEY,
    id_dente            INT NOT NULL REFERENCES odontograma_dentes(id) ON DELETE CASCADE,
    descricao           VARCHAR(200) NOT NULL,
    registrado_em       TIMESTAMP NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------
-- faturamento_mensal — histórico financeiro por mês
-- ------------------------------------------------------------
CREATE TABLE faturamento_mensal (
    id                SERIAL PRIMARY KEY,
    ano               SMALLINT NOT NULL,
    mes_num           SMALLINT NOT NULL CHECK (mes_num BETWEEN 1 AND 12),
    receita           NUMERIC(12,2) NOT NULL DEFAULT 0,
    despesas          NUMERIC(12,2) NOT NULL DEFAULT 0,
    qtd_procedimentos INT NOT NULL DEFAULT 0,
    UNIQUE (ano, mes_num)
);

-- ------------------------------------------------------------
-- Índices de apoio pra buscas comuns
-- ------------------------------------------------------------
CREATE INDEX idx_pacientes_nome  ON pacientes (nome);
CREATE INDEX idx_usuarios_email  ON usuarios (email);
