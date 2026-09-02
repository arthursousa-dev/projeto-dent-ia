<?php
// painel principal do dono — visão geral da clínica
session_start();
require_once 'includes/functions.php';
verificarSessao('dono'); // redireciona pro login se não for o dono

$tituloPagina = 'Painel do Gestor';
include 'includes/head.php';

// dados financeiros do mês atual e anterior pra calcular a variação
$faturamentoMes      = 28750;
$faturamentoAnterior = 24300;
$variacaoPercentual  = (($faturamentoMes - $faturamentoAnterior) / $faturamentoAnterior) * 100;
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

      <a href="dono_pacientes.php" class="cartao-estatistica" aria-label="284 pacientes ativos">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>284</strong><span>Pacientes ativos</span></div>
      </a>

      <a href="dono_relatorios.php" class="cartao-estatistica" aria-label="156 atendimentos no mês">
        <div class="icone-estatistica amarelo" aria-hidden="true"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>156</strong><span>Atendimentos no mês</span></div>
      </a>

      <a href="dono_dentistas.php" class="cartao-estatistica" aria-label="4 dentistas ativos">
        <div class="icone-estatistica verde-agua" aria-hidden="true"><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>4</strong><span>Dentistas ativos</span></div>
      </a>
    </div>

    <!-- Gráfico e ranking -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">

      <!-- gráfico de barras do faturamento -->
      <div class="cartao">
        <div class="cartao-cabecalho">
          <h3><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Faturamento — Últimos 6 meses</h3>
          <a href="dono_faturamento.php" class="cartao-link">Ver detalhes →</a>
        </div>
        <div class="cartao-corpo">
          <?php
          // dados do gráfico — mês, valor em mil, altura percentual
          $dadosGrafico = [
              ['Out', 18, 55],
              ['Nov', 21, 65],
              ['Dez', 25, 78],
              ['Jan', 22, 68],
              ['Fev', 24, 74],
              ['Mar', 29, 100], // mês atual
          ];
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
          <a href="dono_dentistas.php" class="cartao-link">Ver todos →</a>
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
        <h3><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> Procedimentos Mais Realizados — <?= data_pt('F') ?></h3>
        <a href="dono_relatorios.php" class="cartao-link">Relatório completo →</a>
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
            $procedimentosMes = [
                ['Limpeza Dental',           48, 'R$ 4.800', '30,8%'],
                ['Ortodontia (Manutenção)',   35, 'R$ 7.000', '22,4%'],
                ['Restauração',              28, 'R$ 5.600', '17,9%'],
                ['Clareamento',              22, 'R$ 6.600', '14,1%'],
                ['Extração',                 15, 'R$ 3.000', '9,6%'],
                ['Tratamento de Canal',       8, 'R$ 1.750', '5,1%'],
            ];
            foreach ($procedimentosMes as [$nome, $quantidade, $receita, $percentual]): ?>
            <tr>
              <td><strong><?= $nome ?></strong></td>
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
        <a href="dono_pacientes.php" class="cartao-link">Ver todos →</a>
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
            <?php foreach (array_slice($listaAgendamentos, 0, 5) as $agendamento): ?>
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
