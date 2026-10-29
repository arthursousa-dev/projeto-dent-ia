<?php
/** Horários livres obtidos do PostgreSQL (regra única em horariosLivres(), includes/operacoes.php). */
require_once __DIR__.'/includes/bootstrap_sessao.php';
require_once __DIR__.'/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!in_array($_SESSION['perfil'] ?? '', ['cliente','recepcionista'],true)) {
    http_response_code(403); echo json_encode(['erro'=>'Sessão encerrada. Entre novamente.'],JSON_UNESCAPED_UNICODE); exit;
}
try {
    $data=dataValida((string)($_GET['data'] ?? ''),'data da consulta',hoje(),emDias(AGENDA_DIAS_A_FRENTE));
    $nomeProc=trim((string)($_GET['procedimento'] ?? ''));
    if ($nomeProc==='') throw new InvalidArgumentException('Selecione o procedimento.');
    $dentista=(string)($_GET['dentista'] ?? '0');
    if (!ctype_digit($dentista) || strlen($dentista)>9) throw new InvalidArgumentException('Dentista inválido.');
    $paciente=(string)($_GET['paciente_id'] ?? '0');
    $paciente=ctype_digit($paciente) && strlen($paciente)<=9 ? (int)$paciente : 0;
    if ($_SESSION['perfil']==='cliente') {
        $p=pacienteSessao(); $paciente=$p ? (int)$p['id'] : 0;
        $pdo=\App\Config\Database::getConnection();
        if ($data<dataMinimaPaciente($pdo)) {
            throw new InvalidArgumentException('Pelo portal, agende a partir de '.date('d/m/Y',strtotime(dataMinimaPaciente($pdo))).'.');
        }
    }
    echo json_encode(['horarios'=>horariosLivres($data,$nomeProc,(int)$dentista,$paciente)],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['erro'=>$e instanceof InvalidArgumentException ? $e->getMessage() : mensagemOperacao($e)],JSON_UNESCAPED_UNICODE);
}
