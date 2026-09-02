<?php
// agenda do dia com filtros por data e dentista
session_start();
require_once 'includes/functions.php';
verificarSessao('recepcionista');

$tituloPagina = 'Agenda do Dia';

// pega os filtros da URL
$dataFiltro     = $_GET['data']     ?? date('Y-m-d');
$dentistaFiltro = $_GET['dentista'] ?? '';

// processa ações da tabela
$sucesso = '';
if (isset($_POST['fazer_checkin']))    $sucesso = 'Check-in realizado!';
if (isset($_POST['cancelar_consulta'])) $sucesso = 'Consulta cancelada.';

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
      <a href="recepcionista_novo_agendamento.php" class="btn btn-primario">
        + Novo Agendamento
      </a>
    </div>

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
          <a href="?data=<?= date('Y-m-d', strtotime($dataFiltro . ' +1 day')) ?>"
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
            <?php foreach ($listaAgendamentos as $agendamento): ?>
            <tr>
              <td><strong><?= limpar($agendamento['hora']) ?></strong></td>
              <td><?= limpar($agendamento['paciente']) ?></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['dentista']) ?></td>
              <td><?= limpar($agendamento['sala']) ?></td>
              <td><?= emblema($agendamento['status']) ?></td>
              <td>
                <div style="display:flex;gap:5px;">
                  <?php if (in_array($agendamento['status'], ['Aguardando', 'Agendado', 'Confirmado'])): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="fazer_checkin"
                            class="btn btn-primario btn-pequeno"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Check-in</button>
                  </form>
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Cancelar esta consulta?')">
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
