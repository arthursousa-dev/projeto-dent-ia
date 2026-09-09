-- ============================================================
-- DENT IA — Dados de demonstração (migrados do armazenamento
-- em JSON original para o schema PostgreSQL)
-- ============================================================

-- usuarios
INSERT INTO usuarios (id, email, senha_hash, tipo, nome, cpf, telefone, nascimento, cargo, status, criado_em) VALUES
(1, 'dono@dentai.com', '$2b$10$gQkZCg8hNrlWwiHZXXmg.e5HQ76nbCnGd96AetT62q3FN7Ffd1TIW', 'dono', 'Arthur Sousa', '444.555.666-77', '(67) 99292-8122', '1995-05-10', 'Proprietário', 'ativo', '2019-01-01'::timestamp),
(2, 'dentista@dentai.com', '$2b$10$faf7bTrGRCY7WfN0/KaLN./.02S2sC9cesFcKJiP1kdnJJSOizwWW', 'dentista', 'Dr. Carlos Mendes', '333.444.555-66', '(67) 99111-0001', '1978-03-20', NULL, 'ativo', '2020-03-01'::timestamp),
(3, 'recepcao@dentai.com', '$2b$10$Avfl3IHtC/4reK4SjL6OsekGMLChIWqJ.a6RfNu/E1nU0ykmjSlYq', 'recepcionista', 'João Recepção', '222.333.444-55', '(67) 99211-2222', '1990-07-23', 'Recepcionista', 'ativo', '2023-06-01'::timestamp),
(4, 'cliente@gmail.com', '$2b$10$aawDJlVYnFexl03flR4VrO1RTYT4b19MCxV/oVQWUMlnZg/xfdyBi', 'cliente', 'Maria Oliveira', '111.222.333-44', '(67) 99211-1111', '1985-04-12', NULL, 'ativo', '2024-01-15'::timestamp),
(5, 'ana@dentai.com', '$2b$10$IVHxFKz08QBndxOLTAImcuKLsmMSu0X33P/OFve0XkHPdXwcUC6/S', 'dentista', 'Dra. Ana Silva', '555.111.222-33', '(67) 99111-0002', '1982-09-14', NULL, 'ativo', '2021-06-15'::timestamp),
(6, 'paulo@dentai.com', '$2b$10$zaPL2a6e6nRSqp.o5.LiZeJx9KSYbOhLRyt/nyKec66X/pVEiORza', 'dentista', 'Dr. Paulo Ramos', '666.222.333-44', '(67) 99111-0003', '1974-11-08', NULL, 'ativo', '2022-01-10'::timestamp),
(7, 'luciana@dentai.com', '$2b$10$u2mBhFLNJSVp9U2FAjLddeXYCCtFeJXbL5C/oXh18edcW6s2v77kG', 'dentista', 'Dra. Luciana Torres', '777.333.444-55', '(67) 99111-0004', '1980-06-25', NULL, 'ativo', '2019-09-20'::timestamp);
SELECT setval('usuarios_id_seq', 7);

-- clinica (linha única)
INSERT INTO clinica (id, nome, razao_social, cnpj, telefone, email, endereco, bairro, cidade, estado, cep, horario, logo_texto, cor_primaria, cor_destaque, limite_agendamentos_dia, antecedencia_min_dias, atualizado_em) VALUES
(1, 'DENT IA', 'Clínica Odontológica DENT IA Ltda.', '12.345.678/0001-99', '(67) 99292-8122', 'contato@dentai.com.br', 'Rua das Flores, 142', 'Centro', 'Campo Grande', 'MS', '79002-000', 'Segunda a Sexta: 08h às 18h | Sábado: 08h às 13h', 'DENT IA', '#0b2845', '#00c9a7', 20, 1, '2026-04-10'::timestamp);

-- pacientes
INSERT INTO pacientes (id, id_usuario, nome, cpf, rg, email, telefone, telefone_emergencia, nascimento, sexo, estado_civil, profissao, plano, numero_carteirinha, endereco, bairro, cidade, estado, cep, alergias, medicamentos_em_uso, doencas_preexistentes, observacoes, status, criado_em) VALUES
(1, 4, 'Maria Oliveira', '111.222.333-44', '1.234.567', 'maria@email.com', '(67) 99211-1111', '(67) 99211-9999', '1985-04-12', 'F', 'Casada', 'Professora', 'Particular', NULL, 'Rua das Flores, 45', 'Centro', 'Campo Grande', 'MS', '79002-000', 'Nenhuma conhecida', 'Nenhum', 'Nenhuma', 'Paciente colaborativa, sem complicações anteriores.', 'ativo', '2024-01-15'::timestamp),
(2, NULL, 'João Santos', '222.333.444-55', '2.345.678', 'joao@email.com', '(67) 99211-2222', '(67) 99211-8888', '1990-07-23', 'M', 'Solteiro', 'Engenheiro', 'Unimed Odonto', 'UNI-00123456', 'Av. Afonso Pena, 1200', 'Jardim dos Estados', 'Campo Grande', 'MS', '79020-001', 'Lidocaína', 'Nenhum', 'Nenhuma', 'Alergia a anestésico convencional — usar Mepivacaína.', 'ativo', '2024-03-10'::timestamp),
(3, NULL, 'Carla Pereira', '333.444.555-66', '3.456.789', 'carla@email.com', '(67) 99211-3333', '(67) 99211-7777', '1978-11-05', 'F', 'Casada', 'Contadora', 'SulAmérica Odonto', 'SUL-00234567', 'Rua Bahia, 320', 'Bela Vista', 'Campo Grande', 'MS', '79040-100', 'Nenhuma conhecida', 'Levotiroxina 50mcg', 'Hipotireoidismo', 'Em tratamento ortodôntico há 18 meses.', 'ativo', '2024-02-20'::timestamp),
(4, NULL, 'Pedro Alves', '444.555.666-77', '4.567.890', 'pedro@email.com', '(67) 99211-4444', '(67) 99211-6666', '1995-01-30', 'M', 'Solteiro', 'Analista de TI', 'Particular', NULL, 'Rua Ceará, 780', 'Monte Castelo', 'Campo Grande', 'MS', '79010-200', 'Nenhuma conhecida', 'Nenhum', 'Nenhuma', 'Candidato a implante dentário no quadrante inferior esquerdo.', 'ativo', '2024-04-05'::timestamp),
(5, NULL, 'Fernanda Costa', '555.666.777-88', '5.678.901', 'fernanda@email.com', '(67) 99211-5555', '(67) 99211-5500', '1988-09-17', 'F', 'Divorciada', 'Médica', 'Odontoprev', 'ODP-00345678', 'Av. Mato Grosso, 560', 'Cabreúva', 'Campo Grande', 'MS', '79050-300', 'Nenhuma conhecida', 'Nenhum', 'Nenhuma', 'Paciente inativa — contatar para retorno.', 'inativo', '2023-11-12'::timestamp),
(6, NULL, 'Ricardo Souza', '666.777.888-99', '6.789.012', 'ricardo@email.com', '(67) 99211-6666', '(67) 99211-6600', '2000-03-08', 'M', 'Solteiro', 'Estudante', 'Particular', NULL, 'Rua Minas Gerais, 200', 'Pioneiros', 'Campo Grande', 'MS', '79060-400', 'Nenhuma conhecida', 'Nenhum', 'Bruxismo', 'Bruxismo — placa miorrelaxante indicada.', 'ativo', '2024-05-18'::timestamp),
(7, NULL, 'Ana Ferreira', '777.888.999-00', '7.890.123', 'ana.ferreira@email.com', '(67) 99211-7777', '(67) 99211-7700', '1972-06-30', 'F', 'Casada', 'Empresária', 'Bradesco Saúde Dental', 'BRS-00456789', 'Rua Espírito Santo, 100', 'Santa Fé', 'Campo Grande', 'MS', '79070-500', 'Penicilina', 'Atenolol 25mg, Losartana 50mg', 'Hipertensão arterial', 'Hipertensa — monitorar pressão antes de anestesia. Não usar vasoconstritores.', 'ativo', '2023-08-22'::timestamp),
(8, NULL, 'Lucas Martins', '888.999.000-11', '8.901.234', 'lucas@email.com', '(67) 99211-8888', '(67) 99211-8800', '2010-12-15', 'M', 'Solteiro', 'Estudante', 'Unimed Odonto', 'UNI-00567890', 'Rua Paraná, 450', 'Amambai', 'Campo Grande', 'MS', '79080-600', 'Nenhuma conhecida', 'Nenhum', 'Nenhuma', 'Paciente pediátrico. Dentição mista — acompanhamento de erupção.', 'ativo', '2024-06-01'::timestamp);
SELECT setval('pacientes_id_seq', 8);

-- dentistas
INSERT INTO dentistas (id, id_usuario, nome, email, telefone, cpf, nascimento, cro, especialidade, titulacao, faculdade, ano_formatura, admissao, cor, sala, status, horarios, dias_atendimento) VALUES
(1, 2, 'Dr. Carlos Mendes', 'carlos@dentai.com', '(67) 99111-0001', '333.444.555-66', '1978-03-20', 'CRO-MS 12345', 'Clínico Geral', 'Especialista em Dentística', 'UFMS', 2002, '2020-03-01', '#00c9a7', 'Sala 1', 'ativo', '{"08:00","08:30","09:00","09:30","10:00","10:30","11:00","11:30","14:00","14:30","15:00","15:30","16:00","16:30","17:00"}', '{"Segunda","Terça","Quarta","Quinta","Sexta"}'),
(2, 5, 'Dra. Ana Silva', 'ana@dentai.com', '(67) 99111-0002', '555.111.222-33', '1982-09-14', 'CRO-MS 23456', 'Ortodontia', 'Mestre em Ortodontia - USP', 'USP', 2006, '2021-06-15', '#3182ce', 'Sala 2', 'ativo', '{"09:00","09:30","10:00","10:30","11:00","14:00","14:30","15:00","15:30","16:00","16:30"}', '{"Segunda","Terça","Quarta","Quinta","Sexta"}'),
(3, 6, 'Dr. Paulo Ramos', 'paulo@dentai.com', '(67) 99111-0003', '666.222.333-44', '1974-11-08', 'CRO-MS 34567', 'Cirurgia Oral', 'Doutor em Cirurgia Oral - UNICAMP', 'UNICAMP', 1998, '2022-01-10', '#d97706', 'Sala 3', 'ativo', '{"08:00","09:00","10:00","11:00","14:00","15:00","16:00"}', '{"Terça","Quinta"}'),
(4, 7, 'Dra. Luciana Torres', 'luciana@dentai.com', '(67) 99111-0004', '777.333.444-55', '1980-06-25', 'CRO-MS 45678', 'Endodontia', 'Especialista em Endodontia', 'PUC-SP', 2004, '2019-09-20', '#805ad5', 'Sala 4', 'ativo', '{"08:00","08:30","09:00","09:30","10:00","10:30","11:00","14:00","14:30","15:00","15:30","16:00"}', '{"Segunda","Quarta","Sexta"}');
SELECT setval('dentistas_id_seq', 4);

-- procedimentos
INSERT INTO procedimentos (id, nome, categoria, duracao_min, valor_base) VALUES
(1, 'Consulta de Rotina', 'Preventivo', 30, 90.0),
(2, 'Limpeza Dental', 'Preventivo', 60, 120.0),
(3, 'Selante', 'Preventivo', 30, 80.0),
(4, 'Flúor Tópico', 'Preventivo', 20, 50.0),
(5, 'Restauração', 'Restaurador', 60, 180.0),
(6, 'Restauração Ampla', 'Restaurador', 90, 280.0),
(7, 'Clareamento Dental', 'Estético', 90, 450.0),
(8, 'Faceta de Porcelana', 'Estético', 120, 1200.0),
(9, 'Canal Radicular', 'Endodontia', 120, 1200.0),
(10, 'Retratamento Endodôntico', 'Endodontia', 150, 1500.0),
(11, 'Extração Simples', 'Cirurgia', 45, 180.0),
(12, 'Extração de Siso', 'Cirurgia', 90, 350.0),
(13, 'Implante Dentário', 'Implantodontia', 120, 2800.0),
(14, 'Ortodontia (Aparelho Metálico)', 'Ortodontia', 60, 280.0),
(15, 'Ortodontia (Aparelho Estético)', 'Ortodontia', 60, 350.0),
(16, 'Alinhadores Transparentes', 'Ortodontia', 60, 400.0),
(17, 'Coroa de Porcelana', 'Prótese', 90, 1800.0),
(18, 'Prótese Total', 'Prótese', 60, 2200.0),
(19, 'Placa Miorrelaxante', 'Preventivo', 30, 600.0),
(20, 'Periodontia', 'Periodontia', 60, 250.0),
(21, 'Cirurgia de Gengiva', 'Periodontia', 90, 800.0),
(22, 'Enxerto Ósseo', 'Implantodontia', 120, 2000.0),
(23, 'Pulpotomia', 'Endodontia', 60, 500.0),
(24, 'Contenção Ortodôntica', 'Ortodontia', 30, 200.0);
SELECT setval('procedimentos_id_seq', 24);

-- dentista_procedimentos
INSERT INTO dentista_procedimentos (id_dentista, id_procedimento) VALUES
(1, 2),
(1, 5),
(1, 7),
(1, 1),
(1, 3),
(1, 20),
(2, 14),
(2, 15),
(2, 24),
(2, 16),
(3, 11),
(3, 12),
(3, 13),
(3, 21),
(3, 22),
(4, 9),
(4, 10),
(4, 23);

-- agendamentos
INSERT INTO agendamentos (id, id_paciente, id_dentista, id_procedimento, data, hora, sala, status, valor, pago, forma_pagamento, observacoes) VALUES
(1, 1, 1, 2, '2026-04-10', '08:00', 'Sala 1', 'Confirmado', 120.0, true, 'Pix', NULL),
(2, 2, 2, 14, '2026-04-10', '09:00', 'Sala 2', 'Em atendimento', 280.0, false, '', 'Ajuste mensal — 3° mês'),
(3, 3, 2, 14, '2026-04-10', '10:00', 'Sala 2', 'Aguardando', 180.0, false, '', 'Ajuste mensal de aparelho — 18° mês'),
(4, 4, 3, 12, '2026-04-10', '11:00', 'Sala 3', 'Aguardando', 350.0, false, '', 'Extração do siso inferior esquerdo (38)'),
(5, 5, 1, 1, '2026-04-10', '14:00', 'Sala 1', 'Agendado', 90.0, false, '', 'Retorno após inatividade'),
(6, 6, 4, 9, '2026-04-11', '09:00', 'Sala 4', 'Agendado', 1200.0, false, '', 'Dente 46 — sessão inicial. Verificar sensibilidade.'),
(7, 7, 1, 2, '2026-04-11', '10:00', 'Sala 1', 'Agendado', 120.0, false, '', 'Hipertensa — verificar PA antes do procedimento'),
(8, 8, 1, 1, '2026-04-11', '14:30', 'Sala 1', 'Agendado', 90.0, false, '', 'Acompanhamento dentição mista — paciente pediátrico'),
(9, 1, 1, 5, '2026-03-15', '08:00', 'Sala 1', 'Concluída', 220.0, true, 'Cartão Crédito', 'Restauração dente 41 — concluída sem intercorrências'),
(10, 2, 1, 2, '2026-03-10', '09:00', 'Sala 1', 'Concluída', 120.0, true, 'Pix', 'Limpeza de rotina — sem intercorrências');
SELECT setval('agendamentos_id_seq', 10);

-- prontuarios
INSERT INTO prontuarios (id, id_paciente) VALUES
(1, 1),
(2, 2),
(3, 6);
SELECT setval('prontuarios_id_seq', 3);

-- odontograma_dentes + odontograma_procedimentos
INSERT INTO odontograma_dentes (id, id_prontuario, numero_dente, status, notas) VALUES
(1, 1, 11, 'tratado', 'Clareamento realizado'),
(2, 1, 15, 'tratado', 'Restauração composta classe I'),
(3, 1, 16, 'precisaTratamento', 'Cárie Classe II face mesial'),
(4, 1, 22, 'precisaTratamento', 'Trinca de esmalte visível'),
(5, 1, 26, 'tratado', 'Selante preventivo aplicado'),
(6, 1, 33, 'precisaTratamento', 'Cárie inicial face vestibular'),
(7, 1, 36, 'tratado', 'Canal e coroa concluídos com sucesso'),
(8, 1, 41, 'tratado', 'Restauração realizada'),
(9, 1, 46, 'precisaTratamento', 'Cárie profunda, possível necessidade de canal'),
(10, 2, 18, 'precisaTratamento', 'Siso incluso — avaliação cirúrgica indicada'),
(11, 2, 28, 'precisaTratamento', 'Siso incluso — avaliação cirúrgica indicada'),
(12, 2, 38, 'tratado', 'Extração realizada'),
(13, 2, 48, 'tratado', 'Extração realizada'),
(14, 3, 46, 'precisaTratamento', 'Cárie profunda com comprometimento pulpar — canal indicado'),
(15, 3, 16, 'tratado', 'Restauração ampla'),
(16, 3, 36, 'tratado', 'Restauração de resina');
SELECT setval('odontograma_dentes_id_seq', 16);

INSERT INTO odontograma_procedimentos (id, id_dente, descricao) VALUES
(1, 1, 'Clareamento - Jan/2026'),
(2, 2, 'Restauração - Mai/2025'),
(3, 3, 'Restauração indicada'),
(4, 4, 'Avaliação necessária'),
(5, 5, 'Selante - Ago/2024'),
(6, 6, 'Restauração preventiva'),
(7, 7, 'Canal Radicular - Dez/2025'),
(8, 7, 'Coroa de Porcelana - Jan/2026'),
(9, 8, 'Restauração - Mar/2026'),
(10, 9, 'Avaliação urgente'),
(11, 9, 'Possível Canal Radicular'),
(12, 10, 'Avaliação cirúrgica'),
(13, 11, 'Avaliação cirúrgica'),
(14, 12, 'Extração - Jun/2024'),
(15, 13, 'Extração - Jun/2024'),
(16, 14, 'Canal Radicular indicado'),
(17, 15, 'Restauração - 2023'),
(18, 16, 'Restauração - 2024');
SELECT setval('odontograma_procedimentos_id_seq', 18);

-- faturamento_mensal
INSERT INTO faturamento_mensal (ano, mes_num, receita, despesas, qtd_procedimentos) VALUES
(2025, 11, 22100.0, 8400.0, 112),
(2025, 12, 19800.0, 7600.0, 98),
(2026, 1, 24300.0, 9200.0, 128),
(2026, 2, 26100.0, 9800.0, 138),
(2026, 3, 27900.0, 10200.0, 148),
(2026, 4, 28750.0, 10600.0, 156);
