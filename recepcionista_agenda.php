<?php
// agenda do dia com filtros por data e dentista
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('recepcionista');carregarListas(['dentistas']);

$tituloPagina = 'Agenda do Dia';

// Filtros da URL, validados antes de qualquer uso (inclusive no redirecionamento depois de uma ação).
$dataFiltro = (string)($_GET['data'] ?? '');
if ($dataFiltro !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFiltro)) $dataFiltro = 'invalida';
$dentistaFiltro = (string)($_GET['dentista'] ?? '');
if ($dentistaFiltro !== '' && idParametro($dentistaFiltro) < 1) $dentistaFiltro = '';
$somentePendentes = isset($_GET['pendentes']);
$sucesso = ''; $erro = '';

// Ações da tabela: cada uma volta para a mesma agenda com a mensagem (padrão POST → redirecionamento → GET).
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $id = idParametro($_POST['id'] ?? 0);
        if (isset($_POST['receber'])) { confirmarPagamento($id,(int)($_POST['forma'] ?? 0)); $mensagem='Pagamento registrado.'; }
        elseif (isset($_POST['estornar'])) { estornarPagamento($id,(string)($_POST['motivo'] ?? '')); $mensagem='Pagamento estornado. O registro do estorno fica guardado; a consulta voltou a "não paga".'; }
        elseif (isset($_POST['corrigir_falta'])) { $mensagem='Falta desfeita. A consulta voltou para "'.corrigirFalta($id).'".'; }
        else {
            $status=match (true) {
                isset($_POST['fazer_checkin'])     => 'Aguardando',
                isset($_POST['cancelar_consulta']) => 'Cancelado',
                isset($_POST['marcar_falta'])      => 'Faltou',
                default                            => (string)($_POST['status'] ?? ''),
            };
            alterarStatusAgenda($id,$status);
            $mensagem=match ($status) { 'Faltou'=>'Falta registrada.'.($somentePendentes ? ' Se foi engano, use "Desfazer falta" na agenda do dia da consulta.' : ''), 'Cancelado'=>'Consulta cancelada.', 'Aguardando'=>'Check-in feito.', default=>'Consulta atualizada.' };
        }
        $voltar = $somentePendentes ? 'pendentes=1' : 'data='.urlencode($dataFiltro === 'invalida' ? '' : $dataFiltro).'&dentista='.urlencode($dentistaFiltro);
        redirecionar('recepcionista_agenda.php?'.$voltar, $mensagem);
    } catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
// A agenda consulta diretamente o PostgreSQL, sempre com filtros de data e dentista.
// Evita registros de outras datas e reflete imediatamente inclusões/alterações.
$dataFiltro = (string) ($_GET['data'] ?? date('Y-m-d'));
$dtFiltro = DateTimeImmutable::createFromFormat('!Y-m-d', $dataFiltro);
if (!$dtFiltro || $dtFiltro->format('Y-m-d') !== $dataFiltro) {
    if ($dataFiltro !== '') definirFlash('aviso', 'Data da agenda inválida. Mostrando a agenda de hoje.');
    $dataFiltro = date('Y-m-d');
}
$pdo = \App\Config\Database::getConnection();
// Pendentes de regularização: dia já passou e a consulta não teve desfecho (concluída, cancelada ou falta).
$condicaoPendente = "ag.data < CURRENT_DATE AND ag.status IN (".listaSql(STATUS_EM_ABERTO).")";
$totalPendentes = (int)$pdo->query("SELECT count(*) FROM agendamentos ag WHERE $condicaoPendente")->fetchColumn();
if ($somentePendentes) {
    $listaAgendamentos = agendaRecepcao($condicaoPendente);
} elseif ($dentistaFiltro !== '') {
    $listaAgendamentos = agendaRecepcao('ag.data = CAST(:data AS date) AND ag.id_dentista = :dentista', ['data'=>$dataFiltro, 'dentista'=>(int)$dentistaFiltro]);
} else {
    $listaAgendamentos = agendaRecepcao('ag.data = CAST(:data AS date)', ['data'=>$dataFiltro]);
}
$hoje = date('Y-m-d');
$historico = [];
if ($listaAgendamentos) {
    $stmtHist = $pdo->prepare("SELECT h.id_agendamento,h.status_anterior::text AS de,h.status_novo::text AS para,
        to_char(h.alterado_em,'DD/MM/YYYY HH24:MI') AS quando,p.nome AS quem
        FROM historico_status_agendamento h JOIN usuarios u ON u.id=h.id_usuario JOIN pessoa p ON p.id=u.id_pessoa
        WHERE h.id_agendamento = ANY(CAST(:ids AS int[])) ORDER BY h.alterado_em,h.id");
    $stmtHist->execute(['ids'=>'{'.implode(',',array_map('intval',array_column($listaAgendamentos,'id'))).'}']);
    foreach ($stmtHist->fetchAll() as $h) $historico[(int)$h['id_agendamento']][]=$h;
}
$formas=$pdo->query('SELECT id,nome FROM forma_pagamento ORDER BY id')->fetchAll();
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <?php if ($somentePendentes): ?>
        <h1><i class="bi bi-hourglass-split" aria-hidden="true"></i> Consultas sem desfecho</h1>
        <p>Dias que já passaram, com consulta ainda agendada, confirmada ou em atendimento. Registre o que aconteceu em cada uma.</p>
        <?php else: ?>
        <h1><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agenda do Dia</h1>
        <p><?= data_pt('l, d \d\e F \d\e Y', strtotime($dataFiltro)) ?></p>
        <?php endif; ?>
      </div>
      <a href="recepcionista_novo_agendamento.php?data=<?= urlencode($dataFiltro) ?>" class="btn btn-primario">
        + Novo Agendamento
      </a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>
    <?php if ($somentePendentes): ?>
      <p><a href="recepcionista_agenda.php" class="btn btn-fantasma btn-pequeno"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar para a agenda do dia</a></p>
    <?php elseif ($totalPendentes): ?>
      <div class="alerta alerta-aviso" role="status">
        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
        <?= $totalPendentes ?> <?= $totalPendentes===1 ? 'consulta de dias anteriores está' : 'consultas de dias anteriores estão' ?> sem desfecho.
        <a href="recepcionista_agenda.php?pendentes=1">Ver e regularizar</a>
      </div>
    <?php endif; ?>

    <?php if (!$somentePendentes): ?>
    <!-- filtros de data e dentista -->
    <div class="cartao">
      <div class="cartao-corpo">
        <form method="GET" aria-label="Filtros da agenda" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
          <div class="grupo-campo" style="margin-bottom:0;flex:1;min-width:150px;">
            <label for="filtro-data">Data</label>
            <input type="date" id="filtro-data" name="data" value="<?= limpar($dataFiltro) ?>">
          </div>
          <div class="grupo-campo" style="margin-bottom:0;flex:2;min-width:200px;">
            <label for="filtro-dentista">Dentista</label>
            <select id="filtro-dentista" name="dentista">
              <option value="">Todos os dentistas</option>
              <?php foreach ($listaDentistas as $dentista): ?>
                <option value="<?= $dentista['id'] ?>"
                        <?= $dentistaFiltro == $dentista['id'] ? 'selected' : '' ?>>
                  <?= limpar($dentista['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primario">Filtrar</button>
          <a href="recepcionista_agenda.php" class="btn btn-fantasma">Limpar</a>
          <!-- navegar para o próximo dia -->
          <a href="?data=<?= date('Y-m-d', strtotime($dataFiltro . ' +1 day')) ?>&amp;dentista=<?= urlencode($dentistaFiltro) ?>"
             class="btn btn-fantasma">
            Próximo dia →
          </a>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- lista de consultas do dia -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><?= $somentePendentes ? 'Pendentes de regularização' : 'Consultas do dia' ?></h3>
        <span style="color:var(--texto-fraco);font-size:0.82rem;">
          <?= count($listaAgendamentos) ?> consultas
        </span>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Consultas agendadas para o dia">
          <thead>
            <tr>
              <th><?= $somentePendentes ? 'Data e hora' : 'Hora' ?></th>
              <th>Paciente</th>
              <th>Procedimento</th>
              <th>Dentista</th>
              <th>Sala</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$listaAgendamentos): ?><tr><td colspan="7"><?= $somentePendentes ? 'Nenhuma consulta pendente. Todas as consultas de dias anteriores têm desfecho.' : 'Nenhuma consulta encontrada nesta data.' ?></td></tr><?php endif; ?>
            <?php foreach ($listaAgendamentos as $agendamento):
              $semDesfecho = $agendamento['data'] < $hoje && in_array($agendamento['status'],STATUS_EM_ABERTO,true); ?>
            <tr data-consulta="<?= (int)$agendamento['id'] ?>">
              <td><strong><?= $somentePendentes ? date('d/m/Y', strtotime($agendamento['data'])).' ' : '' ?><?= limpar($agendamento['hora']) ?></strong></td>
              <td><?= limpar($agendamento['paciente']) ?></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['dentista']) ?></td>
              <td><?= limpar($agendamento['sala']) ?></td>
              <td>
                <?= emblema($agendamento['status']) ?>
                <?php if ($semDesfecho): ?><span class="emblema emblema-pendente" title="O dia passou e o desfecho não foi registrado">Sem desfecho</span><?php endif; ?>
                <?php if (!empty($historico[(int)$agendamento['id']])): ?>
                <details class="historico-status">
                  <summary>Histórico</summary>
                  <ul>
                    <?php foreach ($historico[(int)$agendamento['id']] as $h): ?>
                    <li><?= limpar($h['quando']) ?> · <?= limpar($h['de']) ?> → <?= limpar($h['para']) ?> · <?= limpar($h['quem']) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </details>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:5px;"><?php if (in_array($agendamento['status'],['Agendado','Aguardando','Em atendimento'],true)): ?>
                  <form method="POST"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                  <select name="status">
                    <?php $opcoes=match($agendamento['status']){'Agendado'=>['Confirmado'],'Aguardando'=>['Em atendimento'],default=>['Concluída']};foreach ($opcoes as $st): ?><option><?= limpar($st) ?></option><?php endforeach; ?>
                  </select><button class="btn btn-pequeno" name="atualizar_status">Atualizar</button></form><?php endif; ?>
                  <?php if (!$agendamento['pago'] && !in_array($agendamento['status'],['Cancelado','Faltou'],true)): ?>
                  <form method="POST" data-confirmar="Registrar o pagamento desta consulta?"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $agendamento['id'] ?>"><select name="forma" aria-label="Forma de pagamento"><?php foreach ($formas as $f): ?><option value="<?= $f['id'] ?>"><?= limpar($f['nome']) ?></option><?php endforeach; ?></select><button class="btn btn-pequeno" name="receber">Receber</button></form>
                  <?php elseif ($agendamento['pago']): ?><span class="emblema emblema-sucesso">Pago</span>
                  <form method="POST" class="form-estorno" data-confirmar="Estornar o pagamento desta consulta? O registro do estorno fica guardado.">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= (int)$agendamento['id'] ?>">
                    <input type="text" name="motivo" required maxlength="300" placeholder="Motivo do estorno" aria-label="Motivo do estorno">
                    <button class="btn btn-fantasma btn-pequeno" name="estornar"><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Estornar</button>
                  </form>
                  <?php endif; ?>
                  <?php if (in_array($agendamento['status'], ['Agendado', 'Confirmado'])): ?>
                  <?php if ($agendamento['data'] <= date('Y-m-d')): ?>
                  <form method="POST" style="display:inline;">
          <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="fazer_checkin"
                            class="btn btn-primario btn-pequeno"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Check-in</button>
                  </form>
                  <?php endif; ?>
                  <?php if (!$agendamento['pago']): ?><a href="recepcionista_novo_agendamento.php?reagendar=<?= (int)$agendamento['id'] ?>" class="btn btn-fantasma btn-pequeno"><i class="bi bi-calendar2-week" aria-hidden="true"></i> Remarcar</a><?php endif; ?>
                  <?php if (!$agendamento['pago'] && faltaPermitida($agendamento['data'],$agendamento['hora'])): ?>
                  <form method="POST" style="display:inline;" data-confirmar="Registrar que o paciente faltou? Se for engano, use “Desfazer falta”.">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="marcar_falta" class="btn btn-fantasma btn-pequeno"><i class="bi bi-person-x" aria-hidden="true"></i> Faltou</button>
                  </form>
                  <?php endif; ?>
                  <?php if (!$agendamento['pago']): ?>
                  <form method="POST" style="display:inline;"
                        data-confirmar="Cancelar esta consulta?">
          <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="cancelar_consulta"
                            class="btn btn-perigo btn-pequeno"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></button>
                  </form>
                  <?php endif; ?>
                  <?php elseif ($agendamento['status']==='Aguardando' && !$agendamento['pago']): ?>
                  <form method="POST" style="display:inline;" data-confirmar="Cancelar esta consulta? O paciente fez check-in, mas não foi atendido.">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= (int)$agendamento['id'] ?>">
                    <button type="submit" name="cancelar_consulta" class="btn btn-perigo btn-pequeno" aria-label="Cancelar consulta"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></button>
                  </form>
                  <?php elseif ($agendamento['status']==='Faltou'): ?>
                  <form method="POST" style="display:inline;" data-confirmar="Desfazer a falta desta consulta? Ela volta ao status anterior e a correção fica no histórico.">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="corrigir_falta" class="btn btn-fantasma btn-pequeno"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Desfazer falta</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
