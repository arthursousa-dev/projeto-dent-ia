<?php
function rotuloMesFinanceiro(DateTimeImmutable $data): string {
    $meses=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return $meses[(int)$data->format('n')-1].'/'.$data->format('Y');
}

function resumoFinanceiro(PDO $pdo, ?string $data = null): array {
    $data = $data ?? date('Y-m-d');
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d',$data);
    if (!$dt || $dt->format('Y-m-d')!==$data) throw new InvalidArgumentException('Período financeiro inválido.');
    $inicio = $dt->modify('first day of this month')->format('Y-m-d');
    $fim = $dt->modify('first day of next month')->format('Y-m-d');
    $anterior = $dt->modify('first day of last month');
    $stmt = $pdo->prepare('SELECT * FROM vw_resumo_financeiro_mensal WHERE ano=:ano AND mes_num=:mes');
    $stmt->execute(['ano'=>$dt->format('Y'),'mes'=>$dt->format('n')]);
    $m = $stmt->fetch() ?: ['receita'=>0,'despesas'=>0,'procedimentos'=>0,'valor_producao'=>0];
    $stmt->execute(['ano'=>$anterior->format('Y'),'mes'=>$anterior->format('n')]);
    $receitaAnterior = (float)(($stmt->fetch() ?: [])['receita'] ?? 0);
    $receita = (float)$m['receita']; $despesas = (float)$m['despesas'];
    $porDentista = $pdo->prepare("SELECT d.id AS id_dentista, p.nome,
        (SELECT count(*) FROM agendamentos a WHERE a.id_dentista=d.id AND a.status='Concluída' AND a.data>=:inicio AND a.data<:fim) AS procedimentos,
        COALESCE((SELECT sum(pg.valor) FROM pagamento pg JOIN agendamentos a ON a.id=pg.id_agendamento
            WHERE a.id_dentista=d.id AND pg.pago_em>=:de::date AND pg.pago_em<:ate::date),0) AS receita
        FROM dentistas d JOIN pessoa p ON p.id=d.id_pessoa ORDER BY receita DESC,p.nome");
    $porDentista->execute(['inicio'=>$inicio,'fim'=>$fim,'de'=>$inicio,'ate'=>$fim]);
    $stmt = $pdo->prepare("SELECT COALESCE(sum(a.valor),0) FROM agendamentos a LEFT JOIN pagamento pg ON pg.id_agendamento=a.id
        WHERE a.status<>'Cancelado' AND a.data>=:inicio AND a.data<:fim AND pg.pago_em IS NULL");
    $stmt->execute(['inicio'=>$inicio,'fim'=>$fim]);
    $aReceber=(float)$stmt->fetchColumn();
    $stmt=$pdo->prepare("SELECT pl.nome, SUM(pg.valor) AS receita FROM pagamento pg
        JOIN agendamentos a ON a.id=pg.id_agendamento JOIN pacientes pac ON pac.id=a.id_paciente
        JOIN plano_saude pl ON pl.id=pac.id_plano
        WHERE pg.pago_em>=:inicio::date AND pg.pago_em<:fim::date GROUP BY pl.nome ORDER BY receita DESC,pl.nome");
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
    for ($i=5; $i>=0; $i--) {
        $mes=$dt->modify('first day of this month')->modify("-$i months");
        $stmt=$pdo->prepare('SELECT * FROM vw_resumo_financeiro_mensal WHERE ano=:ano AND mes_num=:mes');
        $stmt->execute(['ano'=>$mes->format('Y'),'mes'=>$mes->format('n')]);
        $linha=$stmt->fetch() ?: ['receita'=>0,'despesas'=>0,'procedimentos'=>0];
        $historico[]=['ano'=>(int)$mes->format('Y'),'mes_num'=>(int)$mes->format('n'),
            'mes'=>rotuloMesFinanceiro($mes),'receita'=>(float)$linha['receita'],
            'despesas'=>(float)$linha['despesas'],'procedimentos'=>(int)$linha['procedimentos']];
    }
    $stmt=$pdo->prepare('SELECT * FROM vw_movimentos_financeiros WHERE data>=:inicio AND data<:fim ORDER BY data,id');
    $stmt->execute(['inicio'=>$inicio,'fim'=>$fim]);
    return ['mes_atual'=>$mesAtual,'historico_mensal'=>$historico,'transacoes'=>$stmt->fetchAll()];
}

function valorMonetario(string $valor): string {
    $valor=trim($valor);
    if (str_contains($valor,',')) $valor=str_replace(',','.',str_replace('.','',$valor));
    if (!preg_match('/^\d+(\.\d{1,2})?$/',$valor)) throw new InvalidArgumentException('Valor monetário inválido.');
    return number_format((float)$valor,2,'.','');
}
