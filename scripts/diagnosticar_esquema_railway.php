<?php
// CLI only. Read-only: does not alter Railway database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/app/Config/Database.php';
try {
    $db = \App\Config\Database::getConnection();
    echo "Banco: " . $db->query('SELECT current_database()')->fetchColumn() . "\n";
    $nomes = ['agendamentos','dentistas','pacientes','procedimentos','dentista_procedimentos','pessoa','sala','disponibilidade_dentista','funcionamento_clinica','vw_agendamentos','vw_pacientes'];
    $sql = "SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :nome ORDER BY ordinal_position";
    $stmt = $db->prepare($sql);
    foreach ($nomes as $nome) {
        echo "\n=== {$nome} ===\n";
        $stmt->execute(['nome'=>$nome]); $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$cols) { echo "NÃO ENCONTRADA ou SEM COLUNAS\n"; continue; }
        foreach ($cols as $col) echo sprintf("%-28s %-22s NULL:%-3s DEFAULT:%s\n", $col['column_name'], $col['data_type'], $col['is_nullable'], $col['column_default'] ?? '-');
    }
    echo "\n=== RESTRIÇÕES DE AGENDAMENTOS ===\n";
    $constraints = $db->query("SELECT conname, pg_get_constraintdef(oid) AS definicao FROM pg_constraint WHERE conrelid = to_regclass('agendamentos') ORDER BY conname")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($constraints as $c) echo $c['conname'].' = '.$c['definicao']."\n";
    echo "\nDiagnóstico somente leitura concluído. Não foram feitas gravações.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] '.$e->getCode().' - '.$e->getMessage()."\n"); exit(1);
}
