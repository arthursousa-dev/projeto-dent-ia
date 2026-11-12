<?php
// Gráfico receita x despesas (barras) + lucro (linha). Espera $historico de resumoFinanceiro(); desenhado por js/grafico-financeiro.js.
$temHistorico = array_sum(array_column($historico,'receita'))+array_sum(array_column($historico,'despesas'))>0;
?>
  <?php if (!$temHistorico): ?>
    <p style="color:var(--texto-fraco);text-align:center;padding:20px;">Sem movimentação nos últimos <?= count($historico) ?> meses.</p>
  <?php else: ?>
  <div class="grafico-legenda" role="group" aria-label="Mostrar ou ocultar séries">
    <button type="button" class="legenda-item" data-serie="receita" aria-pressed="true"><span class="legenda-cor receita"></span>Receita</button>
    <button type="button" class="legenda-item" data-serie="despesas" aria-pressed="true"><span class="legenda-cor despesas"></span>Despesas</button>
    <button type="button" class="legenda-item" data-serie="lucro" aria-pressed="true"><span class="legenda-cor lucro"></span>Lucro</button>
  </div>
  <div class="grafico-financeiro" id="grafico-financeiro" role="img" aria-label="Receita, despesas e lucro mensais; os valores estão na tabela abaixo do gráfico"></div>
  <div class="grafico-dica" id="grafico-dica" hidden></div>
  <details class="grafico-tabela">
    <summary>Ver dados em tabela</summary>
    <div class="tabela-wrapper"><table><thead><tr><th>Mês</th><th>Receita</th><th>Despesas</th><th>Lucro</th></tr></thead><tbody>
    <?php foreach ($historico as $h): ?><tr><td><?= limpar($h['mes']) ?></td><td><?= moeda($h['receita']) ?></td><td><?= moeda($h['despesas']) ?></td><td><?= moeda($h['receita']-$h['despesas']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </details>
  <script type="application/json" id="dados-grafico"><?= json_encode(array_map(fn($h)=>['mes'=>$h['mes'],'receita'=>$h['receita'],'despesas'=>$h['despesas'],'lucro'=>round($h['receita']-$h['despesas'],2)],$historico),JSON_HEX_TAG|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?></script>
  <?php endif; ?>
<script src="js/grafico-financeiro.js?v=<?= filemtime(dirname(__DIR__).'/js/grafico-financeiro.js') ?>" defer></script>
