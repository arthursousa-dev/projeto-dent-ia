<?php
// Cria um banco de teste só com schema.sql + seed.sql + migrações: nenhum dado vem de backup (P2.12).
// Uso: php scripts/criar_banco_teste.php [nome]      nome padrão: dentia_limpo_teste
// Conecta como dentia_migracao (.env). Recusa nome que não termine em _teste/_test e banco que já exista:
// para refazer, apague antes com:  dropdb -U dentia_migracao <nome>
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/Database.php';

use App\Config\Database;

$parar = function (string $motivo): never { fwrite(STDERR, $motivo . "\n"); exit(1); };
$nome = $argv[1] ?? 'dentia_limpo_teste';
if (!preg_match('/^[a-z][a-z0-9_]*_teste?$/', $nome)) $parar('O nome precisa terminar em _teste ou _test e ter só letras minúsculas, números e "_".');

putenv('DB_NAME=postgres'); // banco de manutenção, só para criar o novo
Database::usarPapelDeMigracao();
$pdo = Database::getConnection();
$existe = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :nome');
$existe->execute(['nome' => $nome]);
if ($existe->fetchColumn()) $parar("O banco $nome já existe. Nada foi alterado.");
$pdo->exec("CREATE DATABASE $nome");

$raiz = dirname(__DIR__);
try {
    $novo = new PDO('pgsql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';port=' . (getenv('DB_PORT') ?: '5432') . ";dbname=$nome",
        getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['database/schema.sql', 'database/seed.sql'] as $arquivo) {
        $novo->exec(file_get_contents("$raiz/$arquivo"));
        echo "[OK] $arquivo\n";
    }
    $novo->exec('TRUNCATE auditoria_banco'); // a carga inicial não entra no registro de alterações (igual ao instalar_banco.bat)
    $novo = null;
} catch (Throwable $e) {
    $novo = null;
    $pdo->exec("DROP DATABASE IF EXISTS $nome");
    $parar('Falhou ao carregar schema/seed; o banco novo foi apagado. ' . $e->getMessage());
}

// As migrações a partir da 012 entram pelo aplicador, em processo próprio (ele lê DB_NAME do ambiente).
putenv("DB_NAME=$nome");
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/scripts/aplicar_migracoes.php"), $saida);
if ($saida !== 0) $parar("Migrações falharam em $nome. O banco ficou criado para você inspecionar.");
echo "[OK] Banco $nome pronto. Rode os testes com DB_NAME=$nome\n";
