<?php
// tela onde o paciente agenda sua própria consulta
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('cliente');carregarListas();

$tituloPagina = 'Agendar Consulta';
$erro='';
if (isset($_POST['solicitar_consulta'])) {
    try { $id=criarAgendamento($_POST); redirecionar('cliente_consultas.php','Consulta #'.$id.' agendada.'); }
    catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$pdo=\App\Config\Database::getConnection();
$salas=$pdo->query('SELECT * FROM sala ORDER BY nome')->fetchAll();
$formas=$pdo->query('SELECT * FROM forma_pagamento ORDER BY nome')->fetchAll();
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

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>

    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Solicitar Nova Consulta</h3></div>
      <div class="cartao-corpo">
        <form method="POST" data-agendamento-dinamico aria-label="Formulário de agendamento de consulta">
          <?= csrfCampo() ?>

          <!-- tipo de procedimento -->
          <div class="grupo-campo">
            <label for="sol-procedimento">Procedimento desejado</label>
            <select id="sol-procedimento" name="procedimento" required aria-required="true">
              <option value="">Selecione o procedimento...</option>
              <?php foreach ($listaProcedimentos as $procedimento): ?>
                <option <?= ($_POST['procedimento'] ?? '')===$procedimento ? 'selected' : '' ?>><?= limpar($procedimento) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- dentista de preferência -->
          <div class="grupo-campo">
            <label for="sol-dentista">Dentista de preferência</label>
            <select id="sol-dentista" name="dentista">
              <option value="">Sem preferência (qualquer dentista disponível)</option>
              <?php foreach ($listaDentistas as $dentista): ?>
                <option value="<?= $dentista['id'] ?>" <?= (int)($_POST['dentista'] ?? 0)===(int)$dentista['id'] ? 'selected' : '' ?>><?= limpar($dentista['nome']) ?> — <?= limpar($dentista['especialidade']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- data e horário preferidos -->
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="sol-data">Data preferida</label>
              <!-- antecedência mínima definida pelo gestor em Configurações -->
              <?php $dataMinima = dataMinimaPaciente(\App\Config\Database::getConnection()); ?>
              <input type="date" id="sol-data" name="data"
                     min="<?= $dataMinima ?>"
                     value="<?= limpar((string)($_POST['data'] ?? '') ?: $dataMinima) ?>"
                     required aria-required="true">
            </div>
            <div class="grupo-campo">
              <label for="sol-horario">Horário preferido</label>
              <select id="sol-horario" name="horario">
                <?php foreach ($listaHorarios as $horario): ?>
                  <option <?= ($_POST['horario'] ?? '')===$horario ? 'selected' : '' ?>><?= $horario ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <p>O plano cadastrado no seu perfil será mantido. O valor segue o catálogo da clínica.</p>
          <!-- campo aberto para o paciente descrever o problema -->
          <div class="grupo-campo">
            <label for="sol-obs">Descreva o motivo ou sintomas (opcional)</label>
            <textarea id="sol-obs" name="obs" rows="3"
                      placeholder="Descreva o que está sentindo, dúvidas ou informações importantes para o dentista..."><?= limpar((string)($_POST['obs'] ?? '')) ?></textarea>
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
<script src="js/agenda-horarios.js?v=<?= filemtime(__DIR__ . "/js/agenda-horarios.js") ?>" defer></script>
</body>
</html>
