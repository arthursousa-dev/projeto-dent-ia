<?php
// agenda pessoal do dentista — filtra por data
session_start();
require_once 'includes/functions.php';
verificarSessao('dentista');

$tituloPagina = 'Minha Agenda';
$dataFiltro   = $_GET['data'] ?? date('Y-m-d');

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Minha Agenda</h1>
        <p><?= data_pt('l, d \d\e F \d\e Y', strtotime($dataFiltro)) ?></p>
      </div>
    </div>

    <!-- seletor de data para navegar na agenda -->
    <div class="cartao">
      <div class="cartao-corpo">
        <form method="GET" aria-label="Selecionar data da agenda" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
          <div class="grupo-campo" style="margin-bottom:0;flex:1;min-width:160px;">
            <label for="sel-data">Data</label>
            <input type="date" id="sel-data" name="data" value="<?= limpar($dataFiltro) ?>">
          </div>
          <button type="submit" class="btn btn-primario">Ver agenda</button>
          <!-- atalho para hoje -->
          <a href="?data=<?= date('Y-m-d') ?>" class="btn btn-fantasma">Hoje</a>
          <!-- navega para o dia seguinte -->
          <a href="?data=<?= date('Y-m-d', strtotime($dataFiltro . ' +1 day')) ?>"
             class="btn btn-fantasma">Próximo dia →</a>
        </form>
      </div>
    </div>

    <!-- cartões de consulta do dia -->
    <div class="grade-agenda" role="list" aria-label="Consultas do dia">
      <?php foreach ($listaAgendamentos as $agendamento):
        // define a classe visual de acordo com o status
        $classeCartao = '';
        if (in_array($agendamento['status'], ['Aguardando', 'Agendado'])) {
            $classeCartao = 'pendente';
        } elseif ($agendamento['status'] === 'Cancelado') {
            $classeCartao = 'cancelado';
        }
      ?>
      <article class="cartao-agenda <?= $classeCartao ?>" role="listitem">
        <div class="hora-agenda"><i class="bi bi-clock-fill" aria-hidden="true"></i> <?= limpar($agendamento['hora']) ?> — <?= limpar($agendamento['sala']) ?></div>
        <h4><?= limpar($agendamento['paciente']) ?></h4>
        <div class="procedimento-agenda"><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> <?= limpar($agendamento['procedimento']) ?></div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;">
          <?= emblema($agendamento['status']) ?>
          <a href="dentista_prontuario.php?paciente=<?= urlencode($agendamento['paciente']) ?>"
             class="cartao-link" style="font-size:0.8rem;">
            Ver prontuário →
          </a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
