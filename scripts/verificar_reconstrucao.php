<?php
require_once dirname(__DIR__).'/app/Config/Database.php';
$pdo=\App\Config\Database::getConnection();
$tabs=['pessoa','usuarios','pacientes','dentistas','procedimentos','agendamentos','pagamento','prontuarios','evolucao_clinica','lancamento_financeiro','sala','disponibilidade_dentista','funcionamento_clinica'];
$views=['vw_usuarios','vw_pacientes','vw_dentistas','vw_procedimentos','vw_agendamentos','vw_resumo_financeiro_mensal','vw_movimentos_financeiros'];
$ok=true;
foreach (array_merge($tabs,$views) as $name) {
 $found=$pdo->prepare('SELECT to_regclass(:name) IS NOT NULL'); $found->execute(['name'=>'public.'.$name]);
 $exists=(bool)$found->fetchColumn(); echo ($exists?'[OK] ':'[FALTA] ').$name.PHP_EOL; $ok=$ok&&$exists;
}
if ($ok) {
 $owner=$pdo->query("SELECT p.nome FROM usuarios u JOIN pessoa p ON p.id=u.id_pessoa WHERE u.tipo='dono' LIMIT 1")->fetchColumn();
 echo 'Nome do dono: '.($owner ?: '[NÃO ENCONTRADO]').PHP_EOL;
 foreach (['pacientes','dentistas','agendamentos'] as $t) echo $t.': '.$pdo->query('SELECT count(*) FROM '.$t)->fetchColumn().PHP_EOL;
 $pdo->beginTransaction();
 try {
  $stmt=$pdo->query("SELECT a.id_paciente,a.id_dentista,a.id_procedimento,a.id_sala,a.valor FROM agendamentos a WHERE a.status <> 'Cancelado' LIMIT 1");
  $a=$stmt->fetch();
  if ($a) {
   $sql="INSERT INTO agendamentos(id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor) VALUES (:pac,:den,:proc,:sala,current_date + interval '730 days','07:00',:valor) RETURNING id";
   $ins=$pdo->prepare($sql);$ins->execute(['pac'=>$a['id_paciente'],'den'=>$a['id_dentista'],'proc'=>$a['id_procedimento'],'sala'=>$a['id_sala'],'valor'=>$a['valor']]);
   $id=$ins->fetchColumn(); echo '[OK] INSERT agendamentos ID '.$id.' (rollback)'.PHP_EOL;
  } else echo '[AVISO] Não há agendamento de exemplo para testar o INSERT'.PHP_EOL;
 } finally {if ($pdo->inTransaction()) $pdo->rollBack();}
}
exit($ok?0:1);
