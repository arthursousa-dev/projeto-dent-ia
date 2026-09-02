<?php
// tela onde o paciente agenda sua própria consulta
session_start();
require_once 'includes/functions.php';
verificarSessao('cliente');

$tituloPagina = 'Agendar Consulta';
$sucesso = isset($_POST['solicitar_consulta'])
    ? 'Agendamento solicitado com sucesso! A clínica confirmará em breve por e-mail.'
    : '';

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agendar Consulta</h1>
        <p>Escolha o procedimento, dentista e horário ideal para você</p>
      </div>
    </div>

    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status" aria-live="polite">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?>
      </div>
    <?php endif; ?>

    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Solicitar Nova Consulta</h3></div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de agendamento de consulta">

          <!-- tipo de procedimento -->
          <div class="grupo-campo">
            <label for="sol-procedimento">Procedimento desejado</label>
            <select id="sol-procedimento" name="procedimento" required aria-required="true">
              <option value="">Selecione o procedimento...</option>
              <?php foreach ($listaProcedimentos as $procedimento): ?>
                <option><?= limpar($procedimento) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- dentista de preferência -->
          <div class="grupo-campo">
            <label for="sol-dentista">Dentista de preferência</label>
            <select id="sol-dentista" name="dentista">
              <option value="">Sem preferência (qualquer dentista disponível)</option>
              <?php foreach ($listaDentistas as $dentista): ?>
                <option><?= limpar($dentista['nome']) ?> — <?= limpar($dentista['especialidade']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- data e horário preferidos -->
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="sol-data">Data preferida</label>
              <!-- começa do dia seguinte pra não permitir agendar hoje -->
              <input type="date" id="sol-data" name="data"
                     min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                     value="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                     required aria-required="true">
            </div>
            <div class="grupo-campo">
              <label for="sol-horario">Horário preferido</label>
              <select id="sol-horario" name="horario">
                <?php foreach ($listaHorarios as $horario): ?>
                  <option><?= $horario ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- convênio ou plano de saúde -->
          <div class="grupo-campo">
            <label for="sol-convenio">Convênio / Plano de saúde</label>
            <select id="sol-convenio" name="convenio">
              <option>Particular</option>
              <option>Unimed</option>
              <option>Bradesco Saúde</option>
              <option>Amil</option>
              <option>SulAmérica</option>
              <option>Outro</option>
            </select>
          </div>

          <!-- campo aberto para o paciente descrever o problema -->
          <div class="grupo-campo">
            <label for="sol-obs">Descreva o motivo ou sintomas (opcional)</label>
            <textarea id="sol-obs" name="obs" rows="3"
                      placeholder="Descreva o que está sentindo, dúvidas ou informações importantes para o dentista..."></textarea>
          </div>

          <button type="submit" name="solicitar_consulta" class="btn btn-primario"
                  style="font-size:1rem;padding:12px 32px;">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Solicitar Agendamento
          </button>
        </form>
      </div>
    </div>

    <!-- apresentação dos dentistas disponíveis -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Nossos Especialistas</h3></div>
      <div class="cartao-corpo">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
          <?php foreach ($listaDentistas as $dentista): ?>
          <div style="padding:18px;background:var(--fundo-campo);border:1px solid var(--borda);border-radius:var(--raio);text-align:center;">
            <div class="avatar avatar-medio destaque" style="margin:0 auto 10px;" aria-hidden="true">
              <?= iniciais($dentista['nome']) ?>
            </div>
            <strong style="display:block;font-size:0.9rem;color:var(--texto-principal);">
              <?= limpar($dentista['nome']) ?>
            </strong>
            <span style="color:var(--destaque);font-size:0.8rem;font-weight:600;">
              <?= limpar($dentista['especialidade']) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
