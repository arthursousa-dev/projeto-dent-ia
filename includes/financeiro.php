<?php
require_once __DIR__ . '/operacoes.php'; // consultasAReceber usa listaSql e STATUS_SEM_COBRANCA
function rotuloMesFinanceiro(DateTimeImmutable $data): string {
    $meses=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return $meses[(int)$data->format('n')-1].'/'.$data->format('Y');
}

/** Valor em reais no padrão brasileiro, com o sinal antes do símbolo: -R$ 1.175,00. */
function moeda(float $valor): string {
    return ($valor<0 ? '-' : '').'R$ '.number_format(abs($valor),2,',','.');
}

function resumoFinanceiro(PDO $pdo, ?string $data = null, int $mesesHistorico = 6): array {
    if ($mesesHistorico<1 || $mesesHistorico>24) throw new InvalidArgumentException('Histórico deve ter de 1 a 24 meses.');
    $data = $data ?? date('Y-m-d');
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d',$data);
    // A partir de 2000: o histórico volta 5 meses e o banco não aceita datas antes do ano 1.
    if (!$dt || $dt->format('Y-m-d')!==$data || $data<'2000-01-01' || $data>'2100-12-31') throw new InvalidArgumentException('Período financeiro inválido.');
    $inicio = $dt->modify('first day of this month')->format('Y-m-d');
    $fim = $dt->modify('first day of next month')->format('Y-m-d');
    $anterior = $dt->modify('first day of last month');
    // A view agrega as tabelas inteiras; avaliá-la uma vez para todos os meses do histórico.
    $stmt = $pdo->prepare("SELECT ano||'-'||mes_num AS chave, * FROM vw_resumo_financeiro_mensal
        WHERE make_date(ano,mes_num,1) BETWEEN CAST(:de AS date) AND CAST(:ate AS date)");
    $stmt->execute(['de'=>$dt->modify('first day of this month')->modify('-'.max(1,$mesesHistorico-1).' months')->format('Y-m-d'),'ate'=>$inicio]);
    $meses = $stmt->fetchAll(PDO::FETCH_UNIQUE);
    $m = $meses[$dt->format('Y-n')] ?? ['receita'=>0,'despesas'=>0,'procedimentos'=>0,'valor_producao'=>0];
    $receitaAnterior = (float)($meses[$anterior->format('Y-n')]['receita'] ?? 0);
    $receita = (float)$m['receita']; $despesas = (float)$m['despesas'];
    $porDentista = $pdo->prepare("SELECT d.id AS id_dentista, p.nome,
        (SELECT count(*) FROM agendamentos a WHERE a.id_dentista=d.id AND a.status='Concluída' AND a.data>=:inicio AND a.data<:fim) AS procedimentos,
        COALESCE((SELECT sum(pg.valor) FROM (SELECT id_agendamento,valor,pago_em FROM pagamento WHERE pago_em IS NOT NULL UNION ALL SELECT id_agendamento,valor,pago_em FROM pagamento_estorno UNION ALL SELECT id_agendamento,-valor,estornado_em FROM pagamento_estorno) pg JOIN agendamentos a ON a.id=pg.id_agendamento
            WHERE a.id_dentista=d.id AND pg.pago_em IS NOT NULL AND pg.pago_em>=CAST(:de AS date) AND pg.pago_em<CAST(:ate AS date)),0) AS receita
        FROM dentistas d JOIN pessoa p ON p.id=d.id_pessoa ORDER BY receita DESC,p.nome");
    $porDentista->execute(['inicio'=>$inicio,'fim'=>$fim,'de'=>$inicio,'ate'=>$fim]);
    $aReceber=array_sum(array_map('floatval',array_column(consultasAReceber($pdo,$inicio,$fim),'valor')));
    $stmt=$pdo->prepare("SELECT pl.nome, SUM(pg.valor) AS receita FROM (SELECT id_agendamento,valor,pago_em FROM pagamento WHERE pago_em IS NOT NULL UNION ALL SELECT id_agendamento,valor,pago_em FROM pagamento_estorno UNION ALL SELECT id_agendamento,-valor,estornado_em FROM pagamento_estorno) pg
        JOIN agendamentos a ON a.id=pg.id_agendamento JOIN pacientes pac ON pac.id=a.id_paciente
        JOIN plano_saude pl ON pl.id=pac.id_plano
        WHERE pg.pago_em IS NOT NULL AND pg.pago_em>=CAST(:inicio AS date) AND pg.pago_em<CAST(:fim AS date) GROUP BY pl.nome ORDER BY receita DESC,pl.nome");
    $stmt->execute(['inicio'=>$inicio,'fim'=>$fim]);
    $porPlano=[];
    foreach ($stmt->fetchAll() as $plano) $porPlano[$plano['nome']]=(float)$plano['receita'];
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM lancamento_financeiro
        WHERE tipo='Receita' AND data>=:inicio AND data<:fim");
    $stmt->execute(['inicio'=>$inicio,'fim'=>$fim]);
    $avulsas=(float)$stmt->fetchColumn();
    $mesAtual = [
        'mes'=>rotuloMesFinanceiro($dt), 'receita_total'=>$receita, 'despesas_total'=>$despesas,
        'lucro'=>$receita-$despesas, 'procedimentos_realizados'=>(int)$m['procedimentos'],
        'ticket_medio'=>$m['procedimentos'] ? (float)$m['valor_producao']/(int)$m['procedimentos'] : 0,
        'crescimento_percentual'=>$receitaAnterior>0 ? ($receita-$receitaAnterior)/$receitaAnterior*100 : 0,
        'a_receber'=>$aReceber, 'por_dentista'=>$porDentista->fetchAll(), 'por_plano'=>$porPlano, 'receitas_avulsas'=>$avulsas,
    ];
    $historico=[];
    for ($i=$mesesHistorico-1; $i>=0; $i--) {
        $mes=$dt->modify('first day of this month')->modify("-$i months");
        $linha=$meses[$mes->format('Y-n')] ?? ['receita'=>0,'despesas'=>0,'procedimentos'=>0];
        $historico[]=['ano'=>(int)$mes->format('Y'),'mes_num'=>(int)$mes->format('n'),
            'mes'=>rotuloMesFinanceiro($mes),'receita'=>(float)$linha['receita'],
            'despesas'=>(float)$linha['despesas'],'procedimentos'=>(int)$linha['procedimentos']];
    }
    $stmt=$pdo->prepare('SELECT * FROM vw_movimentos_financeiros WHERE data>=:inicio AND data<:fim ORDER BY data,id');
    $stmt->execute(['inicio'=>$inicio,'fim'=>$fim]);
    return ['mes_atual'=>$mesAtual,'historico_mensal'=>$historico,'transacoes'=>$stmt->fetchAll()];
}

/** Consultas do período que geram cobrança e ainda não foram pagas: regra única do "a receber" (resumo e lista). */
function consultasAReceber(PDO $pdo, string $inicio, string $fim): array {
    $stmt=$pdo->prepare("SELECT * FROM vw_agendamentos WHERE NOT pago AND status NOT IN (".listaSql(STATUS_SEM_COBRANCA).")
        AND data>=:inicio AND data<:fim ORDER BY data,hora,id");
    $stmt->execute(['inicio'=>$inicio,'fim'=>$fim]);
    return $stmt->fetchAll();
}

/** Estornos feitos no período: o gestor vê quem estornou, quando, o valor e o motivo. */
function estornosDoPeriodo(PDO $pdo, string $inicio, string $fim): array {
    $stmt=$pdo->prepare("SELECT e.id_agendamento, pp.nome AS paciente, e.valor, to_char(e.pago_em,'DD/MM/YYYY') AS pago_em,
        to_char(e.estornado_em,'DD/MM/YYYY HH24:MI') AS estornado_em, pu.nome AS usuario, e.motivo
        FROM pagamento_estorno e JOIN agendamentos a ON a.id=e.id_agendamento
        JOIN pacientes pac ON pac.id=a.id_paciente JOIN pessoa pp ON pp.id=pac.id_pessoa
        JOIN usuarios u ON u.id=e.id_usuario JOIN pessoa pu ON pu.id=u.id_pessoa
        WHERE e.estornado_em>=CAST(:inicio AS date) AND e.estornado_em<CAST(:fim AS date) ORDER BY e.estornado_em");
    $stmt->execute(['inicio'=>$inicio,'fim'=>$fim]);
    return $stmt->fetchAll();
}

/** Valor em reais digitado pelo usuário: aceita "1500", "1.500", "1.500,50", "1500,5" e "1500.50". */
function valorMonetario(string $valor): string {
    $valor=str_replace(['R$',' '],'',trim($valor));
    if (str_contains($valor,',') || preg_match('/^\d{1,3}(\.\d{3})+$/',$valor)) $valor=str_replace(',','.',str_replace('.','',$valor));
    if (!preg_match('/^\d+(\.\d{1,2})?$/',$valor)) throw new InvalidArgumentException('Valor monetário inválido. Use, por exemplo, 150,00.');
    if ((float)$valor>999999.99) throw new InvalidArgumentException('Valor acima do máximo permitido (R$ 999.999,99).');
    return number_format((float)$valor,2,'.','');
}
