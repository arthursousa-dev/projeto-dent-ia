<?php
// painel do paciente — área pessoal com resumo das consultas
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('cliente');

$tituloPagina = 'Minha Área';
$nomeExibido   = $_SESSION['usuario'] ?? 'Paciente';
$emailSessao   = strtolower($_SESSION['email'] ?? '');

// Carrega agendamentos e filtra pelo paciente logado
$todosAgend = lerJson('agendamentos.json');
$todosAgend = array_map(function($a) {
    $a['procedimento'] = $a['servico'] ?? $a['procedimento'] ?? '—';
    return $a;
}, $todosAgend);
$meusAgend = array_values(array_filter($todosAgend, function($a) use ($nomeExibido) {
    return stripos($a['paciente'] ?? '', $nomeExibido) !== false;
}));
if (empty($meusAgend)) $meusAgend = array_slice($todosAgend, 0, 3);

$hoje    = date('Y-m-d');
$proxima = null;
foreach ($meusAgend as $a) {
    if (($a['data'] ?? '') >= $hoje) { $proxima = $a; break; }
}
$totalConsultas = count($meusAgend);
$concluidas     = count(array_filter($meusAgend, fn($a) => in_array($a['status'] ?? '', ['Concluída','Confirmado'])));

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1>Olá, <?= limpar(explode(" ", $nomeExibido)[0]) ?>! <i class="bi bi-emoji-smile" aria-hidden="true"></i></h1>
        <p><?= data_pt('l, d \d\e F \d\e Y') ?></p>
      </div>
      <a href="cliente_agendamento.php" class="btn btn-primario"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agendar Consulta</a>
    </div>

    <!-- resumo rápido do paciente -->
    <div class="grade-estatisticas" style="grid-template-columns:repeat(3,1fr);">
      <div class="cartao-estatistica">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>8</strong><span>Consultas realizadas</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>1</strong><span>Próxima consulta</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo" aria-hidden="true"><i class="bi bi-star-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>2 anos</strong><span>Paciente DENT IA</span></div>
      </div>
    </div>

    <!-- destaque da próxima consulta -->
    <div style="background:linear-gradient(135deg,var(--primario),var(--primario-claro));border-radius:var(--raio);padding:26px 30px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px;color:#fff;"
         role="region" aria-label="Sua próxima consulta agendada">
      <div>
        <p style="color:rgba(255,255,255,0.55);font-size:0.78rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:7px;">
          <i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Sua Próxima Consulta
        </p>
        <h2 style="font-family:var(--fonte-titulo);font-size:1.4rem;margin-bottom:5px;">
          Limpeza Dental
        </h2>
        <p style="color:rgba(255,255,255,0.7);font-size:0.9rem;">Dr. Carlos Mendes — Clínico Geral</p>
        <p style="color:var(--destaque);font-weight:700;margin-top:6px;">
          10 de Abril de 2026 às 08:00
        </p>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="cliente_consultas.php" class="btn btn-primario">Ver detalhes</a>
        <button class="btn btn-contorno"
                onclick="if(confirm('Deseja cancelar esta consulta?')) alert('Cancelamento enviado à recepção.')">
          Cancelar
        </button>
      </div>
    </div>

    <!-- histórico recente -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Histórico Recente</h3>
        <a href="cliente_consultas.php" class="cartao-link">Ver tudo →</a>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Histórico recente de consultas">
          <thead>
            <tr>
              <th>Data</th>
              <th>Procedimento</th>
              <th>Dentista</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>10/02/2026</td>
              <td>Clareamento</td>
              <td>Dr. Carlos Mendes</td>
              <td><?= emblema('Concluída') ?></td>
            </tr>
            <tr>
              <td>05/01/2026</td>
              <td>Limpeza Dental</td>
              <td>Dr. Carlos Mendes</td>
              <td><?= emblema('Concluída') ?></td>
            </tr>
            <tr>
              <td>20/11/2024</td>
              <td>Consulta de Rotina</td>
              <td>Dra. Ana Silva</td>
              <td><?= emblema('Concluída') ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- atalhos de contato com a clínica -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-telephone-fill" aria-hidden="true"></i> Fale com a Clínica</h3></div>
      <div class="cartao-corpo">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;">

          <?php
          // atalhos de contato — organizado em array pra facilitar manutenção
          $atalhos = [
              ['<i class="bi bi-telephone-fill" aria-hidden="true"></i>', 'Ligar',    '(67) 3000-0000',     'tel:+556730000000'],
              ['<i class="bi bi-envelope-fill" aria-hidden="true"></i>', 'E-mail',   'contato@dentai.com', 'mailto:contato@dentai.com'],
              ['<i class="bi bi-calendar-event-fill" aria-hidden="true"></i>', 'Agendar',  'Marcar consulta',    'cliente_agendamento.php'],
          ];
          foreach ($atalhos as [$icone, $titulo, $descricao, $href]): ?>
          <a href="<?= $href ?>"
             style="display:flex;align-items:center;gap:12px;padding:16px;background:var(--fundo-campo);border:1px solid var(--borda);border-radius:var(--raio);text-decoration:none;color:var(--texto-principal);transition:border-color var(--transicao);"
             onmouseover="this.style.borderColor='var(--destaque)'"
             onmouseout="this.style.borderColor='var(--borda)'">
            <span style="font-size:1.5rem;" aria-hidden="true"><?= $icone ?></span>
            <div>
              <strong style="display:block;font-size:0.9rem;"><?= $titulo ?></strong>
              <span style="color:var(--texto-secundario);font-size:0.82rem;"><?= $descricao ?></span>
            </div>
          </a>
          <?php endforeach; ?>

        </div>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
