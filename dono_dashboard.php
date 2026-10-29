<?php
// painel principal do dono — visão geral da clínica
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');carregarListas(['agendamentos', 'dentistas']); // redireciona pro login se não for o dono

$tituloPagina = 'Painel do Gestor';
include 'includes/head.php';

$pdoDash = \App\Config\Database::getConnection();
$fat = lerJsonObjeto('faturamento.json');
$hoje = new DateTime();
$mesAtual = (int)$hoje->format('n'); $anoAtual=(int)$hoje->format('Y');
$faturamentoMes=$fat['mes_atual']['receita_total'];
$variacaoPercentual=$fat['mes_atual']['crescimento_percentual'];
// contadores reais dos cards de estatística
$totalPacientesAtivos = (int) $pdoDash->query("SELECT COUNT(*) FROM pacientes WHERE status = 'ativo'")->fetchColumn();
$totalDentistasAtivos = (int) $pdoDash->query("SELECT COUNT(*) FROM dentistas WHERE status = 'ativo'")->fetchColumn();
$stmtAtendMes = $pdoDash->prepare(
    "SELECT COUNT(*) FROM agendamentos WHERE status='Concluída' AND date_trunc('month', data) = date_trunc('month', make_date(:ano, :mes, 1))"
);
$stmtAtendMes->execute([':ano' => $anoAtual, ':mes' => $mesAtual]);
$totalAtendimentosMes = (int) $stmtAtendMes->fetchColumn();
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1>Painel do Gestor <i class="bi bi-bar-chart-fill" aria-hidden="true"></i></h1>
        <p>Visão geral da clínica — <?= data_pt('F \d\e Y') ?></p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="dono_relatorios.php"  class="btn btn-escuro"><i class="bi bi-file-earmark-text-fill" aria-hidden="true"></i> Relatório</a>
        <a href="dono_faturamento.php" class="btn btn-primario"><i class="bi bi-cash-coin" aria-hidden="true"></i> Faturamento</a>
      </div>
    </div>

    <!-- Cartões de estatísticas -->
    <div class="grade-estatisticas">

      <a href="dono_faturamento.php" class="cartao-estatistica"
         aria-label="Faturamento do mês: R$ <?= number_format($faturamentoMes, 0, ',', '.') ?>">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-cash-coin" aria-hidden="true"></i></div>
        <div class="info-estatistica">
          <strong>R$ <?= number_format($faturamentoMes, 0, ',', '.') ?></strong>
          <span>Faturamento do mês</span>
          <!-- mostra se subiu ou caiu em relação ao mês anterior -->
          <span class="emblema-variacao <?= $variacaoPercentual >= 0 ? 'subiu' : 'caiu' ?>">
            <?= $variacaoPercentual >= 0 ? '▲' : '▼' ?>
            <?= number_format(abs($variacaoPercentual), 1) ?>% vs mês anterior
          </span>
        </div>
      </a>

      <a href="dono_pacientes.php" class="cartao-estatistica" aria-label="<?= $totalPacientesAtivos ?> pacientes ativos">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= $totalPacientesAtivos ?></strong><span>Pacientes ativos</span></div>
      </a>

      <a href="dono_relatorios.php" class="cartao-estatistica" aria-label="<?= $totalAtendimentosMes ?> atendimentos no mês">
        <div class="icone-estatistica amarelo" aria-hidden="true"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= $totalAtendimentosMes ?></strong><span>Atendimentos no mês</span></div>
      </a>

      <a href="dono_dentistas.php" class="cartao-estatistica" aria-label="<?= $totalDentistasAtivos ?> dentistas ativos">
        <div class="icone-estatistica verde-agua" aria-hidden="true"><i class="bi bi-person-badge-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= $totalDentistasAtivos ?></strong><span>Dentistas ativos</span></div>
      </a>
    </div>

    <!-- Gráfico e ranking -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">

      <!-- gráfico de barras do faturamento -->
      <div class="cartao">
        <div class="cartao-cabecalho">
          <h3><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Faturamento — Últimos 6 meses</h3>
          <a href="dono_faturamento.php" class="cartao-link">Ver detalhes</a>
        </div>
        <div class="cartao-corpo">
          <?php
          $maxReceita=max(1,...array_column($fat['historico_mensal'],'receita'));
          $dadosGrafico=array_map(fn($m)=>[explode('/',$m['mes'])[0],round($m['receita']/1000,2),max(1,round($m['receita']/$maxReceita*100))],$fat['historico_mensal']);
          ?>
          <div class="grafico-barras"
               role="img"
               aria-label="Gráfico de barras do faturamento dos últimos 6 meses">
            <?php foreach ($dadosGrafico as $indice => [$mes, $valor, $altura]):
              $eAtual = $indice === count($dadosGrafico) - 1; ?>
            <div class="barra-item">
              <div class="barra-valor">R$<?= $valor ?>k</div>
              <div class="barra <?= $eAtual ? 'atual' : '' ?>"
                   style="height:<?= $altura ?>%;"
                   title="<?= $mes ?>: R$ <?= $valor ?>k">
              </div>
              <div class="barra-label">
                <?= $eAtual ? '<strong>' . $mes . '</strong>' : $mes ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ranking de atendimentos por dentista -->
      <div class="cartao">
        <div class="cartao-cabecalho">
          <h3><i class="bi bi-trophy-fill" aria-hidden="true"></i> Atendimentos por Dentista</h3>
          <a href="dono_dentistas.php" class="cartao-link">Ver todos</a>
        </div>
        <div class="cartao-corpo" style="padding:12px 20px;">
          <?php
          // calcula o máximo pra definir a proporção da barra de progresso
          $colAtend = array_column($listaDentistas, 'atendimentos');
          $maximo = !empty($colAtend) ? max($colAtend) : 1;
          $maximo = $maximo ?: 1;
          foreach ($listaDentistas as $dentista):
            $percentual = round(($dentista['atendimentos'] / $maximo) * 100);
          ?>
          <a href="dono_dentistas.php"
             class="linha-dentista"
             style="text-decoration:none;display:flex;"
             aria-label="<?= limpar($dentista['nome']) ?>, <?= $dentista['atendimentos'] ?> atendimentos">
            <div class="avatar avatar-pequeno destaque" aria-hidden="true">
              <?= iniciais($dentista['nome']) ?>
            </div>
            <div class="dentista-info">
              <strong><?= limpar($dentista['nome']) ?></strong>
              <span><?= limpar($dentista['especialidade']) ?></span>
            </div>
            <div class="progresso-trilha"
                 role="progressbar"
                 aria-valuenow="<?= $dentista['atendimentos'] ?>"
                 aria-valuemax="<?= $maximo ?>"
                 aria-label="<?= $dentista['atendimentos'] ?> atendimentos">
              <div class="progresso-preenchido" style="width:<?= $percentual ?>%;"></div>
            </div>
            <div class="dentista-contagem"><?= $dentista['atendimentos'] ?> at.</div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Procedimentos mais realizados -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Produção por procedimento — <?= data_pt('F') ?></h3>
        <a href="dono_relatorios.php" class="cartao-link">Relatório completo</a>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Procedimentos mais realizados no mês">
          <thead>
            <tr>
              <th scope="col">Procedimento</th>
              <th scope="col">Qtd.</th>
              <th scope="col">Receita</th>
              <th scope="col">% do Total</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $stmt=$pdoDash->prepare("SELECT p.nome,count(*) AS quantidade,COALESCE(sum(a.valor),0) AS receita
                FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento
                WHERE a.status='Concluída' AND date_trunc('month',a.data)=date_trunc('month',CURRENT_DATE)
                GROUP BY p.nome ORDER BY quantidade DESC,p.nome");
            $stmt->execute();
            $producao=$stmt->fetchAll();$qtdTotal=array_sum(array_column($producao,'quantidade'));
            $procedimentosMes=array_map(fn($p)=>[$p['nome'],(int)$p['quantidade'],'R$ '.number_format($p['receita'],2,',','.'),
                number_format($qtdTotal ? $p['quantidade']/$qtdTotal*100 : 0,1,',','.').'%'],$producao);
            foreach ($procedimentosMes as [$nome, $quantidade, $receita, $percentual]): ?>
            <tr>
              <td><strong><?= limpar($nome) ?></strong></td>
              <td><?= $quantidade ?></td>
              <td><?= $receita ?></td>
              <td><?= $percentual ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Últimos atendimentos -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-people-fill" aria-hidden="true"></i> Últimos Atendimentos</h3>
        <a href="dono_pacientes.php" class="cartao-link">Ver todos</a>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Últimos pacientes atendidos">
          <thead>
            <tr>
              <th>Paciente</th>
              <th>Procedimento</th>
              <th>Dentista</th>
              <th>Status</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php $ultimos=array_values(array_filter($listaAgendamentos,fn($a)=>$a['status']==='Concluída')); usort($ultimos,fn($a,$b)=>strcmp($b['data'].$b['hora'],$a['data'].$a['hora'])); foreach (array_slice($ultimos,0,5) as $agendamento): ?>
            <tr>
              <td><strong><?= limpar($agendamento['paciente']) ?></strong></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['dentista']) ?></td>
              <td><?= emblema($agendamento['status']) ?></td>
              <td>
                <a href="dono_pacientes.php" class="cartao-link" style="font-size:0.8rem;">Ver</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
