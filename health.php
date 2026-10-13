<?php
// Para Healthcheck HTTP do Railway; não imprime credenciais ou dados clínicos.
try {
    require_once __DIR__.'/app/Config/Database.php';
    $pdo=\App\Config\Database::getConnection();
    $pdo->query("SELECT 1 FROM vw_agendamentos LIMIT 1")->fetchColumn();
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'ok']);
} catch (Throwable $e) {
    error_log('[DENTIA] Healthcheck: '.$e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'indisponivel']);
}
