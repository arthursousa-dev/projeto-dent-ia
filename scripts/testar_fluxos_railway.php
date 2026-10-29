<?php
/** Teste de integração com transação global e ROLLBACK (não deixa alterações). */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$_SERVER['REQUEST_METHOD']='GET';
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__).'/includes/functions.php';
$pdo=\App\Config\Database::getConnection();
function ok(bool $val, string $label): void { if (!$val) throw new RuntimeException('FALHOU: '.$label); echo '[OK] '.$label."\n"; }
try {
    echo 'Banco: '.$pdo->query('SELECT current_database()')->fetchColumn()."\n";
    foreach (['pessoa','usuarios','pacientes','dentistas','procedimentos','dentista_procedimentos',
        'disponibilidade_dentista','funcionamento_clinica','sala','agendamentos','pagamento',
        'prontuarios','evolucao_clinica','lancamento_financeiro','categoria_lancamento',
        'vw_pacientes','vw_dentistas','vw_agendamentos','vw_resumo_financeiro_mensal'] as $t) {
        ok((bool)$pdo->query("SELECT to_regclass('public.".$t."')")->fetchColumn(),'estrutura '.$t);
    }
    $owner=$pdo->query("SELECT p.nome FROM usuarios u JOIN pessoa p ON p.id=u.id_pessoa WHERE u.tipo='dono'")->fetchColumn();
    ok($owner==='Arthur Sousa','proprietário Arthur Sousa');
    $pdo->beginTransaction();
    $pac=(int)$pdo->query("SELECT id FROM pacientes WHERE status='ativo' ORDER BY id LIMIT 1")->fetchColumn();
    ok($pac>0,'paciente cadastrado');
    $candidatos=$pdo->query("SELECT d.id AS id_dentista,p.nome AS procedimento,to_char(dd.hora,'HH24:MI') AS hora,
        dd.id_dia_semana FROM dentistas d JOIN disponibilidade_dentista dd ON dd.id_dentista=d.id
        JOIN dentista_procedimentos dp ON dp.id_dentista=d.id
        JOIN procedimentos p ON p.id=dp.id_procedimento
        JOIN funcionamento_clinica fc ON fc.id_dia_semana=dd.id_dia_semana AND fc.id_clinica=1
        WHERE d.status='ativo' AND d.id_sala IS NOT NULL AND dd.hora+p.duracao_min*interval '1 minute'<=fc.fechamento
        ORDER BY d.id,dd.hora LIMIT 40")->fetchAll();
    ok((bool)$candidatos,'disponibilidade de dentista e procedimentos');
    $chosen=null;$day=null;
    for ($i=4;$i<40 && !$chosen;$i++) {
        $dt=(new DateTimeImmutable('today'))->modify('+'.$i.' days');
        foreach ($candidatos as $c) if ((int)$dt->format('w')===(int)$c['id_dia_semana']) {
            $chosen=$c; $day=$dt->format('Y-m-d'); break;
        }
    }
    ok($chosen!==null,'dia futuro com expediente');
    $_SESSION['perfil']='recepcionista';$_SESSION['email']='recepcao@dentai.com';
    $dados=['paciente_id'=>$pac,'dentista'=>(int)$chosen['id_dentista'],'procedimento'=>$chosen['procedimento'],
        'data'=>$day,'horario'=>$chosen['hora'],'obs'=>'Teste automático, revertido ao finalizar'];
    $id=criarAgendamento($dados);
    ok($id>0,'INSERT de agendamento retornou ID');
    $stmt=$pdo->prepare('SELECT id_paciente,data FROM vw_agendamentos WHERE id=:id');
    $stmt->execute(['id'=>$id]);$a=$stmt->fetch();
    ok((int)$a['id_paciente']===$pac && $a['data']===$day,'agendamento aparece na agenda do dia correto');
    try {criarAgendamento($dados);throw new RuntimeException('duplicidade aceita');}
    catch (InvalidArgumentException $e) {ok(true,'horário duplicado bloqueado');}
    try {alterarStatusAgenda($id,'Aguardando');throw new RuntimeException('check-in de consulta futura aceito');}
    catch (InvalidArgumentException $e) {ok(true,'check-in antes do dia da consulta é recusado');}
    // Dentro da transação de teste: traz a consulta para hoje (00:00) para seguir o fluxo do dia.
    $pdo->prepare("UPDATE agendamentos SET data=CURRENT_DATE, hora='00:00' WHERE id=:id")->execute(['id'=>$id]);
    alterarStatusAgenda($id,'Aguardando');
    alterarStatusAgenda($id,'Em atendimento');
    alterarStatusAgenda($id,'Concluída');
    ok((string)$pdo->query('SELECT status::text FROM agendamentos WHERE id='.$id)->fetchColumn()==='Concluída','check-in e conclusão');
    $forma=(int)$pdo->query('SELECT id FROM forma_pagamento ORDER BY id LIMIT 1')->fetchColumn();
    confirmarPagamento($id,$forma);
    ok((bool)$pdo->query('SELECT pago_em IS NOT NULL FROM pagamento WHERE id_agendamento='.$id)->fetchColumn(),'pagamento vinculado');
    $_SESSION['perfil']='dentista';
    $s=$pdo->prepare('SELECT u.email FROM usuarios u JOIN dentistas d ON d.id_pessoa=u.id_pessoa WHERE d.id=:id');
    $s->execute(['id'=>$chosen['id_dentista']]);$_SESSION['email']=$s->fetchColumn();
    $evolucao=registrarEvolucao(['paciente'=>(string)$pac,'procedimento'=>$chosen['procedimento'],'obs'=>'Atendimento de teste']);
    ok($evolucao>0,'prontuário e evolução clínica');
    salvarOdontograma($pac,['11'=>['status'=>'tratado','notas'=>'Teste temporário']]);
    $_SESSION['perfil']='dono';$_SESSION['email']='dono@dentai.com';
    $cat=(int)$pdo->query("SELECT id FROM categoria_lancamento WHERE tipo='Despesa' ORDER BY id LIMIT 1")->fetchColumn();
    $lanc=registrarLancamento(['tipo'=>'Despesa','valor'=>'35.50','descricao'=>'Teste de despesa','data'=>date('Y-m-d'),'id_categoria'=>$cat]);
    ok($lanc>0,'despesa financeira');
    $fat=resumoFinanceiro($pdo,$day);
    ok(isset($fat['mes_atual']['receita_total']),'faturamento baseado em pagamentos');
    echo "[OK] TESTES TRANSACIONAIS PASSARAM.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n");
    $falhou=true;
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack();echo '[OK] ROLLBACK: nenhum dado do teste permaneceu. '."\n"; }
}
if (!empty($falhou)) exit(1);
