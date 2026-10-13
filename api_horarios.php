<?php
/** Horários livres obtidos do PostgreSQL, não de JSON fictício. */
require_once __DIR__.'/includes/bootstrap_sessao.php';
require_once __DIR__.'/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
if (!in_array($_SESSION['perfil'] ?? '', ['cliente','recepcionista'],true)) {
    http_response_code(403); echo json_encode(['erro'=>'Não autorizado']); exit;
}
try {
    $data=(string)($_GET['data'] ?? '');
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$data);
    if (!$dt || $dt->format('Y-m-d')!==$data || $data<date('Y-m-d')) throw new InvalidArgumentException('Data inválida.');
    $nomeProc=trim((string)($_GET['procedimento'] ?? ''));
    if ($nomeProc==='') throw new InvalidArgumentException('Selecione o procedimento.');
    $dentista=max(0,(int)($_GET['dentista'] ?? 0));
    $paciente=(int)($_GET['paciente_id'] ?? 0);
    if ($_SESSION['perfil']==='cliente') {
        $p=pacienteSessao(); $paciente=$p ? (int)$p['id'] : 0;
    }
    $pdo=\App\Config\Database::getConnection();
    $sql="SELECT DISTINCT to_char(dd.hora,'HH24:MI') AS horario FROM dentistas d
      JOIN dentista_procedimentos dp ON dp.id_dentista=d.id
      JOIN procedimentos p ON p.id=dp.id_procedimento
      JOIN disponibilidade_dentista dd ON dd.id_dentista=d.id
      JOIN funcionamento_clinica fc ON fc.id_clinica=1 AND fc.id_dia_semana=dd.id_dia_semana
      WHERE p.nome=? AND (?=0 OR d.id=?) AND d.status='ativo' AND d.id_sala IS NOT NULL
        AND dd.id_dia_semana=EXTRACT(DOW FROM CAST(? AS date))
        AND dd.hora>=fc.abertura AND dd.hora+make_interval(mins=>p.duracao_min)<=fc.fechamento
        AND (CAST(? AS date)>CURRENT_DATE OR dd.hora>LOCALTIME)
        AND NOT EXISTS (
          SELECT 1 FROM agendamentos a JOIN procedimentos op ON op.id=a.id_procedimento
          WHERE a.data=CAST(? AS date) AND a.status<>'Cancelado'
            AND (a.id_dentista=d.id OR a.id_sala=d.id_sala OR (? > 0 AND a.id_paciente=?))
            AND tsrange(a.data+a.hora,a.data+a.hora+make_interval(mins=>op.duracao_min),'[)') &&
            tsrange(CAST(? AS date)+dd.hora,CAST(? AS date)+dd.hora+make_interval(mins=>p.duracao_min),'[)')
        ) ORDER BY 1";
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$nomeProc,$dentista,$dentista,$data,$data,$data,$paciente,$paciente,$data,$data]);
    echo json_encode(['horarios'=>$stmt->fetchAll(PDO::FETCH_COLUMN)],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['erro'=>$e instanceof InvalidArgumentException?$e->getMessage():'Falha ao consultar disponibilidade.'],JSON_UNESCAPED_UNICODE);
    error_log('[DENTIA] api_horarios: '.$e->getMessage());
}
