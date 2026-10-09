<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');
$tituloPagina = 'Faturamento';

$fat        = lerJsonObjeto('faturamento.json');
$mesAtual   = $fat['mes_atual']       ?? [];
$historico  = $fat['historico_mensal']?? [];
$transacoes = $fat['transacoes']      ?? [];

$sucesso=''; $erro='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (isset($_POST['salvar'])) registrarLancamento($_POST);
        elseif (isset($_POST['receber'])) confirmarPagamento((int)$_POST['id'],(int)$_POST['forma']);
        $sucesso='Registro financeiro salvo.';
    } catch (Throwable $e) { $erro=mensagemOperacao($e); }
    $fat=lerJsonObjeto('faturamento.json');
    $mesAtual=$fat['mes_atual']; $historico=$fat['historico_mensal']; $transacoes=$fat['transacoes'];
}
$pdo=\App\Config\Database::getConnection();
$categorias=$pdo->query('SELECT * FROM categoria_lancamento ORDER BY tipo,nome')->fetchAll();
$formas=$pdo->query('SELECT * FROM forma_pagamento ORDER BY nome')->fetchAll();
$pendentes=array_values(array_filter(lerJson('agendamentos.json'),fn($a)=>!$a['pago'] && $a['status']!=='Cancelado'));
// Valores calculados
$receita   = $mesAtual['receita_total']    ?? 0;
$despesas  = $mesAtual['despesas_total']   ?? 0;
$lucro     = $mesAtual['lucro']            ?? ($receita - $despesas);
$procMes   = $mesAtual['procedimentos_realizados'] ?? 0;
$ticketMed = $mesAtual['ticket_medio']     ?? ($procMes ? $receita / $procMes : 0);
$crescimento = $mesAtual['crescimento_percentual'] ?? 0;

// Máximo para barras do gráfico
$maxHistorico = !empty($historico) ? max(array_column($historico,'receita')) : 1;
$maxHistorico = $maxHistorico ?: 1;

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-cash-coin" aria-hidden="true"></i> Faturamento</h1><p>Controle financeiro — <?= data_pt('F \d\e Y') ?></p></div>
      <a href="dono_relatorios.php" class="btn btn-primario"><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i> Relatórios</a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?><div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div><?php endif; ?>

    <!-- KPIs -->
    <div class="grade-estatisticas" style="margin-bottom:20px;">
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-cash-coin" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong>R$ <?= number_format($receita,0,',','.') ?></strong>
          <span>Receita do mês</span>
          <?php if ($crescimento): ?>
            <span style="color:var(--sucesso);font-size:12px;font-weight:600;">▲ <?= number_format($crescimento,1,',','.') ?>% vs mês anterior</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-graph-down-arrow" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong>R$ <?= number_format($despesas,0,',','.') ?></strong>
          <span>Despesas do mês</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong>R$ <?= number_format($lucro,0,',','.') ?></strong>
          <span>Lucro líquido</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-bullseye" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong>R$ <?= number_format($ticketMed,2,',','.') ?></strong>
          <span>Ticket médio dos atendimentos concluídos</span>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

      <!-- Gráfico histórico -->
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-bar-chart-fill" aria-hidden="true"></i> Receita — Últimos <?= count($historico) ?> meses</h3></div>
        <div class="cartao-corpo">
          <?php if (empty($historico)): ?>
            <p style="color:var(--texto-fraco);text-align:center;padding:20px;">Sem dados históricos.</p>
          <?php else: ?>
          <div class="grafico-barras" role="img" aria-label="Gráfico de receita mensal">
            <?php foreach ($historico as $mes): ?>
            <?php $pct = round(($mes['receita'] / $maxHistorico) * 100); ?>
            <div class="coluna-grafico">
              <span class="valor-barra" aria-hidden="true">
                R$<?= number_format($mes['receita']/1000,0,',','.') ?>k
              </span>
              <div class="barra-grafico <?= $mes === end($historico) ? 'atual' : '' ?>"
                   style="height:<?= max(8,$pct) ?>%"
                   role="presentation"
                   title="<?= limpar($mes['mes']) ?>: R$ <?= number_format($mes['receita'],2,',','.') ?>">
              </div>
              <span class="rotulo-barra"><?= limpar(explode('/',$mes['mes'])[0]) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Por plano -->
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-hospital-fill" aria-hidden="true"></i> Receita por Convênio</h3></div>
        <div class="cartao-corpo">
          <?php
          $porPlano = $mesAtual['por_plano'] ?? [];
          $totalPlano = array_sum($porPlano) ?: 1;
          ?>
          <?php if (empty($porPlano)): ?>
            <p style="color:var(--texto-fraco);text-align:center;padding:20px;">Sem dados de convênios.</p>
          <?php else: ?>
          <?php foreach ($porPlano as $plano => $valor): ?>
          <?php $pct = round(($valor / $totalPlano) * 100); ?>
          <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px;">
              <span style="font-weight:600;"><?= limpar($plano) ?></span>
              <span style="color:var(--destaque);font-weight:700;">R$ <?= number_format($valor,0,',','.') ?> <small style="color:var(--texto-fraco);">(<?= $pct ?>%)</small></span>
            </div>
            <div style="height:8px;background:var(--borda);border-radius:4px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--destaque);border-radius:4px;transition:width .6s ease;"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Por dentista -->
    <?php $porDentista = $mesAtual['por_dentista'] ?? []; ?>
    <?php if (!empty($porDentista)): ?>
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho"><h3><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> Produção por Dentista</h3></div>
      <div class="cartao-corpo">
        <?php $maxDentista = max(array_column($porDentista,'receita')) ?: 1; ?>
        <?php foreach ($porDentista as $d): ?>
        <?php $pct = round(($d['receita'] / $maxDentista) * 100); ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
          <div class="avatar avatar-pequeno destaque" style="flex-shrink:0;"><?= iniciais($d['nome']) ?></div>
          <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">
              <span style="font-weight:600;"><?= limpar($d['nome']) ?></span>
              <span style="color:var(--destaque);font-weight:700;">R$ <?= number_format($d['receita'],0,',','.') ?> <small style="color:var(--texto-fraco);">(<?= $d['procedimentos'] ?> proc.)</small></span>
            </div>
            <div style="height:7px;background:var(--borda);border-radius:4px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--destaque);border-radius:4px;"></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Lançamentos -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Últimos Lançamentos</h3>
        <span style="font-size:13px;color:var(--texto-secundario);"><?= count($transacoes) ?> registros</span>
      </div>
      <div class="cartao-corpo" style="padding:0;">
        <table class="tabela-dados" style="width:100%">
          <thead><tr>
            <th>Data</th><th>Descrição</th><th>Tipo</th><th>Valor</th>
          </tr></thead>
          <tbody>
          <?php if (empty($transacoes)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--texto-fraco);padding:24px;">Nenhum lançamento registrado.</td></tr>
          <?php else: ?>
          <?php foreach (array_reverse($transacoes) as $t): ?>
          <tr>
            <td><?= limpar(isset($t['data']) ? date('d/m/Y', strtotime($t['data'])) : '—') ?></td>
            <td><?= limpar($t['descricao'] ?? '—') ?></td>
            <td><?= emblema($t['tipo'] ?? '—') ?></td>
            <td style="font-weight:700;color:<?= ($t['tipo']??'') === 'Receita' ? 'var(--sucesso)' : 'var(--perigo)' ?>;">
              <?= ($t['tipo']??'') === 'Receita' ? '+' : '-' ?>R$ <?= number_format($t['valor'] ?? 0,2,',','.') ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao-cabecalho"><h3>Consultas a receber</h3><span>R$ <?= number_format($mesAtual['a_receber'],2,',','.') ?> no mês</span></div>
      <div class="tabela-wrapper"><table><thead><tr><th>Paciente</th><th>Consulta</th><th>Valor</th><th>Recebimento</th></tr></thead><tbody>
      <?php foreach ($pendentes as $a): ?><tr>
        <td><?= limpar($a['paciente']) ?></td><td><?= limpar($a['data'].' '.$a['servico']) ?></td><td>R$ <?= number_format($a['valor'],2,',','.') ?></td>
        <td><form method="POST"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $a['id'] ?>">
          <select name="forma"><?php foreach ($formas as $f): ?><option value="<?= $f['id'] ?>"><?= limpar($f['nome']) ?></option><?php endforeach; ?></select>
          <button class="btn btn-primario btn-pequeno" name="receber">Confirmar pagamento</button></form></td>
      </tr><?php endforeach; ?>
      <?php if (!$pendentes): ?><tr><td colspan="4">Nenhuma consulta pendente.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <!-- Novo lançamento -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-plus-circle-fill" aria-hidden="true"></i> Novo Lançamento</h3></div>
      <div class="cartao-corpo">
        <form method="POST" novalidate>
          <?= csrfCampo() ?>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="fat-tipo">Tipo</label>
              <select id="fat-tipo" name="tipo"
                style="width:100%;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pequeno);font-family:var(--fonte);font-size:14px;color:var(--texto-principal);background:var(--fundo-campo);">
                <option>Receita</option><option>Despesa</option>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="fat-val">Valor (R$)</label>
              <input type="number" id="fat-val" name="valor" min="0" step="0.01" placeholder="0,00">
            </div>
          </div>
          <div class="grupo-campo"><label for="fat-categoria">Categoria</label><select id="fat-categoria" name="id_categoria"><option value="">Categoria padrão do tipo</option>
          <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>"><?= limpar($c['tipo'].' — '.$c['nome']) ?></option><?php endforeach; ?></select></div>
          <div class="grupo-campo">
            <label for="fat-desc">Descrição</label>
            <input type="text" id="fat-desc" name="descricao" placeholder="Ex: Limpeza — Maria Oliveira">
          </div>
          <button type="submit" name="salvar" class="btn btn-primario"><i class="bi bi-save-fill" aria-hidden="true"></i> Registrar Lançamento</button>
        </form>
      </div>
    </div>

  </main>
</div>
<?php include 'includes/a11y_bar.php'; ?>
