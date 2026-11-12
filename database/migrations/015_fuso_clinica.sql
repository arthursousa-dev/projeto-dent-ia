-- 015: fuso horário da clínica configurável (P2.9). Antes era fixo em America/Cuiaba no código.
-- Não altera dados existentes: a clínica continua em America/Cuiaba até o gestor mudar em Configurações.
-- Aplicar: php scripts/aplicar_migracoes.php   (sem BEGIN/COMMIT: o aplicador usa uma transação só)

ALTER TABLE clinica ADD COLUMN fuso_horario TEXT NOT NULL DEFAULT 'America/Cuiaba'
    CHECK (fuso_horario ~ '^America/[A-Za-z_]+$');   -- a lista exata (fusos do Brasil) é conferida na aplicação
