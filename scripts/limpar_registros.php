<?php
// Limpeza agendada dos registros técnicos. Substitui a limpeza "ao acaso" que rodava dentro das páginas (P1.5).
// Política: tentativas de acesso duram 1 dia; a auditoria de tabelas técnicas, 180 dias.
// A auditoria de dados de pacientes, agenda e dinheiro NÃO é apagada aqui (prontuário: guarda mínima de 20 anos).
// Uso: php scripts/limpar_registros.php   — agende 1 vez por dia no Agendador de Tarefas.
// Conecta como dentia_migracao (DB_MIGRACAO_USER no .env): o papel da aplicação não pode apagar auditoria.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/Database.php';

App\Config\Database::usarPapelDeMigracao();
$pdo = App\Config\Database::getConnection();
$tentativas = $pdo->exec("DELETE FROM tentativa_acesso WHERE momento < now() - interval '1 day'");
$auditoria = $pdo->exec("DELETE FROM auditoria_banco WHERE momento < now() - interval '180 days'
    AND tabela IN ('tentativa_acesso', 'recuperacao_senha')");
echo date('Y-m-d H:i') . ' ' . $pdo->query('SELECT current_database()')->fetchColumn()
    . ": $tentativas tentativas de acesso e $auditoria linhas de auditoria técnica removidas\n";
