<?php
// Backup automático com rotação e teste de restauração (P0.5).
// Uso: php scripts/backup.php                        gera backups/auto_<banco>_<data>.dump e aplica a rotação
//      php scripts/backup.php --testar-restauracao   além disso restaura o dump em dentia_restore_teste,
//                                                    compara as linhas de cada tabela e apaga o banco temporário
// Conecta como dentia_migracao (.env). Rotação: 7 diários + 4 semanais, só entre os arquivos "auto_*";
// backups manuais (backup_banco.bat, antes de migração) nunca são apagados por aqui.
// O resultado de cada teste de restauração fica em backups/restauracoes.log.

/** Dos nomes de arquivo recebidos, quais a rotação apaga. Só olha "auto_<banco>_AAAAMMDD_HHMMSS.dump". */
function backupsParaApagar(array $nomes): array {
    $autos = array_values(array_filter($nomes, fn($n) => preg_match('/^auto_.+_\d{8}_\d{6}\.dump$/', $n)));
    rsort($autos); // mesmo banco: ordem do nome = ordem da data, mais novo primeiro
    $dias = $semanas = $apagar = [];
    foreach ($autos as $nome) {
        preg_match('/_(\d{8})_\d{6}\.dump$/', $nome, $m);
        $dia = $m[1];
        $semana = date('o-W', strtotime($dia));
        if (!isset($dias[$dia]) && count($dias) < 7) { $dias[$dia] = true; $semanas[$semana] = 'diario'; continue; }
        if (!isset($dias[$dia]) && !isset($semanas[$semana]) && count(array_keys($semanas, 'semanal')) < 4) { $semanas[$semana] = 'semanal'; continue; }
        $apagar[] = $nome;
    }
    return $apagar;
}

// Incluído pelo teste: só a função acima interessa.
if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;

require dirname(__DIR__) . '/app/Config/Database.php';
use App\Config\Database;

function contarLinhas(PDO $pdo): array {
    $contagem = [];
    foreach ($pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $contagem[$t] = (int)$pdo->query('SELECT count(*) FROM "' . str_replace('"', '""', $t) . '"')->fetchColumn();
    }
    return $contagem;
}
$parar = function (string $motivo): never { fwrite(STDERR, $motivo . "\n"); exit(1); };

Database::usarPapelDeMigracao();
$pdo = Database::getConnection();
$banco = $pdo->query('SELECT current_database()')->fetchColumn();
$pasta = dirname(__DIR__) . '/backups';
if (!is_dir($pasta)) mkdir($pasta);
foreach (['PGHOST' => getenv('DB_HOST') ?: 'localhost', 'PGPORT' => getenv('DB_PORT') ?: '5432',
          'PGUSER' => getenv('DB_USER'), 'PGPASSWORD' => getenv('DB_PASS')] as $k => $v) putenv("$k=$v");

// shortcut: a contagem é tirada logo antes do dump, não no mesmo instante; rode com a clínica fechada. Se der alarme falso, tirar a contagem do próprio dump.
$original = contarLinhas($pdo);
$nome = 'auto_' . $banco . '_' . date('Ymd_His') . '.dump';
passthru('pg_dump -Fc -d ' . escapeshellarg($banco) . ' -f ' . escapeshellarg("$pasta/$nome"), $saida);
if ($saida !== 0 || !is_file("$pasta/$nome") || filesize("$pasta/$nome") === 0) $parar('Backup FALHOU (pg_dump).');
echo "[OK] backup: backups/$nome (" . number_format(filesize("$pasta/$nome") / 1024, 0, ',', '.') . " KB)\n";

foreach (backupsParaApagar(array_map('basename', glob("$pasta/auto_{$banco}_*.dump"))) as $velho) {
    unlink("$pasta/$velho");
    echo "     rotação: apagado $velho\n";
}

if (!in_array('--testar-restauracao', $argv, true)) exit(0);

$temp = 'dentia_restore_teste';
$registrar = function (string $resultado) use ($pasta, $nome): void {
    file_put_contents("$pasta/restauracoes.log", date('Y-m-d H:i') . " $nome $resultado\n", FILE_APPEND);
};
$pdo->exec("DROP DATABASE IF EXISTS $temp");
$pdo->exec("CREATE DATABASE $temp");
passthru('pg_restore --no-owner --no-acl -d ' . $temp . ' ' . escapeshellarg("$pasta/$nome"), $saida);
$restaurado = [];
if ($saida === 0) {
    $r = new PDO('pgsql:host=' . getenv('PGHOST') . ';port=' . getenv('PGPORT') . ";dbname=$temp", getenv('PGUSER'), getenv('PGPASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $restaurado = contarLinhas($r);
    $r = null; // fecha a conexão para o DROP abaixo
}
$pdo->exec("DROP DATABASE IF EXISTS $temp");

if ($saida !== 0) { $registrar('FALHOU: pg_restore devolveu erro'); $parar('Restauração FALHOU (pg_restore).'); }
$diferentes = array_keys(array_diff_assoc($original, $restaurado) + array_diff_assoc($restaurado, $original));
if ($diferentes) {
    $registrar('FALHOU: contagem diferente em ' . implode(', ', $diferentes));
    $parar('Restauração FALHOU: contagem diferente em ' . implode(', ', $diferentes));
}
$registrar('OK: ' . count($restaurado) . ' tabelas, ' . array_sum($restaurado) . ' linhas conferidas');
echo '[OK] restauração conferida: ' . count($restaurado) . ' tabelas, ' . array_sum($restaurado) . " linhas iguais ao banco $banco\n";
