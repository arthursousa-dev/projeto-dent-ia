<?php
/** Installer apenas CLI e apenas DEMO. Executa reset+schema+seed atomicamente. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/app/Config/Database.php';
use App\Config\Database;
if (($argv[1] ?? '') !== '--confirmar-reset-demo' || getenv('DENTIA_PERMITIR_RESET') !== 'SIM') {
    fwrite(STDERR,"BLOQUEADO: configure DENTIA_PERMITIR_RESET=SIM e execute php scripts/instalar_demo_railway.php --confirmar-reset-demo\n");
    exit(2);
}
$pdo=Database::getConnection();
$identidade=$pdo->query('SELECT current_database() AS db,current_user AS usuario,inet_server_addr()::text AS host')->fetch();
fwrite(STDOUT,"Banco escolhido: ".json_encode($identidade,JSON_UNESCAPED_UNICODE)."\n");
fwrite(STDOUT,"ATENÇÃO: serão descartadas TODAS as tabelas do schema public desse banco!\n");
if (!isset($argv[2]) || $argv[2] !== '--apagar-mesmo') {
    fwrite(STDERR,"Adicione --apagar-mesmo no final apenas depois de verificar o banco acima.\n");
    exit(3);
}
$root=dirname(__DIR__);
try {
    $pdo->beginTransaction();
    $pdo->exec('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public; GRANT USAGE, CREATE ON SCHEMA public TO CURRENT_USER;');
    foreach (['database/schema.sql','database/seed.sql'] as $path) {
        $conteudo=file_get_contents($root.'/'.$path);
        if ($conteudo===false) throw new RuntimeException('Não foi possível abrir '.$path);
        $conteudo=preg_replace('/^\s*BEGIN;\s*/mi','',$conteudo,1);
        $conteudo=preg_replace('/\s*COMMIT;\s*$/mi','',$conteudo,1);
        $pdo->exec($conteudo);
        echo '[OK] '.$path."\n";
    }
    $stmt=$pdo->query("SELECT p.nome FROM usuarios u JOIN pessoa p ON p.id=u.id_pessoa WHERE u.tipo='dono' LIMIT 1");
    $nome=$stmt->fetchColumn();
    if ($nome !== 'Arthur Sousa') throw new RuntimeException('Proprietário divergente: '.$nome);
    $pdo->query('SELECT * FROM vw_agendamentos LIMIT 1')->fetch();
    $pdo->query('SELECT * FROM vw_resumo_financeiro_mensal LIMIT 1')->fetch();
    $pdo->commit();
    echo "[OK] INSTALAÇÃO CONCLUÍDA. Dono: Arthur Sousa.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'[ERRO] Instalador revertido (rollback): '.$e->getMessage()."\n");
    exit(1);
}
