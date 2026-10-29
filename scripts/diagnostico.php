<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // só pelo terminal: nunca pela web
/** Executar: php scripts/diagnostico.php */
require_once __DIR__ . '/../app/Config/Database.php';
$falhas = 0;
function checar(string $rotulo, callable $teste): void {
    global $falhas;
    try { $resultado=$teste(); if (!$resultado) throw new RuntimeException('Nenhum registro retornado');
        echo "[OK] $rotulo: ".(is_scalar($resultado) ? $resultado : 'ok').PHP_EOL;
    } catch (Throwable $e) { $falhas++; echo "[ERRO] $rotulo: {$e->getMessage()}".PHP_EOL; }
}
checar('PHP 8.1+', fn()=>version_compare(PHP_VERSION,'8.1','>=') ? PHP_VERSION : false);
checar('PDO PostgreSQL', fn()=>extension_loaded('pdo_pgsql'));
checar('Conexão PostgreSQL', fn()=>\App\Config\Database::getConnection()->query('SELECT version()')->fetchColumn());
$pdo = null;
try { $pdo=\App\Config\Database::getConnection(); } catch (Throwable $e) { echo "Diagnóstico interrompido: {$e->getMessage()}".PHP_EOL;exit(1); }
foreach (['pessoa','usuarios','pacientes','dentistas','procedimentos','agendamentos','prontuarios','evolucao_clinica','pagamento','lancamento_financeiro'] as $tabela) {
    checar("Tabela $tabela", fn()=> $pdo->query('SELECT count(*) FROM '.$tabela)->fetchColumn() !== false);
}
foreach (['vw_pacientes','vw_agendamentos','vw_resumo_financeiro_mensal','vw_movimentos_financeiros'] as $view) {
    checar("View $view", fn()=> $pdo->query('SELECT count(*) FROM '.$view)->fetchColumn() !== false);
}
checar('Relacionamento agenda/paciente/dentista', fn()=> $pdo->query('SELECT count(*) FROM agendamentos a JOIN pacientes p ON p.id=a.id_paciente JOIN dentistas d ON d.id=a.id_dentista')->fetchColumn() !== false);
checar('Movimentos / faturamento', fn()=> $pdo->query('SELECT coalesce(sum(receita),0) FROM vw_resumo_financeiro_mensal')->fetchColumn() !== false);
checar('Contas de demonstração', fn()=> (int)$pdo->query("SELECT count(*) FROM usuarios WHERE status='ativo'")->fetchColumn() >= 4);
echo $falhas ? "\nFalhas encontradas: $falhas\n" : "\nVerificações concluídas sem erros de consulta. Faça também o teste funcional pelo navegador.\n";
exit($falhas ? 1 : 0);
