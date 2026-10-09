<?php
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
date_default_timezone_set('America/Cuiaba');
require_once __DIR__.'/../includes/db.php';
$pdo=\App\Config\Database::getConnection();
if (!$pdo->query("SELECT to_regclass('public.vw_resumo_financeiro_mensal')")->fetchColumn()) {
    fwrite(STDERR,"Estrutura financeira ausente. Veja as instruções de instalação e migração no README.\n");exit(1);
}
try {
    $mes=$argv[1] ?? date('Y-m');
    $resumo=resumoFinanceiro($pdo,$mes.'-01');
    $contagem=$pdo->query("SELECT (SELECT count(*) FROM pagamento WHERE pago_em IS NOT NULL) AS pagamentos_confirmados,
        (SELECT count(*) FROM lancamento_financeiro) AS lancamentos,
        (SELECT max(pago_em)::date FROM pagamento) AS ultimo_recebimento")->fetch();
    echo json_encode(['periodo'=>$mes,'base'=>$contagem,'indicadores'=>$resumo['mes_atual'],'historico'=>$resumo['historico_mensal']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
    if (!$resumo['transacoes']) echo "Não há recebimentos ou despesas no mês consultado. Escolha um mês com movimento ou registre operações.\n";
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1); }
