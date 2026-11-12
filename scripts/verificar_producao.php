<?php
// Portão de "pôr em produção". Só leitura. Sai com código 1 enquanto houver pendência crítica.
// Uso: php scripts/verificar_producao.php          (banco do .env)
//      DB_NAME=dentia_teste php scripts/verificar_producao.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/functions.php';

$pdo = \App\Config\Database::getConnection();
$pdo->exec('SET default_transaction_read_only = on');
$pendencias = [];

// 1. Conta ativa com senha de demonstração ou das mais comuns.
$contas = $pdo->query("SELECT email, tipo, senha_hash FROM usuarios WHERE status='ativo' ORDER BY tipo, email")->fetchAll();
foreach ($contas as $c) {
    foreach (SENHAS_PROIBIDAS as $senha) {
        if (password_verify($senha, $c['senha_hash'])) {
            $pendencias[] = "Conta {$c['tipo']} {$c['email']} usa senha de demonstração ou comum.";
            break;
        }
    }
}

// 2. A aplicação não pode conectar como superusuário do PostgreSQL.
if ($pdo->query('SELECT rolsuper FROM pg_roles WHERE rolname = current_user')->fetchColumn()) {
    $pendencias[] = 'A aplicação conecta como superusuário (' . $pdo->query('SELECT current_user')->fetchColumn() . '). Use o papel dentia_app.';
}

// 3. Modo demonstração mostra as contas de exemplo na tela de login.
if (getenv('APP_DEMO') === 'true') $pendencias[] = 'APP_DEMO=true: desligue o modo demonstração no .env.';

echo 'Banco: ' . $pdo->query('SELECT current_database()')->fetchColumn() . ' — ' . count($contas) . " contas ativas conferidas\n";
if (!$pendencias) { echo "[OK] Nenhuma pendência crítica.\n"; exit(0); }
foreach ($pendencias as $p) echo '[PENDENTE] ' . $p . "\n";
echo 'RECUSADO: ' . count($pendencias) . " pendência(s). Não coloque pacientes reais neste banco ainda.\n";
exit(1);
