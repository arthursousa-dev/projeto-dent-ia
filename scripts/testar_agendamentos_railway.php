<?php
// CLI only. Validates connection, schema and transactional INSERT + ROLLBACK.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/app/Config/Database.php';
try {
    $db=\App\Config\Database::getConnection();
    echo "PostgreSQL conectado: ".$db->query("SELECT current_database() || ' / ' || current_user")->fetchColumn()."\n";
    foreach (['agendamentos','pacientes','dentistas','procedimentos','dentista_procedimentos','disponibilidade_dentista','funcionamento_clinica','vw_agendamentos','vw_pacientes'] as $table) {
        $exists=$db->prepare('SELECT to_regclass(:nome)');$exists->execute(['nome'=>'public.'.$table]);
        echo ($exists->fetchColumn() ? '[OK] ' : '[FALTA] ').$table."\n";
    }
    foreach (['pacientes','dentistas','procedimentos','agendamentos'] as $table) {
        echo $table.': '.$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()." registros\n";
    }
    echo "\n[ATENÇÃO] O banco conectado pode ter esquema diferente do esperado pelo PHP.\n";
    echo "Execute php scripts/diagnosticar_esquema_railway.php e compare as colunas.\n";
    echo "Nenhum INSERT foi realizado neste diagnóstico.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'[ERRO] SQLSTATE/código: '.$e->getCode().' - '.$e->getMessage()."\n");
    exit(1);
}
