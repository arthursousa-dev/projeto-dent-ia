<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('cliente');
$tituloPagina = 'Minhas Consultas';

$erro='';
if (isset($_POST['cancelar_consulta'])) {
    try { alterarStatusAgenda(idParametro($_POST['id'] ?? 0),'Cancelado');redirecionar(paginaAtual(),'Consulta cancelada.'); }
    catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$p=pacienteSessao();
$minhasConsultas=lerJson('agendamentos.json',['id_paciente'=>$p['id'] ?? 0]);
$minhasConsultas=array_map(function($a){$a['procedimento']=$a['servico'];return $a;},$minhasConsultas);
$hoje   = date('Y-m-d');
$futuras = array_values(array_filter($minhasConsultas, 'consultaFutura'));
// Tudo o que não é futura vai para o histórico (concluídas, canceladas, faltas e as de hoje já iniciadas).
$passadas = array_values(array_filter($minhasConsultas, fn($a) => !consultaFutura($a)));
$proxima = $futuras[0] ?? null;

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">
    <?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>
    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Minhas Consultas</h1><p>Histórico e próximas consultas agendadas</p></div>
      <a href="cliente_agendamento.php" class="btn btn-primario">+ Nova Consulta</a>
    </div>

    <!-- Próxima consulta destaque -->
    <?php if ($proxima): ?>
    <div class="cartao" style="margin-bottom:20px;border-left:4px solid var(--destaque);">
      <div class="cartao-cabecalho"><h3 style="color:var(--destaque);"><i class="bi bi-bell-fill" aria-hidden="true"></i> Próxima Consulta</h3></div>
      <div class="cartao-corpo">
        <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap;">
          <div style="background:var(--destaque);color:#0b2845;border-radius:var(--raio);padding:16px 22px;text-align:center;min-width:70px;flex-shrink:0;">
            <div style="font-size:28px;font-weight:900;line-height:1;"><?= date('d', strtotime($proxima['data'])) ?></div>
            <div style="font-size:12px;font-weight:600;"><?= data_pt('M', strtotime($proxima['data'])) ?></div>
          </div>
          <div style="flex:1;">
            <div style="font-size:18px;font-weight:700;color:var(--texto-principal);margin-bottom:4px;"><?= limpar($proxima['procedimento']) ?></div>
            <div style="font-size:14px;color:var(--texto-secundario);">
              <i class="bi bi-person-badge-fill" aria-hidden="true"></i> <?= limpar($proxima['dentista'] ?? '—') ?> &nbsp;·&nbsp;
              <i class="bi bi-clock-fill" aria-hidden="true"></i> <?= limpar($proxima['hora'] ?? '—') ?> &nbsp;·&nbsp;
              <i class="bi bi-geo-alt-fill" aria-hidden="true"></i> <?= limpar($proxima['sala'] ?? '—') ?>
            </div>
            <?php if (!empty($proxima['observacoes'])): ?>
            <div style="margin-top:8px;font-size:13px;color:var(--texto-fraco);"><i class="bi bi-pencil-square" aria-hidden="true"></i> <?= limpar($proxima['observacoes']) ?></div>
            <?php endif; ?>
          </div>
          <?= emblema($proxima['status'] ?? '—') ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Consultas futuras -->
    <?php if (!empty($futuras)): ?>
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Consultas Agendadas</h3>
        <span style="font-size:13px;color:var(--texto-secundario);"><?= count($futuras) ?> consulta(s)</span>
      </div>
      <div class="cartao-corpo" style="padding:0;">
        <table class="tabela-dados" style="width:100%">
          <thead><tr><th>Data</th><th>Hora</th><th>Procedimento</th><th>Dentista</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
          <?php foreach ($futuras as $c): ?>
          <tr>
            <td style="font-weight:600;"><?= date('d/m/Y', strtotime($c['data'])) ?></td>
            <td><?= limpar($c['hora'] ?? '—') ?></td>
            <td><?= limpar($c['procedimento']) ?></td>
            <td><?= limpar($c['dentista'] ?? '—') ?></td>
            <td><?= emblema($c['status'] ?? '—') ?></td>
            <td>
              <?php if (in_array($c['status'], ['Agendado','Confirmado'], true) && !$c['pago']): ?>
              <form method="POST" data-confirmar="Cancelar a consulta de <?= limpar(date('d/m/Y', strtotime($c['data'])).' às '.$c['hora']) ?>?">
                <?= csrfCampo() ?>
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-perigo btn-pequeno" name="cancelar_consulta">Cancelar</button>
              </form>
              <?php elseif ($c['pago']): ?><span class="dica-campo">Paga: para cancelar, fale com a recepção.</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Histórico -->
    <?php if (!empty($passadas)): ?>
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-folder2-open" aria-hidden="true"></i> Histórico</h3>
        <span style="font-size:13px;color:var(--texto-secundario);"><?= count($passadas) ?> consulta(s)</span>
      </div>
      <div class="cartao-corpo" style="padding:0;">
        <table class="tabela-dados" style="width:100%">
          <thead><tr><th>Data</th><th>Procedimento</th><th>Dentista</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($passadas) as $c): ?>
          <tr>
            <td style="font-weight:600;"><?= date('d/m/Y', strtotime($c['data'])) ?></td>
            <td><?= limpar($c['procedimento']) ?></td>
            <td><?= limpar($c['dentista'] ?? '—') ?></td>
            <td><?= emblema($c['status'] ?? 'Concluída') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="cartao">
      <div class="cartao-corpo" style="text-align:center;padding:48px 20px;">
        <div style="font-size:48px;margin-bottom:12px;"><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i></div>
        <h3 style="font-size:16px;font-weight:700;margin-bottom:8px;">Nenhuma consulta encontrada</h3>
        <p style="color:var(--texto-secundario);margin-bottom:20px;">Você ainda não possui histórico de consultas.</p>
        <a href="cliente_agendamento.php" class="btn btn-primario">Agendar primeira consulta</a>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>
<?php include 'includes/a11y_bar.php'; ?>
