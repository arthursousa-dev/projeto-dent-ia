<?php
// painel principal do dentista — visão do dia de trabalho
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dentista');

$tituloPagina = 'Painel do Dentista';

// processa o salvamento rápido de prontuário
$sucesso = isset($_POST['salvar_atendimento'])
    ? 'Prontuário salvo em ' . data_pt('d/m/Y \à\s H:i') . '!'
    : '';

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1>Olá, Dr. Carlos! <i class="bi bi-emoji-smile-fill" aria-hidden="true"></i></h1>
        <p>Clínico Geral — CRO-SP 12345 — <?= data_pt('d \d\e F \d\e Y') ?></p>
      </div>
      <a href="dentista_agenda.php" class="btn btn-primario"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Ver Agenda Completa</a>
    </div>

    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>

    <!-- estatísticas do dentista -->
    <div class="grade-estatisticas">
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>5</strong><span>Atendidos hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo" aria-hidden="true">⏳</div>
        <div class="info-estatistica"><strong>3</strong><span>Restantes hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>48</strong><span>Atendimentos no mês</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde-agua" aria-hidden="true"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>127</strong><span>Total de pacientes</span></div>
      </div>
    </div>

    <!-- destaque do próximo paciente -->
    <div style="background:linear-gradient(135deg,var(--primario),var(--primario-claro));border-radius:var(--raio);padding:26px 30px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px;"
         role="region" aria-label="Próximo paciente">
      <div style="color:#fff;">
        <p style="color:rgba(255,255,255,0.55);font-size:0.78rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:7px;">
          <i class="bi bi-bell-fill" aria-hidden="true"></i> Próximo Paciente — 10:00
        </p>
        <h2 style="font-family:var(--fonte-titulo);font-size:1.5rem;margin-bottom:5px;">Carla Pereira</h2>
        <p style="color:rgba(255,255,255,0.7);font-size:0.9rem;">
          Procedimento: <strong style="color:var(--destaque);">Ortodontia</strong>
        </p>
        <p style="color:rgba(255,255,255,0.55);font-size:0.82rem;margin-top:3px;">
          Última visita: 15/01/2026 — Sala 2
        </p>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="dentista_prontuario.php?paciente=Carla+Pereira"
           class="btn btn-primario"><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Prontuário</a>
        <a href="dentista_agenda.php" class="btn btn-contorno">Ver Agenda</a>
      </div>
    </div>

    <!-- agenda resumida do dia -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agenda de Hoje — <?= data_pt('d/m/Y') ?></h3>
        <a href="dentista_agenda.php" class="cartao-link">Ver completa →</a>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Agenda resumida do dia do dentista">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Paciente</th>
              <th>Procedimento</th>
              <th>Sala</th>
              <th>Status</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($listaAgendamentos, 0, 5) as $agendamento): ?>
            <tr>
              <td><strong><?= limpar($agendamento['hora']) ?></strong></td>
              <td><?= limpar($agendamento['paciente']) ?></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['sala']) ?></td>
              <td><?= emblema($agendamento['status']) ?></td>
              <td>
                <a href="dentista_prontuario.php?paciente=<?= urlencode($agendamento['paciente']) ?>"
                   class="cartao-link" style="font-size:0.8rem;">
                  <i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Prontuário
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- registro rápido de atendimento -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Registrar Atendimento Rápido</h3></div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de registro rápido de atendimento">

          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="rap-paciente">Paciente</label>
              <select id="rap-paciente" name="paciente">
                <?php foreach ($listaPacientes as $paciente): ?>
                  <option><?= limpar($paciente['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="rap-procedimento">Procedimento realizado</label>
              <select id="rap-procedimento" name="procedimento">
                <?php foreach ($listaProcedimentos as $proc): ?>
                  <option><?= limpar($proc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grupo-campo">
            <label for="rap-obs">Observações clínicas</label>
            <textarea id="rap-obs" name="obs" rows="3"
                      placeholder="Descreva o procedimento, materiais utilizados, observações importantes..."></textarea>
          </div>

          <div class="grupo-campo">
            <label for="rap-retorno">Próximo retorno</label>
            <input type="date" id="rap-retorno" name="retorno" min="<?= date('Y-m-d') ?>">
          </div>

          <button type="submit" name="salvar_atendimento" class="btn btn-primario">
            <i class="bi bi-save-fill" aria-hidden="true"></i> Salvar Prontuário
          </button>
        </form>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
