<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');
$tituloPagina = 'Faturamento';

$periodo=(string)($_GET['mes'] ?? '') ?: date('Y-m');
$dt=DateTimeImmutable::createFromFormat('!Y-m',$periodo);
$erro='';
if (!$dt || $dt->format('Y-m')!==$periodo || $periodo<'2000-01' || $periodo>'2100-12') { $periodo=date('Y-m');$erro='Mês inválido: use o formato AAAA-MM, entre 2000-01 e 2100-12. Mostrando o mês atual.'; }
$sucesso='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (isset($_POST['salvar'])) registrarLancamento($_POST);
        elseif (isset($_POST['receber'])) confirmarPagamento(idParametro($_POST['id'] ?? 0),(int)($_POST['forma'] ?? 0));
        else throw new InvalidArgumentException('Ação financeira inválida.');
        $destino=isset($_POST['salvar']) ? substr((string)($_POST['data'] ?? date('Y-m-d')),0,7) : $periodo;
        redirecionar('dono_faturamento.php?mes='.urlencode($destino).'&historico='.$mesesHistorico, isset($_POST['salvar']) ? 'Lançamento salvo.' : 'Pagamento registrado.');
    } catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$mesesHistorico=in_array((int)($_GET['historico'] ?? 6),[6,12],true) ? (int)($_GET['historico'] ?? 6) : 6;
$fat=resumoFinanceiro(\App\Config\Database::getConnection(),$periodo.'-01',$mesesHistorico);
$mesAtual=$fat['mes_atual'];$historico=$fat['historico_mensal'];$transacoes=$fat['transacoes'];
$inicio=$periodo.'-01';$fim=date('Y-m-d',strtotime($inicio.' +1 month'));
$pdo=\App\Config\Database::getConnection();
$categorias=$pdo->query('SELECT * FROM categoria_lancamento ORDER BY tipo,nome')->fetchAll();
$formas=$pdo->query('SELECT * FROM forma_pagamento ORDER BY nome')->fetchAll();
$pendentes=consultasAReceber($pdo,$inicio,$fim);
$estornos=estornosDoPeriodo($pdo,$inicio,$fim);
// Valores calculados
$receita   = $mesAtual['receita_total'];
$despesas  = $mesAtual['despesas_total'];
$lucro     = $mesAtual['lucro'];
$procMes   = $mesAtual['procedimentos_realizados'];
$ticketMed = $mesAtual['ticket_medio'];
$crescimento = $mesAtual['crescimento_percentual'];

$margem    = $receita>0 ? $lucro/$receita*100 : 0;

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-cash-coin" aria-hidden="true"></i> Faturamento</h1><p>Controle financeiro — <?= limpar($mesAtual['mes']) ?></p></div>
      <a href="dono_relatorios.php" class="btn btn-primario"><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i> Relatórios</a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?><div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div><?php endif; ?>

    <form method="GET" style="margin-bottom:20px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="grupo-campo" style="margin-bottom:0;min-width:220px;"><label for="fat-mes">Mês de referência</label><input id="fat-mes" type="month" name="mes" value="<?= limpar($periodo) ?>" min="2000-01" max="2100-12" required></div>
      <div class="grupo-campo" style="margin-bottom:0;min-width:160px;"><label for="fat-historico">Histórico</label>
        <select id="fat-historico" name="historico"><?php foreach ([6,12] as $n): ?><option value="<?= $n ?>" <?= $n===$mesesHistorico ? 'selected' : '' ?>>Últimos <?= $n ?> meses</option><?php endforeach; ?></select></div>
      <button class="btn btn-primario">Consultar</button>
    </form>
    <?php if (!$transacoes): ?><div class="alerta" role="status">Nenhum recebimento ou despesa neste mês. Consulte outro período ou registre um lançamento. Consultas pendentes entram na receita após confirmar o pagamento.</div><?php endif; ?>
    <!-- KPIs -->
    <div class="grade-estatisticas grade-3" style="margin-bottom:20px;">
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde"><i class="bi bi-cash-coin" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= moeda($receita) ?></strong>
          <span>Receita do mês</span>
          <?php if ($crescimento): ?>
            <span class="emblema-variacao <?= $crescimento>0 ? 'subiu' : 'caiu' ?>"><?= $crescimento>0 ? '▲' : '▼' ?> <?= number_format(abs($crescimento),1,',','.') ?>% vs mês anterior</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-graph-down-arrow" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= moeda($despesas) ?></strong>
          <span>Despesas do mês</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica azul"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong style="<?= $lucro<0 ? 'color:var(--perigo)' : '' ?>"><?= moeda($lucro) ?></strong>
          <span><?= $lucro<0 ? 'Prejuízo do mês' : 'Lucro líquido' ?><?= $receita>0 ? ' · margem '.number_format($margem,1,',','.').'%' : '' ?></span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= moeda($mesAtual['a_receber']) ?></strong>
          <span>A receber (<?= count($pendentes) ?> consulta<?= count($pendentes)===1 ? '' : 's' ?>)</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-bullseye" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= moeda($ticketMed) ?></strong>
          <span>Ticket médio por atendimento</span>
        </div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica"><i class="bi bi-clipboard2-check" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong><?= $procMes ?></strong>
          <span>Atendimentos concluídos</span>
        </div>
      </div>
    </div>

    <div class="grade-financeiro">

      <!-- Gráfico histórico -->
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-bar-chart-fill" aria-hidden="true"></i> Receita x despesas — últimos <?= count($historico) ?> meses</h3></div>
        <div class="cartao-corpo">
          <?php include 'includes/grafico_financeiro.php'; ?>
        </div>
      </div>

      <!-- Por plano -->
      <div class="cartao">
        <div class="cartao-cabecalho"><h3><i class="bi bi-hospital-fill" aria-hidden="true"></i> Receita por plano do paciente</h3></div>
        <div class="cartao-corpo">
          <?php
          $porPlano = $mesAtual['por_plano'];
          $totalPlano = array_sum($porPlano) ?: 1;
          ?>
          <?php if (empty($porPlano)): ?>
            <p style="color:var(--texto-fraco);text-align:center;padding:20px;">Nenhum pagamento confirmado neste mês.</p>
          <?php else: ?>
          <?php foreach ($porPlano as $plano => $valor): ?>
          <?php $pct = round(($valor / $totalPlano) * 100); ?>
          <div style="margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px;">
              <span style="font-weight:600;"><?= limpar($plano) ?></span>
              <span style="color:var(--destaque);font-weight:700;"><?= moeda((float)($valor)) ?> <small style="color:var(--texto-fraco);">(<?= $pct ?>%)</small></span>
            </div>
            <div style="height:8px;background:var(--borda);border-radius:4px;overflow:hidden;">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--destaque);border-radius:4px;transition:width .6s ease;"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
          <p style="font-size:12px;color:var(--texto-secundario);">Receitas avulsas: <?= moeda((float)($mesAtual['receitas_avulsas'])) ?>. Não atribuídas a planos.</p>
        </div>
      </div>

    </div>

    <!-- Por dentista -->
    <?php $porDentista = $mesAtual['por_dentista'] ?? []; ?>
    <?php if (!empty($porDentista)): ?>
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho"><h3><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Recebimentos e atendimentos por dentista</h3></div>
      <div class="cartao-corpo">
        <?php $maxDentista = max(array_column($porDentista,'receita')) ?: 1; ?>
        <?php foreach ($porDentista as $d): ?>
        <?php $pct = round(($d['receita'] / $maxDentista) * 100); ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
          <div class="avatar avatar-pequeno destaque" style="flex-shrink:0;"><?= iniciais($d['nome']) ?></div>
          <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">
              <span style="font-weight:600;"><?= limpar($d['nome']) ?></span>
              <span style="color:var(--destaque);font-weight:700;"><?= moeda((float)($d['receita'])) ?> <small style="color:var(--texto-fraco);">(<?= $d['procedimentos'] ?> proc.)</small></span>
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
        <h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Lançamentos do mês</h3>
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
              <?= ($t['tipo']??'') === 'Receita' ? '+' : '' ?><?= moeda((($t['tipo']??'') === 'Receita' ? 1 : -1)*(float)($t['valor'] ?? 0)) ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($estornos): ?>
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-arrow-return-left" aria-hidden="true"></i> Estornos do mês</h3><span><?= count($estornos) ?> estorno(s)</span></div>
      <div class="tabela-wrapper"><table aria-label="Estornos do mês"><thead><tr><th>Consulta</th><th>Paciente</th><th>Valor</th><th>Pago em</th><th>Estornado em</th><th>Por</th><th>Motivo</th></tr></thead><tbody>
      <?php foreach ($estornos as $e): ?><tr>
        <td>#<?= (int)$e['id_agendamento'] ?></td><td><?= limpar($e['paciente']) ?></td><td><?= moeda((float)$e['valor']) ?></td>
        <td><?= limpar($e['pago_em']) ?></td><td><?= limpar($e['estornado_em']) ?></td><td><?= limpar($e['usuario']) ?></td><td><?= limpar($e['motivo']) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
    <?php endif; ?>

    <div class="cartao">
      <div class="cartao-cabecalho"><h3>Consultas a receber</h3><span><?= moeda((float)($mesAtual['a_receber'])) ?> no mês</span></div>
      <div class="tabela-wrapper"><table><thead><tr><th>Paciente</th><th>Consulta</th><th>Valor</th><th>Recebimento</th></tr></thead><tbody>
      <?php foreach ($pendentes as $a): ?><tr>
        <td><?= limpar($a['paciente']) ?></td><td><?= limpar(date('d/m/Y',strtotime($a['data'])).' '.$a['hora'].' — '.$a['servico']) ?></td><td><?= moeda((float)($a['valor'])) ?></td>
        <td><form method="POST" data-confirmar="Confirmar o pagamento desta consulta?"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $a['id'] ?>">
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
        <p style="margin-bottom:16px;color:var(--texto-secundario);">Registre receitas avulsas e despesas. Para receber uma consulta, confirme o pagamento na lista acima.</p>
        <form method="POST">
          <?= csrfCampo() ?>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="fat-tipo">Tipo</label>
              <select id="fat-tipo" name="tipo"
                style="width:100%;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pequeno);font-family:var(--fonte);font-size:14px;color:var(--texto-principal);background:var(--fundo-campo);">
                <?php foreach (['Receita','Despesa'] as $tipoOpcao): ?><option <?= ($_POST['tipo'] ?? '')===$tipoOpcao ? 'selected' : '' ?>><?= $tipoOpcao ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="fat-val">Valor (R$)</label>
              <input type="text" id="fat-val" name="valor" inputmode="decimal" placeholder="0,00" required value="<?= limpar((string)($_POST['valor'] ?? '')) ?>">
            </div>
          </div>
          <div class="grupo-campo"><label for="fat-data">Data do lançamento</label><input type="date" id="fat-data" name="data" value="<?= limpar($_POST['data'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required></div>
          <div class="grupo-campo"><label for="fat-categoria">Categoria</label><select id="fat-categoria" name="id_categoria"><option value="">Categoria padrão do tipo</option>
          <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" data-tipo="<?= limpar($c['tipo']) ?>" <?= (string)($_POST['id_categoria'] ?? '')===(string)$c['id'] ? 'selected' : '' ?>><?= limpar($c['tipo'].' — '.$c['nome']) ?></option><?php endforeach; ?></select></div>
          <div class="grupo-campo">
            <label for="fat-desc">Descrição</label>
            <input type="text" id="fat-desc" name="descricao" placeholder="Ex: Venda de kit de higiene" required maxlength="200" value="<?= limpar((string)($_POST['descricao'] ?? '')) ?>">
          </div>
          <button type="submit" name="salvar" class="btn btn-primario"><i class="bi bi-save-fill" aria-hidden="true"></i> Registrar Lançamento</button>
        </form>
      </div>
    </div>

  </main>
</div>
<?php include 'includes/a11y_bar.php'; ?>

<script>
const tipoLancamento=document.getElementById('fat-tipo');
const categoriaLancamento=document.getElementById('fat-categoria');
function atualizarCategorias() {
    for (const opcao of categoriaLancamento.options) {
        opcao.hidden=!!opcao.dataset.tipo && opcao.dataset.tipo!==tipoLancamento.value;
        opcao.disabled=opcao.hidden;
    }
    if (categoriaLancamento.selectedOptions[0]?.disabled) categoriaLancamento.value='';
}
tipoLancamento.addEventListener('change',atualizarCategorias);atualizarCategorias();

</script>
