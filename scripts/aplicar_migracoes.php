<?php
// Aplica só as migrações pendentes de database/migrations, cada uma em transação, com backup antes.
// Uso: php scripts/aplicar_migracoes.php            aplica as pendentes no banco do .env
//      php scripts/aplicar_migracoes.php --listar   só mostra o que está aplicado e o que falta
//      DB_NAME=dentia_teste php scripts/aplicar_migracoes.php
// Conecta com DB_MIGRACAO_USER/DB_MIGRACAO_PASS do .env quando existirem; senão, com DB_USER/DB_PASS.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/Config/Database.php';

use App\Config\Database;

Database::usarPapelDeMigracao();
$pdo = Database::getConnection();
$banco = $pdo->query('SELECT current_database()')->fetchColumn();
$pasta = dirname(__DIR__) . '/database/migrations';
$arquivos = array_values(array_filter(array_map('basename', glob($pasta . '/[0-9][0-9][0-9]_*.sql')),
    fn($a) => !str_ends_with($a, '_reverter.sql')));
sort($arquivos);

// Primeira vez neste banco: cria o registro. O schema.sql já traz tudo até a 011, então elas entram como aplicadas.
if (!$pdo->query("SELECT to_regclass('public.schema_migrations')")->fetchColumn()) {
    if (!$pdo->query("SELECT to_regclass('public.auditoria_banco')")->fetchColumn()) {
        fwrite(STDERR, "Banco \"$banco\" não tem a migração 011. Instale com schema.sql ou aplique até a 011 antes.\n");
        exit(1);
    }
    $pdo->beginTransaction();
    $pdo->exec('CREATE TABLE schema_migrations (nome TEXT PRIMARY KEY, aplicada_em TIMESTAMPTZ NOT NULL DEFAULT now())');
    $ins = $pdo->prepare('INSERT INTO schema_migrations (nome) VALUES (:nome)');
    foreach ($arquivos as $a) if ((int)substr($a, 0, 3) <= 11) $ins->execute(['nome' => $a]);
    $pdo->commit();
}

$aplicadas = $pdo->query('SELECT nome FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$pendentes = array_values(array_diff($arquivos, $aplicadas));
echo "Banco: $banco — " . count($aplicadas) . ' aplicadas, ' . count($pendentes) . " pendentes\n";
foreach ($pendentes as $p) echo "  pendente: $p\n";
if (!$pendentes || in_array('--listar', $_SERVER['argv'], true)) exit(0);

// Backup antes de mexer em banco que não é de teste. Sem backup, nada é aplicado.
if (!preg_match('/_teste?$/', $banco)) {
    $destino = dirname(__DIR__) . '/backups/' . $banco . '_antes_migracao_' . date('Ymd_His') . '.dump';
    if (!is_dir(dirname($destino))) mkdir(dirname($destino));
    putenv('PGPASSWORD=' . getenv('DB_PASS'));
    $cmd = 'pg_dump -Fc -h ' . escapeshellarg(getenv('DB_HOST') ?: 'localhost') . ' -p ' . escapeshellarg(getenv('DB_PORT') ?: '5432')
        . ' -U ' . escapeshellarg(getenv('DB_USER') ?: 'postgres') . ' -d ' . escapeshellarg($banco) . ' -f ' . escapeshellarg($destino);
    passthru($cmd, $saida);
    if ($saida !== 0 || !is_file($destino) || filesize($destino) === 0) {
        fwrite(STDERR, "Backup falhou (pg_dump). Nenhuma migração foi aplicada.\n");
        exit(1);
    }
    echo '  backup: backups/' . basename($destino) . "\n";
}

foreach ($pendentes as $p) {
    $sql = file_get_contents($pasta . '/' . $p);
    if (preg_match('/^\s*(BEGIN|COMMIT)\s*;/mi', $sql)) {
        fwrite(STDERR, "$p tem BEGIN/COMMIT próprio: tire, o aplicador já usa transação.\n");
        exit(1);
    }
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $pdo->prepare('INSERT INTO schema_migrations (nome) VALUES (:nome)')->execute(['nome' => $p]);
        $pdo->commit();
        echo "  [OK] $p\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fwrite(STDERR, "  [FALHOU] $p: " . $e->getMessage() . "\nNada desta migração ficou no banco.\n");
        exit(1);
    }
}
