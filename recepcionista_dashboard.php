<?php
// painel da recepcionista — controle do dia a dia da recepção
session_start();
require_once 'includes/functions.php';
verificarSessao('recepcionista');

$tituloPagina = 'Painel da Recepção';

// processa as ações rápidas da tabela de agenda
$sucesso = '';
if (isset($_POST['fazer_checkin']))   $sucesso = 'Check-in realizado com sucesso!';
if (isset($_POST['cancelar_consulta'])) $sucesso = 'Consulta cancelada.';
if (isset($_POST['agendar_rapido']))  $sucesso = 'Agendamento realizado com sucesso!';

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1>Painel da Recepção <i class="bi bi-folder2-open" aria-hidden="true"></i></h1>
        <p><?= data_pt('l, d \d\e F \d\e Y') ?></p>
      </div>
      <a href="recepcionista_novo_agendamento.php" class="btn btn-primario">
        + Novo Agendamento
      </a>
    </div>

    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>

    <!-- estatísticas do dia -->
    <div class="grade-estatisticas">
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>8</strong><span>Atendidos hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo" aria-hidden="true">⏳</div>
        <div class="info-estatistica"><strong>4</strong><span>Aguardando</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>12</strong><span>Total hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica vermelho" aria-hidden="true"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>2</strong><span>Cancelados</span></div>
      </div>
    </div>

    <!-- agenda do dia com ações de check-in -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agenda de Hoje — <?= data_pt('d/m/Y') ?></h3>
        <a href="recepcionista_agenda.php" class="cartao-link">Ver agenda completa →</a>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Agenda de consultas de hoje">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Paciente</th>
              <th>Procedimento</th>
              <th>Dentista</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($listaAgendamentos, 0, 5) as $agendamento): ?>
            <tr>
              <td><strong><?= limpar($agendamento['hora']) ?></strong></td>
              <td><?= limpar($agendamento['paciente']) ?></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['dentista']) ?></td>
              <td><?= emblema($agendamento['status']) ?></td>
              <td>
                <div style="display:flex;gap:5px;">
                  <?php if (in_array($agendamento['status'], ['Aguardando', 'Agendado', 'Confirmado'])): ?>
                  <!-- botão de check-in -->
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="fazer_checkin"
                            class="btn btn-primario btn-pequeno"
                            aria-label="Fazer check-in de <?= limpar($agendamento['paciente']) ?>">
                      <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Check-in
                    </button>
                  </form>
                  <!-- botão de cancelar -->
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Cancelar consulta de <?= limpar($agendamento['paciente']) ?>?')">
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="cancelar_consulta"
                            class="btn btn-perigo btn-pequeno"
                            aria-label="Cancelar consulta de <?= limpar($agendamento['paciente']) ?>">
                      <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                    </button>
                  </form>
                  <?php else: ?>
                  <a href="recepcionista_pacientes.php" class="btn btn-fantasma btn-pequeno">Ver</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- formulário de agendamento rápido -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> Agendamento Rápido</h3>
        <a href="recepcionista_novo_agendamento.php" class="cartao-link">Formulário completo →</a>
      </div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de agendamento rápido">
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="rapido-paciente">Paciente</label>
              <input type="text" id="rapido-paciente" name="paciente"
                     placeholder="Nome ou CPF do paciente..." required>
            </div>
            <div class="grupo-campo">
              <label for="rapido-procedimento">Procedimento</label>
              <select id="rapido-procedimento" name="procedimento">
                <?php foreach ($listaProcedimentos as $procedimento): ?>
                  <option><?= limpar($procedimento) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="rapido-dentista">Dentista</label>
              <select id="rapido-dentista" name="dentista">
                <?php foreach ($listaDentistas as $dentista): ?>
                  <option><?= limpar($dentista['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="rapido-data">Data</label>
              <input type="date" id="rapido-data" name="data"
                     min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="grupo-campo">
              <label for="rapido-horario">Horário</label>
              <select id="rapido-horario" name="horario">
                <?php foreach ($listaHorarios as $horario): ?>
                  <option><?= $horario ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit" name="agendar_rapido" class="btn btn-primario">
            Confirmar agendamento →
          </button>
        </form>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
