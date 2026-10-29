-- Registra quando o paciente não compareceu. Faça backup antes (backup_banco.bat).
-- Não tem reversão simples: o PostgreSQL não remove valores de um ENUM sem recriar o tipo.
-- A consulta marcada como falta continua ocupando o horário (é sempre no passado).
ALTER TYPE status_agendamento ADD VALUE IF NOT EXISTS 'Faltou';
