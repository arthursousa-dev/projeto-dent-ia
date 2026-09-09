<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');
$tituloPagina = 'Relatórios';

// Carrega dados dos JSONs para montar os relatórios
$fat       = lerJsonObjeto('faturamento.json');
$mesAtual  = $fat['mes_atual']        ?? [];
$historico = $fat['historico_mensal'] ?? [];
$agendamentos = lerJson('agendamentos.json');
$agendamentos = array_map(function($a) {
    $a['procedimento'] = $a['servico'] ?? $a['procedimento'] ?? '—';
    return $a;
}, $agendamentos);
$pacientes  = lerJson('pacientes.json');
$dentistas  = lerJson('dentistas.json');
$procedimentos = lerJson('procedimentos.json');

// Resumo gerado
$sucesso = '';
if (isset($_POST['gerar'])) {
    $tipo = limpar($_POST['tipo'] ?? 'geral');
    $sucesso = 'Relatório de ' . $tipo . ' compilado com dados do sistema em ' . data_pt('d/m/Y \à\s H:i') . '.';
}

// Cálculos para o relatório geral
$totalPacientes = count($pacientes);
$pacAtivos      = count(array_filter($pacientes, fn($p) => strtolower($p['status']??'') === 'ativo'));
$totalDentistas = count($dentistas);
$totalAgend     = count($agendamentos);
$agConcluidos   = count(array_filter($agendamentos, fn($a) => in_array($a['status']??'', ['Concluída','Confirmado'])));

// Contagem de procedimentos
$contProc = [];
foreach ($agendamentos as $a) {
    $proc = $a['procedimento'] ?? '—';
    $contProc[$proc] = ($contProc[$proc] ?? 0) + 1;
}
arsort($contProc);
$top5proc = array_slice($contProc, 0, 5, true);

// Receita total acumulada
$receitaTotal = array_sum(array_column($historico, 'receita'));

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Relatórios</h1><p>Visão analítica completa da clínica</p></div>
      <a href="dono_faturamento.php" class="btn btn-fantasma"><i class="bi bi-cash-coin" aria-hidden="true"></i> Faturamento</a>
    </div>

    <?php if ($sucesso): ?><div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div><?php endif; ?>

    <!-- KPIs Resumo -->
    <div class="grade-estatisticas" style="margin-bottom:20px;">
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= $pacAtivos ?> / <?= $totalPacientes ?></strong>
          <span>Pacientes ativos</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= $totalDentistas ?></strong>
          <span>Dentistas ativos</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= $agConcluidos ?> / <?= $totalAgend ?></strong>
          <span>Atendimentos concluídos</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-cash-coin" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong>R$ <?= number_format($receitaTotal, 0, ',', '.') ?></strong>
          <span>Receita acumulada (6 meses)</span>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

      <!-- Evolução mensal -->
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-bar-chart-fill" aria-hidden="true"></i> Evolução de Receita</h3></div>
        <div class="cartao-corpo">
          <?php if (!empty($historico)):
            $maxH = max(array_column($historico,'receita')) ?: 1; ?>
          <div class="grafico-barras">
            <?php foreach ($historico as $m): ?>
            <?php $pct = round(($m['receita'] / $maxH) * 100); ?>
            <div class="coluna-grafico">
              <span class="valor-barra">R$<?= number_format($m['receita']/1000,0,',','.') ?>k</span>
              <div class="barra-grafico <?= $m === end($historico) ? 'atual' : '' ?>"
                   style="height:<?= max(8,$pct) ?>%"
                   title="<?= limpar($m['mes']) ?>: R$ <?= number_format($m['receita'],2,',','.') ?>"></div>
              <span class="rotulo-barra"><?= limpar(explode('/',$m['mes'])[0]) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="color:var(--texto-fraco);text-align:center;padding:20px;">Sem histórico disponível.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top procedimentos -->
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-trophy-fill" aria-hidden="true"></i> Top Procedimentos</h3></div>
        <div class="cartao-corpo">
          <?php if (!empty($top5proc)):
            $maxP = max($top5proc) ?: 1; ?>
          <?php foreach ($top5proc as $nome => $qtd): ?>
          <?php $pct = round(($qtd / $maxP) * 100); ?>
          <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">
              <span style="font-weight:600;"><?= limpar($nome) ?></span>
              <span style="color:var(--destaque);font-weight:700;"><?= $qtd ?> vez(es)</span>
            </div>
            <div style="height:7px;background:var(--borda);border-radius:4px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--destaque);border-radius:4px;"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <p style="color:var(--texto-fraco);text-align:center;padding:20px;">Sem agendamentos registrados.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Produção por dentista -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho"><h3><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> Produção por Dentista — <?= limpar($mesAtual['mes'] ?? date('Y')) ?></h3></div>
      <div class="cartao-corpo" style="padding:0;">
        <table class="tabela-dados" style="width:100%">
          <thead><tr><th>Dentista</th><th>Especialidade</th><th>Atend./mês</th><th>Receita</th><th>Status</th></tr></thead>
          <tbody>
          <?php
          $porDent = $mesAtual['por_dentista'] ?? [];
          foreach ($dentistas as $d):
            $fat_d = null;
            foreach ($porDent as $pd) {
                if ($pd['id_dentista'] == $d['id']) { $fat_d = $pd; break; }
            }
          ?>
          <tr>
            <td><div style="display:flex;align-items:center;gap:8px;">
              <div class="avatar avatar-pequeno destaque"><?= iniciais($d['nome']) ?></div>
              <strong><?= limpar($d['nome']) ?></strong>
            </div></td>
            <td><?= limpar($d['especialidade'] ?? '—') ?></td>
            <td><strong><?= $fat_d ? $fat_d['procedimentos'] : ($d['atendimentos_mes'] ?? $d['atendimentos'] ?? 0) ?></strong></td>
            <td style="color:var(--sucesso);font-weight:700;">
              <?= $fat_d ? 'R$ '.number_format($fat_d['receita'],0,',','.') : '—' ?>
            </td>
            <td><?= emblema($d['status'] ?? 'Ativo') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pacientes por plano -->
    <?php
    $porPlano = [];
    foreach ($pacientes as $p) {
        $plano = $p['plano'] ?? 'Particular';
        $porPlano[$plano] = ($porPlano[$plano] ?? 0) + 1;
    }
    arsort($porPlano);
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-hospital-fill" aria-hidden="true"></i> Pacientes por Convênio</h3></div>
        <div class="cartao-corpo">
          <?php $maxPl = !empty($porPlano) ? max($porPlano) : 1; ?>
          <?php foreach ($porPlano as $plano => $qtd): ?>
          <?php $pct = round(($qtd / $totalPacientes) * 100); ?>
          <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">
              <span style="font-weight:600;"><?= limpar($plano) ?></span>
              <span style="color:var(--texto-secundario);"><?= $qtd ?> paciente(s) &nbsp;<small>(<?= $pct ?>%)</small></span>
            </div>
            <div style="height:7px;background:var(--borda);border-radius:4px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--primario-claro,#3182ce);border-radius:4px;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Status dos agendamentos -->
      <?php
      $porStatus = [];
      foreach ($agendamentos as $a) {
          $s = $a['status'] ?? 'Desconhecido';
          $porStatus[$s] = ($porStatus[$s] ?? 0) + 1;
      }
      arsort($porStatus);
      ?>
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agendamentos por Status</h3></div>
        <div class="cartao-corpo">
          <?php $maxSt = !empty($porStatus) ? max($porStatus) : 1; ?>
          <?php foreach ($porStatus as $status => $qtd): ?>
          <?php $pct = round(($qtd / $totalAgend) * 100); ?>
          <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">
              <span><?= emblema($status) ?></span>
              <span style="color:var(--texto-secundario);"><?= $qtd ?> (<?= $pct ?>%)</span>
            </div>
            <div style="height:7px;background:var(--borda);border-radius:4px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--destaque);border-radius:4px;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Gerar relatório -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i> Gerar Relatório</h3></div>
      <div class="cartao-corpo">
        <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
          <div class="grupo-campo" style="flex:1;min-width:200px;">
            <label for="rel-tipo">Tipo de relatório</label>
            <select id="rel-tipo" name="tipo"
              style="width:100%;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pequeno);font-family:var(--fonte);font-size:14px;color:var(--texto-principal);background:var(--fundo-campo);">
              <option>Financeiro Mensal</option>
              <option>Produção por Dentista</option>
              <option>Pacientes Ativos</option>
              <option>Agendamentos do Período</option>
              <option>Procedimentos Realizados</option>
            </select>
          </div>
          <button type="submit" name="gerar" class="btn btn-primario" style="padding:11px 24px;"><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i> Gerar Relatório</button>
        </form>
      </div>
    </div>

  </main>
</div>
<?php include 'includes/a11y_bar.php'; ?>
