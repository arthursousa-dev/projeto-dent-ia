<?php
// painel do paciente — área pessoal com resumo das consultas
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('cliente');

$tituloPagina = 'Minha Área';
$nomeExibido   = $_SESSION['usuario'] ?? 'Paciente';
$emailSessao   = strtolower($_SESSION['email'] ?? '');

$erro='';$sucesso='';
if (isset($_POST['cancelar_consulta'])) {
    try { alterarStatusAgenda((int)$_POST['id'],'Cancelado');$sucesso='Consulta cancelada.'; }
    catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$p=pacienteSessao();
$meusAgend=array_values(array_filter(lerJson('agendamentos.json'),fn($a)=>$a['id_paciente']===($p['id'] ?? 0)));
$meusAgend=array_map(function($a){$a['procedimento']=$a['servico'];return $a;},$meusAgend);
$hoje    = date('Y-m-d');
$proxima = null;
foreach ($meusAgend as $a) {
    if (($a['data'] ?? '') >= $hoje && in_array($a['status'],['Agendado','Confirmado','Aguardando'],true)) { $proxima = $a; break; }
}
$totalConsultas = count($meusAgend);
$concluidas     = count(array_filter($meusAgend, fn($a) => ($a['status'] ?? '')==='Concluída'));

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?><div class="alerta alerta-sucesso"><?= limpar($sucesso) ?></div><?php endif; ?>
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
        <div class="info-estatistica"><strong><?= $concluidas ?></strong><span>Consultas realizadas</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= count(array_filter($meusAgend,fn($a)=>$a['data']>=date('Y-m-d') && in_array($a['status'],['Agendado','Confirmado'],true))) ?></strong><span>Consultas futuras</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo" aria-hidden="true"><i class="bi bi-star-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= !empty($p['criado_em']) ? (new DateTime($p['criado_em']))->diff(new DateTime())->y.' anos' : '—' ?></strong><span>Paciente DENT IA</span></div>
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
          <?= limpar($proxima['procedimento'] ?? 'Nenhuma consulta futura') ?>
        </h2>
        <p style="color:rgba(255,255,255,0.7);font-size:0.9rem;"><?= limpar($proxima['dentista'] ?? 'Agende sua próxima consulta') ?></p>
        <p style="color:var(--destaque);font-weight:700;margin-top:6px;">
          <?= $proxima ? limpar(date('d/m/Y',strtotime($proxima['data'])).' às '.$proxima['hora']) : '—' ?>
        </p>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="cliente_consultas.php" class="btn btn-primario">Ver detalhes</a>
        <?php if ($proxima && in_array($proxima['status'],['Agendado','Confirmado'],true) && !$proxima['pago']): ?>
        <form method="POST" onsubmit="return confirm('Cancelar esta consulta?')"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $proxima['id'] ?>"><button class="btn btn-perigo" name="cancelar_consulta">Cancelar consulta</button></form><?php endif; ?>
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
            <?php $passadas=array_values(array_filter($meusAgend,fn($a)=>$a['status']==='Concluída'));usort($passadas,fn($a,$b)=>strcmp($b['data'],$a['data']));foreach (array_slice($passadas,0,5) as $a): ?>
            <tr><td><?= limpar(date('d/m/Y',strtotime($a['data']))) ?></td><td><?= limpar($a['procedimento']) ?></td><td><?= limpar($a['dentista']) ?></td><td><?= emblema($a['status']) ?></td></tr>
            <?php endforeach; ?>
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
          $cl=lerJsonObjeto('clinica.json');
          $atalhos = [
              ['<i class="bi bi-telephone-fill" aria-hidden="true"></i>', 'Ligar',    $cl['telefone'], 'tel:+55'.soDigitos($cl['telefone'])],
              ['<i class="bi bi-envelope-fill" aria-hidden="true"></i>', 'E-mail',   $cl['email'], 'mailto:'.$cl['email']],
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
