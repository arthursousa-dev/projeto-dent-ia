<?php
/** Dados ao vivo da tela "Banco de dados": alterações novas e situação das tabelas. Só o gestor, só neste computador. */
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SESSION['perfil'] ?? '') !== 'dono') {
    http_response_code(403);
    echo json_encode(['erro' => 'Sessão encerrada. Entre novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}
exigirMonitor();
$pdo = \App\Config\Database::getConnection();
$desde = idParametro($_GET['desde'] ?? 0);
$resumo = resumoBanco($pdo);
echo json_encode([
    'resumo' => $resumo,
    'alteracoes' => array_reverse(registroAlteracoes($pdo, $desde, 200)),
    'tabelas' => array_map(fn($t) => [
        'tabela' => $t['tabela'], 'registros' => (int)$t['registros'], 'ultima' => $t['ultima_alteracao'],
        'contagem' => (int)$t['insercoes'] . ' / ' . (int)$t['alteracoes'] . ' / ' . (int)$t['exclusoes'], 'na_hora' => (int)$t['na_hora'], 'tamanho' => $t['tamanho'],
    ], tabelasMonitor($pdo)),
], JSON_UNESCAPED_UNICODE);
