<?php
// Tira o superusuário do .env (P0.2). Rode UMA vez, com o .env ainda no usuário antigo (postgres),
// depois de aplicar a migração 012 em todos os bancos (dentia e dentia_teste):
//   1. gera senhas aleatórias para dentia_app e dentia_migracao (nunca aparecem na tela);
//   2. testa o login dos dois;
//   3. guarda DB_USER/DB_PASS antigos em .env.superusuario e grava os novos no .env.
// Se algo falhar antes do passo 3, o .env fica como estava.
// Desfazer: copie as duas linhas de .env.superusuario de volta para o .env.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/Database.php';

$parar = function (string $motivo): never { fwrite(STDERR, $motivo . "\n"); exit(1); };
$env = dirname(__DIR__) . '/.env';
$guardado = dirname(__DIR__) . '/.env.superusuario';

$pdo = App\Config\Database::getConnection();
if (!$pdo->query('SELECT rolsuper FROM pg_roles WHERE rolname = current_user')->fetchColumn()) {
    $parar('O .env já não usa superusuário. Nada a fazer.');
}
if (!$pdo->query("SELECT has_table_privilege('dentia_app', 'usuarios', 'SELECT')")->fetchColumn()) {
    $parar('O banco ' . getenv('DB_NAME') . ' ainda não tem a migração 012. Rode antes: php scripts/aplicar_migracoes.php');
}
if (file_exists($guardado)) $parar('.env.superusuario já existe: este script já rodou. Apague o arquivo só se souber a senha do postgres.');

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$banco = $pdo->query('SELECT current_database()')->fetchColumn();
$senhas = ['dentia_app' => bin2hex(random_bytes(24)), 'dentia_migracao' => bin2hex(random_bytes(24))];
foreach ($senhas as $papel => $senha) {
    $pdo->exec("ALTER ROLE $papel PASSWORD " . $pdo->quote($senha));
    try {
        $teste = new PDO("pgsql:host=$host;port=$port;dbname=$banco", $papel, $senha, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($teste->query('SELECT current_user')->fetchColumn() !== $papel) throw new RuntimeException('papel inesperado');
    } catch (Throwable $e) {
        $parar("Login como $papel falhou; o .env não foi alterado. Confira o pg_hba.conf.");
    }
}

$linhas = file($env, FILE_IGNORE_NEW_LINES);
$antigas = array_values(array_filter($linhas, fn($l) => preg_match('/^\s*DB_(USER|PASS)\s*=/', $l)));
file_put_contents($guardado, "# Acesso de superusuário que estava no .env. Guarde num gerenciador de senhas e apague este arquivo.\n"
    . implode("\n", $antigas) . "\n");

$novas = [];
foreach ($linhas as $l) {
    if (preg_match('/^\s*DB_MIGRACAO_(USER|PASS)\s*=/', $l)) continue;
    if (preg_match('/^\s*DB_USER\s*=/', $l)) $l = 'DB_USER=dentia_app';
    elseif (preg_match('/^\s*DB_PASS\s*=/', $l)) $l = 'DB_PASS=' . $senhas['dentia_app'];
    $novas[] = $l;
}
$novas[] = '# Dono das tabelas: usado só por scripts/aplicar_migracoes.php e scripts/limpar_registros.php.';
$novas[] = 'DB_MIGRACAO_USER=dentia_migracao';
$novas[] = 'DB_MIGRACAO_PASS=' . $senhas['dentia_migracao'];
file_put_contents($env . '.novo', implode("\n", $novas) . "\n");
rename($env . '.novo', $env);

echo "[OK] .env agora usa dentia_app (site) e dentia_migracao (manutenção).\n";
echo "[OK] Acesso antigo guardado em .env.superusuario — mova para um gerenciador de senhas e apague o arquivo.\n";
