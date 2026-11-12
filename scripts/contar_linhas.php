<?php
// Só leitura: imprime "tabela<TAB>linhas" de todas as tabelas do schema public.
// Uso: php scripts/contar_linhas.php            (banco do .env)
//      DB_NAME=dentia_teste php scripts/contar_linhas.php
require dirname(__DIR__) . '/app/Config/Database.php';

$pdo = App\Config\Database::getConnection();
$pdo->exec('SET default_transaction_read_only = on');
echo '# banco: ' . $pdo->query('SELECT current_database()')->fetchColumn() . PHP_EOL;
$tabelas = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tabelas as $t) {
    // Nome vem do catálogo do Postgres, não do usuário; ainda assim vai entre aspas.
    $n = $pdo->query('SELECT count(*) FROM "' . str_replace('"', '""', $t) . '"')->fetchColumn();
    echo $t . "\t" . $n . PHP_EOL;
}
