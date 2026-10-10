<?php
// agenda do dia com filtros por data e dentista
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('recepcionista');

$tituloPagina = 'Agenda do Dia';

// pega os filtros da URL
$dataFiltro     = $_GET['data']     ?? date('Y-m-d');
$dentistaFiltro = $_GET['dentista'] ?? '';

// processa ações da tabela
$sucesso=isset($_GET['criado']) && ctype_digit((string)$_GET['criado']) ? 'Agendamento #'.(int)$_GET['criado'].' criado e vinculado ao paciente com sucesso.' : (isset($_GET['atualizado']) ? 'Consulta atualizada.' : ''); $erro='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (isset($_POST['receber'])) confirmarPagamento((int)$_POST['id'],(int)$_POST['forma']);
        else {
            $status=isset($_POST['fazer_checkin']) ? 'Aguardando' : (isset($_POST['cancelar_consulta']) ? 'Cancelado' : ($_POST['status'] ?? ''));
            alterarStatusAgenda((int)($_POST['id'] ?? 0),$status);
        }
        header('Location: recepcionista_agenda.php?data='.urlencode($dataFiltro).'&dentista='.urlencode($dentistaFiltro).'&atualizado=1'); exit;
    } catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
// A agenda consulta diretamente o PostgreSQL, sempre com filtros de data e dentista.
// Evita registros de outras datas e reflete imediatamente inclusões/alterações.
$dataFiltro = (string) ($_GET['data'] ?? date('Y-m-d'));
$dtFiltro = DateTimeImmutable::createFromFormat('!Y-m-d', $dataFiltro);
if (!$dtFiltro || $dtFiltro->format('Y-m-d') !== $dataFiltro) $dataFiltro = date('Y-m-d');
$dentistaFiltro = (string) ($_GET['dentista'] ?? '');
if ($dentistaFiltro !== '' && (!ctype_digit($dentistaFiltro) || (int)$dentistaFiltro < 1)) $dentistaFiltro = '';
$pdo = \App\Config\Database::getConnection();
$sqlAgenda = "SELECT a.id,a.id_paciente,a.id_dentista,to_char(a.data,'YYYY-MM-DD') AS data,to_char(a.hora,'HH24:MI') AS hora,a.status::text AS status,a.valor,a.observacoes,pp.nome AS paciente,pd.nome AS dentista,pr.nome AS servico,pr.nome AS procedimento, s.nome AS sala,(pg.pago_em IS NOT NULL) AS pago,COALESCE(fp.nome,'') AS forma_pagamento FROM agendamentos a JOIN pacientes pac ON pac.id=a.id_paciente JOIN pessoa pp ON pp.id=pac.id_pessoa JOIN dentistas d ON d.id=a.id_dentista JOIN pessoa pd ON pd.id=d.id_pessoa LEFT JOIN procedimentos pr ON pr.id=a.id_procedimento JOIN sala s ON s.id=a.id_sala LEFT JOIN pagamento pg ON pg.id_agendamento=a.id LEFT JOIN forma_pagamento fp ON fp.id=pg.id_forma_pagamento
    WHERE a.data = :data";
$paramsAgenda = ['data' => $dataFiltro];
if ($dentistaFiltro !== '') {
    $sqlAgenda .= ' AND a.id_dentista = :dentista';
    $paramsAgenda['dentista'] = (int)$dentistaFiltro;
}
$sqlAgenda .= ' ORDER BY a.hora, a.id';
$stmtAgenda = $pdo->prepare($sqlAgenda);
$stmtAgenda->execute($paramsAgenda);
$listaAgendamentos = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);
$formas=$pdo->query('SELECT id,nome FROM forma_pagamento ORDER BY id')->fetchAll();
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agenda do Dia</h1>
        <p><?= data_pt('l, d \d\e F \d\e Y', strtotime($dataFiltro)) ?></p>
      </div>
      <a href="recepcionista_novo_agendamento.php?data=<?= urlencode($dataFiltro) ?>" class="btn btn-primario">
        + Novo Agendamento
      </a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>

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

    <!-- lista de consultas do dia -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3>Consultas do dia</h3>
        <span style="color:var(--texto-fraco);font-size:0.82rem;">
          <?= count($listaAgendamentos) ?> consultas
        </span>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Consultas agendadas para o dia">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Paciente</th>
              <th>Procedimento</th>
              <th>Dentista</th>
              <th>Sala</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$listaAgendamentos): ?><tr><td colspan="7">Nenhuma consulta encontrada nesta data.</td></tr><?php endif; ?>
            <?php foreach ($listaAgendamentos as $agendamento): ?>
            <tr>
              <td><strong><?= limpar($agendamento['hora']) ?></strong></td>
              <td><?= limpar($agendamento['paciente']) ?></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['dentista']) ?></td>
              <td><?= limpar($agendamento['sala']) ?></td>
              <td><?= emblema($agendamento['status']) ?></td>
              <td>
                <div style="display:flex;gap:5px;"><?php if (in_array($agendamento['status'],['Agendado','Aguardando','Em atendimento'],true)): ?>
                  <form method="POST"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                  <select name="status">
                    <?php $opcoes=match($agendamento['status']){'Agendado'=>['Confirmado'],'Aguardando'=>['Em atendimento'],default=>['Concluída']};foreach ($opcoes as $st): ?><option><?= limpar($st) ?></option><?php endforeach; ?>
                  </select><button class="btn btn-pequeno" name="atualizar_status">Atualizar</button></form><?php endif; ?>
                  <?php if (!$agendamento['pago'] && $agendamento['status']!=='Cancelado'): ?>
                  <form method="POST"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $agendamento['id'] ?>"><select name="forma"><?php foreach ($formas as $f): ?><option value="<?= $f['id'] ?>"><?= limpar($f['nome']) ?></option><?php endforeach; ?></select><button class="btn btn-pequeno" name="receber">Receber</button></form>
                  <?php elseif ($agendamento['pago']): ?><span class="emblema-sucesso">Pago</span><?php endif; ?>
                  <?php if (in_array($agendamento['status'], ['Agendado', 'Confirmado'])): ?>
                  <form method="POST" style="display:inline;">
          <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="fazer_checkin"
                            class="btn btn-primario btn-pequeno"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Check-in</button>
                  </form>
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Cancelar esta consulta?')">
          <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="cancelar_consulta"
                            class="btn btn-perigo btn-pequeno"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></button>
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
