<?php
/** Teste das regras de remarcação, limites da clínica, contas novas e expiração de sessão. Tudo com ROLLBACK. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ob_start(); // sem buffer, o primeiro echo "envia cabeçalhos" e o teste de sessão não consegue trocar o ID
$_SERVER['REQUEST_METHOD']='GET';
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__).'/includes/functions.php';
$pdo=\App\Config\Database::getConnection();
function ok(bool $val, string $label): void { if (!$val) throw new RuntimeException('FALHOU: '.$label); echo '[OK] '.$label."\n"; }
function falha(callable $f, string $label): void {
    try { $f(); } catch (InvalidArgumentException|RuntimeException $e) { ok(true, $label.' ('.$e->getMessage().')'); return; }
    throw new RuntimeException('FALHOU: '.$label.' (operação foi aceita)');
}
/** CPF fictício com dígitos verificadores corretos (o banco valida). */
function cpfTeste(): string {
    $n=array_map('intval',str_split(str_pad((string)random_int(100000000,999999999),9,'0',STR_PAD_LEFT)));
    foreach ([10,11] as $peso) {
        $s=0; foreach ($n as $i=>$d) $s+=$d*($peso-$i);
        $n[]=(($s*10)%11)%10;
    }
    return implode('',$n);
}
function como(string $perfil, string $email): void { $_SESSION['perfil']=$perfil; $_SESSION['email']=$email; }

/** Até $quantos horários livres e válidos (dentista habilitado, expediente) a partir de 4 dias. */
function horariosParaTeste(PDO $pdo, int $quantos): array {
    $cands=$pdo->query("SELECT d.id AS dentista,p.nome AS procedimento,to_char(dd.hora,'HH24:MI') AS hora,dd.id_dia_semana AS dia
        FROM dentistas d JOIN disponibilidade_dentista dd ON dd.id_dentista=d.id
        JOIN dentista_procedimentos dp ON dp.id_dentista=d.id JOIN procedimentos p ON p.id=dp.id_procedimento
        JOIN funcionamento_clinica fc ON fc.id_dia_semana=dd.id_dia_semana AND fc.id_clinica=1
        WHERE d.status='ativo' AND d.id_sala IS NOT NULL AND dd.hora+p.duracao_min*interval '1 minute'<=fc.fechamento
        ORDER BY p.duracao_min,d.id,dd.hora")->fetchAll();
    $achados=[];
    for ($i=4; $i<60 && count($achados)<$quantos; $i++) {
        $dia=(new DateTimeImmutable('today'))->modify("+$i days");
        foreach ($cands as $c) if ((int)$dia->format('w')===(int)$c['dia']) { $achados[]=$c+['data'=>$dia->format('Y-m-d')]; break; }
    }
    return $achados;
}

// Retrato de todas as consultas (inclui as passadas sem desfecho) para provar que o teste não deixa rastro.
$retrato=fn()=>$pdo->query("SELECT count(*) FILTER (WHERE data<CURRENT_DATE AND status IN ('Agendado','Confirmado'))||'|'||
    (SELECT count(*) FROM historico_status_agendamento)||'|'||md5(string_agg(id||status::text||data||hora||valor,',' ORDER BY id)) FROM agendamentos")->fetchColumn();
$antes=$retrato();
try {
    $pdo->beginTransaction();
    $pac=(int)$pdo->query("SELECT id FROM pacientes WHERE status='ativo' ORDER BY id LIMIT 1")->fetchColumn();
    [$s1,$s2]=horariosParaTeste($pdo,2);
    ok($s1 && $s2, 'dois horários livres para os testes');

    // ---- remarcação ----
    como('recepcionista','recepcao@dentai.com');
    $orig=criarAgendamento(['paciente_id'=>$pac,'dentista'=>$s1['dentista'],'procedimento'=>$s1['procedimento'],
        'data'=>$s1['data'],'horario'=>$s1['hora'],'valor'=>'123,45','obs'=>'teste remarcação']);
    $s2proc=$pdo->prepare('SELECT 1 FROM dentista_procedimentos dp JOIN procedimentos p ON p.id=dp.id_procedimento WHERE dp.id_dentista=:d AND p.nome=:n');
    $s2proc->execute(['d'=>$s2['dentista'],'n'=>$s1['procedimento']]);
    $destino=$s2proc->fetchColumn() ? $s2 : $s1+['data'=>$s2['data']];
    $novo=reagendarAgendamento($orig,['dentista'=>$destino['dentista'],'data'=>$destino['data'],'horario'=>$destino['hora']]);
    $st=$pdo->prepare('SELECT status::text FROM agendamentos WHERE id=:id');
    $st->execute(['id'=>$orig]); ok($st->fetchColumn()==='Cancelado','remarcar cancela a consulta original');
    $n=$pdo->prepare('SELECT id_paciente,valor,observacoes FROM agendamentos WHERE id=:id'); $n->execute(['id'=>$novo]); $n=$n->fetch();
    ok((int)$n['id_paciente']===$pac && (float)$n['valor']===123.45 && str_contains($n['observacoes'],'#'.$orig),
        'nova consulta mantém paciente, valor combinado e referência à original');
    falha(fn()=>reagendarAgendamento($orig,['data'=>$s2['data'],'horario'=>$s2['hora']]),'consulta cancelada não pode ser remarcada');
    // horário inválido: a remarcação falha inteira e a consulta continua ativa
    falha(fn()=>reagendarAgendamento($novo,['data'=>$destino['data'],'horario'=>'03:00']),'remarcação para horário fora do expediente é recusada');
    $st->execute(['id'=>$novo]); ok($st->fetchColumn()==='Agendado','após remarcação recusada a consulta segue agendada');
    $forma=(int)$pdo->query('SELECT id FROM forma_pagamento ORDER BY id LIMIT 1')->fetchColumn();
    confirmarPagamento($novo,$forma);
    falha(fn()=>reagendarAgendamento($novo,['data'=>$s1['data'],'horario'=>$s1['hora']]),'consulta paga não pode ser remarcada');
    como('cliente','cliente@gmail.com');
    falha(fn()=>reagendarAgendamento($novo,['data'=>$s1['data'],'horario'=>$s1['hora']]),'paciente não usa a remarcação da recepção');

    // ---- limites da clínica ----
    como('recepcionista','recepcao@dentai.com');
    // o dia da consulta remarcada tem ao menos uma consulta ativa; limite = total atual
    $q=$pdo->prepare("SELECT count(*) FROM agendamentos WHERE data=:d AND status<>'Cancelado'"); $q->execute(['d'=>$destino['data']]);
    $total=(int)$q->fetchColumn(); ok($total>=1,'dia de teste com consulta ativa');
    $pdo->prepare('UPDATE clinica SET limite_agendamentos_dia=:l WHERE id=1')->execute(['l'=>$total]);
    falha(fn()=>criarAgendamento(['paciente_id'=>$pac,'dentista'=>$destino['dentista'],'procedimento'=>$s1['procedimento'],
        'data'=>$destino['data'],'horario'=>'08:00']),'limite de consultas por dia é aplicado');
    $pdo->prepare('UPDATE clinica SET limite_agendamentos_dia=500, antecedencia_min_dias=30 WHERE id=1')->execute();
    como('cliente','cliente@gmail.com');
    falha(fn()=>criarAgendamento(['procedimento'=>$s1['procedimento'],'dentista'=>$s1['dentista'],
        'data'=>$s1['data'],'horario'=>$s1['hora']]),'antecedência mínima vale para o paciente');
    ok(dataMinimaPaciente($pdo)===date('Y-m-d',strtotime('+30 days')),'data mínima do portal segue a configuração');

    // ---- faltas ----
    como('recepcionista','recepcao@dentai.com');
    $futura=criarAgendamento(['paciente_id'=>$pac,'dentista'=>$s1['dentista'],'procedimento'=>$s1['procedimento'],'data'=>$s1['data'],'horario'=>$s1['hora']]);
    falha(fn()=>alterarStatusAgenda($futura,'Faltou'),'falta não é registrada antes do horário da consulta');
    $passada=(int)$pdo->query("SELECT a.id FROM agendamentos a LEFT JOIN pagamento pg ON pg.id_agendamento=a.id AND pg.pago_em IS NOT NULL
        WHERE a.status IN ('Agendado','Confirmado') AND a.data<CURRENT_DATE AND pg.id IS NULL ORDER BY a.data DESC LIMIT 1")->fetchColumn();
    ok($passada>0,'consulta passada sem desfecho para o teste');
    $dentistaPassada=$pdo->prepare('SELECT u.email FROM agendamentos a JOIN dentistas d ON d.id=a.id_dentista JOIN usuarios u ON u.id_pessoa=d.id_pessoa WHERE a.id=:id');
    $dentistaPassada->execute(['id'=>$passada]);
    como('dentista',(string)$dentistaPassada->fetchColumn());
    falha(fn()=>alterarStatusAgenda($passada,'Faltou'),'dentista não registra falta');
    como('recepcionista','recepcao@dentai.com');
    alterarStatusAgenda($passada,'Faltou');
    $st->execute(['id'=>$passada]); ok($st->fetchColumn()==='Faltou','recepção registra falta em consulta passada');
    falha(fn()=>confirmarPagamento($passada,$forma),'consulta com falta não recebe pagamento');
    falha(fn()=>alterarStatusAgenda($passada,'Aguardando'),'consulta com falta não volta para check-in');
    $hist=$pdo->prepare('SELECT status_anterior::text AS de,status_novo::text AS para,id_usuario FROM historico_status_agendamento WHERE id_agendamento=:id ORDER BY id');
    $hist->execute(['id'=>$passada]); $linhas=$hist->fetchAll();
    $idRecepcao=(int)usuarioSessao()['id'];
    ok(count($linhas)===1 && $linhas[0]['para']==='Faltou' && (int)$linhas[0]['id_usuario']===$idRecepcao,'falta grava histórico com quem registrou');

    // ---- regra de horário com relógio controlado (fuso da clínica) ----
    $tz=new DateTimeZone('America/Cuiaba');
    ok(!faltaPermitida('2026-10-27','09:00',new DateTimeImmutable('2026-10-27 08:59:59',$tz)),'1 segundo antes do horário: falta indisponível');
    ok(faltaPermitida('2026-10-27','09:00:00',new DateTimeImmutable('2026-10-27 09:00:00',$tz)),'exatamente no horário: falta disponível');
    ok(faltaPermitida('2026-10-27','09:00',new DateTimeImmutable('2026-10-27 09:01',$tz)),'depois do horário: falta disponível');
    ok(!faltaPermitida('2026-10-28','00:00',new DateTimeImmutable('2026-10-27 23:59',$tz)),'virada do dia respeitada');
    ok(date_default_timezone_get()==='America/Cuiaba' && $pdo->query('SHOW TIME ZONE')->fetchColumn()==='America/Cuiaba'
        && $pdo->query('SELECT CURRENT_DATE::text')->fetchColumn()===date('Y-m-d'),'PHP e PostgreSQL usam o mesmo fuso e a mesma data');

    // ---- desfazer falta ----
    $origem=$linhas[0]['de'];
    $dentistaPassada->execute(['id'=>$passada]); como('dentista',(string)$dentistaPassada->fetchColumn());
    falha(fn()=>corrigirFalta($passada),'dentista não desfaz falta');
    como('dono','dono@dentai.com');
    falha(fn()=>corrigirFalta($passada),'dono não desfaz falta pela recepção');
    como('cliente','cliente@gmail.com');
    falha(fn()=>corrigirFalta($passada),'paciente não desfaz falta');
    como('recepcionista','recepcao@dentai.com');
    ok(corrigirFalta($passada)===$origem,'recepção desfaz a falta e a consulta volta ao status anterior ('.$origem.')');
    $st->execute(['id'=>$passada]); ok($st->fetchColumn()===$origem,'status corrigido gravado no banco');
    $hist->execute(['id'=>$passada]); $linhas=$hist->fetchAll();
    ok(count($linhas)===2 && $linhas[1]['de']==='Faltou' && $linhas[1]['para']===$origem && (int)$linhas[1]['id_usuario']===$idRecepcao,
        'correção entra no histórico sem apagar o registro da falta');
    falha(fn()=>corrigirFalta($passada),'segunda correção da mesma falta é recusada');
    falha(fn()=>corrigirFalta($futura),'correção em consulta sem falta é recusada');

    // ---- evolução não dá como concluída consulta antiga sem check-in ----
    $antiga=$pdo->query("SELECT a.id,a.id_paciente,a.id_dentista,p.nome AS procedimento,u.email FROM agendamentos a
        JOIN procedimentos p ON p.id=a.id_procedimento JOIN dentistas d ON d.id=a.id_dentista JOIN usuarios u ON u.id_pessoa=d.id_pessoa
        WHERE a.status IN ('Agendado','Confirmado') AND a.data<CURRENT_DATE AND a.id<>$passada
          AND NOT EXISTS (SELECT 1 FROM agendamentos b WHERE b.id_paciente=a.id_paciente AND b.id_dentista=a.id_dentista
              AND b.id_procedimento=a.id_procedimento AND b.data=CURRENT_DATE)
        ORDER BY a.data DESC LIMIT 1")->fetch();
    ok((bool)$antiga,'consulta antiga sem desfecho para o teste de evolução');
    como('dentista',$antiga['email']);
    registrarEvolucao(['paciente'=>(string)$antiga['id_paciente'],'procedimento'=>$antiga['procedimento'],'obs'=>'Evolução de teste (rollback)']);
    $st->execute(['id'=>$antiga['id']]); ok(in_array($st->fetchColumn(),['Agendado','Confirmado'],true),'evolução avulsa não conclui consulta de outro dia sem check-in');

    // ---- contas novas não usam senha padrão ----
    como('recepcionista','recepcao@dentai.com');
    $cpf=cpfTeste(); $cpfDentista=cpfTeste();
    $novoPac=salvarCadastroPaciente(['nome'=>'Paciente Teste Rollback','cpf'=>$cpf,'email'=>'teste.rollback.'.$cpf.'@exemplo.com','telefone'=>'67999990000']);
    $u=$pdo->prepare("SELECT u.tipo::text,u.senha_hash FROM usuarios u JOIN pacientes p ON p.id_pessoa=u.id_pessoa WHERE p.id=:id");
    $u->execute(['id'=>$novoPac]); $u=$u->fetch();
    ok($u && $u['tipo']==='cliente','paciente cadastrado com e-mail recebe conta do portal');
    ok(!password_verify('123456',$u['senha_hash']),'conta nova do paciente não aceita senha padrão');
    como('dono','dono@dentai.com');
    $proc=$pdo->query('SELECT nome FROM procedimentos ORDER BY id LIMIT 1')->fetchColumn();
    $dent=cadastrarDentista(['nome'=>'Dentista Teste Rollback','cpf'=>$cpfDentista,'email'=>'dent.rollback.'.$cpf.'@exemplo.com',
        'cro'=>'CRO-MS 99'.substr($cpf,-4),'especialidade'=>'Clínico Geral','admissao'=>'','procedimentos'=>[$proc]]);
    $h=$pdo->prepare('SELECT u.senha_hash FROM usuarios u JOIN dentistas d ON d.id_pessoa=u.id_pessoa WHERE d.id=:id');
    $h->execute(['id'=>$dent]);
    ok(!password_verify('123456',(string)$h->fetchColumn()),'dentista novo não recebe a senha 123456');
} catch (Throwable $e) {
    fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n");
    $falhou=true;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
try {
    ok($retrato()===$antes,'ROLLBACK: todas as consultas (incluindo as '.explode('|',$antes)[0].' passadas sem desfecho) e o histórico ficaram como antes');
} catch (Throwable $e) { fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n"); $falhou=true; }

// ---- expiração de sessão (sem banco) ----
try {
    session_write_close();
    $id=session_create_id(); session_id($id); session_start();
    $_SESSION=['perfil'=>'dono','email'=>'dono@dentai.com','ultima_atividade'=>time()-3601];
    session_write_close(); session_id($id);
    require dirname(__DIR__).'/includes/bootstrap_sessao.php';
    ok(empty($_SESSION['perfil']) && !empty($_SESSION['sessao_expirada']),'sessão parada há mais de 60 minutos é encerrada');
    ok(session_id()!==$id,'sessão expirada recebe novo identificador');
} catch (Throwable $e) { fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n"); $falhou=true; }

if (!empty($falhou)) exit(1);
echo "[OK] TODOS OS TESTES DE MELHORIAS PASSARAM.\n";
