<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('cliente');
$tituloPagina = 'Minhas Consultas';
$emailSessao  = strtolower($_SESSION['email'] ?? '');

// Busca agendamentos do paciente logado filtrando pelo nome na sessão
$nomeUsuario = $_SESSION['usuario'] ?? '';
$todosAgend  = lerJson('agendamentos.json');

// Normaliza chave servico → procedimento
$todosAgend = array_map(function($a) {
    $a['procedimento'] = $a['servico'] ?? $a['procedimento'] ?? '—';
    return $a;
}, $todosAgend);

// Filtra apenas consultas do paciente logado (pelo nome ou id)
$minhasConsultas = array_values(array_filter($todosAgend, function($a) use ($nomeUsuario) {
    return stripos($a['paciente'] ?? '', $nomeUsuario) !== false;
}));

// Se não encontrar pelo nome, pega todas (modo demo)
if (empty($minhasConsultas) && !empty($todosAgend)) {
    $minhasConsultas = $todosAgend;
}

// Ordena por data e hora
usort($minhasConsultas, fn($a,$b) => ($a['data'].$a['hora']) <=> ($b['data'].$b['hora']));

// Separa próximas vs passadas
$hoje   = date('Y-m-d');
$futuras = array_values(array_filter($minhasConsultas, fn($a) => ($a['data']??'') >= $hoje));
$passadas = array_values(array_filter($minhasConsultas, fn($a) => ($a['data']??'') < $hoje));
$proxima = $futuras[0] ?? null;

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">
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
            <div style="font-size:12px;font-weight:600;text-transform:uppercase;"><?= data_pt('M', strtotime($proxima['data'])) ?></div>
          </div>
          <div style="flex:1;">
            <div style="font-size:18px;font-weight:700;color:var(--texto-principal);margin-bottom:4px;"><?= limpar($proxima['procedimento']) ?></div>
            <div style="font-size:14px;color:var(--texto-secundario);">
              <i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> <?= limpar($proxima['dentista'] ?? '—') ?> &nbsp;·&nbsp;
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
          <thead><tr><th>Data</th><th>Hora</th><th>Procedimento</th><th>Dentista</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($futuras as $c): ?>
          <tr>
            <td style="font-weight:600;"><?= date('d/m/Y', strtotime($c['data'])) ?></td>
            <td><?= limpar($c['hora'] ?? '—') ?></td>
            <td><?= limpar($c['procedimento']) ?></td>
            <td><?= limpar($c['dentista'] ?? '—') ?></td>
            <td><?= emblema($c['status'] ?? '—') ?></td>
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
