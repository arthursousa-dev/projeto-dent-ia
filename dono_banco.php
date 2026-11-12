<?php
// Núcleo de dados: grafo vivo das tabelas, fluxo de alterações e conteúdo. Só o gestor, só neste computador, só leitura.
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once __DIR__ . '/includes/functions.php';
exigirMonitor();
$tituloPagina = 'Banco de dados';
$pdo = \App\Config\Database::getConnection();
limparRegistroAntigo($pdo);

$resumo = resumoBanco($pdo);
$tabelas = tabelasMonitor($pdo);
$colunas = colunasPorTabela($pdo);
$totalRegistros = array_sum(array_map('intval', array_column($tabelas, 'registros')));

$tabelaAberta = (string)($_GET['tabela'] ?? '');
$conteudo = null; $erroTabela = '';
if ($tabelaAberta !== '') {
    try { $conteudo = conteudoTabela($pdo, $tabelaAberta, idParametro($_GET['pagina'] ?? 1) ?: 1); }
    catch (Throwable $e) { $erroTabela = mensagemOperacao($e); $tabelaAberta = ''; }
}

// Tudo o que o painel precisa para desenhar o grafo, embutido como JSON (escapado para não fechar a tag <script>).
$dados = [
    'tabelas' => array_map(fn($t) => [
        'tabela' => $t['tabela'], 'grupo' => grupoTabela($t['tabela']), 'registros' => (int)$t['registros'],
        'ultima' => $t['ultima_alteracao'], 'contagem' => (int)$t['insercoes'] . ' / ' . (int)$t['alteracoes'] . ' / ' . (int)$t['exclusoes'],
        'tamanho' => $t['tamanho'], 'na_hora' => (int)$t['na_hora'], 'colunas' => $colunas[$t['tabela']] ?? [],
    ], $tabelas),
    'relacoes' => relacoesTabelas($pdo),
    'alteracoes' => array_reverse(registroAlteracoes($pdo, 0, 60)),
    'ultimoId' => (int)$resumo['ultimo_id'],
    'aberta' => $tabelaAberta,
];
$grupos = ['agenda' => 'Agenda', 'clinico' => 'Clínico', 'financeiro' => 'Financeiro', 'acesso' => 'Pessoas e acesso', 'sistema' => 'Sistema'];
include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal hud" id="conteudo-principal">

    <header class="hud-topo">
      <div class="hud-titulo">
        <span class="hud-nucleo" aria-hidden="true"></span>
        <div>
          <h1>Núcleo de dados</h1>
          <p><span class="hud-mono"><?= limpar($resumo['nome']) ?></span> · PostgreSQL <?= limpar($resumo['versao']) ?> · só leitura · segredos ocultos</p>
        </div>
      </div>
      <dl class="hud-metricas">
        <div><dt>Estado</dt><dd id="monitor-estado" role="status"><span class="ponto-vivo" aria-hidden="true"></span><span class="hud-estado-texto">Ao vivo</span></dd></div>
        <div><dt>Tamanho</dt><dd id="kpi-tamanho"><?= limpar($resumo['tamanho']) ?></dd></div>
        <div><dt>Tabelas</dt><dd><?= count($tabelas) ?></dd></div>
        <div><dt>Registros</dt><dd id="kpi-registros"><?= number_format($totalRegistros, 0, ',', '.') ?></dd></div>
        <div><dt>Alterações hoje</dt><dd id="kpi-hoje"><?= (int)$resumo['alteracoes_hoje'] ?></dd></div>
        <div><dt>Por minuto</dt><dd id="kpi-taxa">0</dd></div>
        <div><dt>Conexões</dt><dd id="kpi-conexoes"><?= (int)$resumo['conexoes'] ?></dd></div>
      </dl>
    </header>

    <div class="hud-grade">
      <section class="hud-painel hud-grafo" aria-labelledby="titulo-grafo">
        <div class="hud-painel-topo">
          <h2 id="titulo-grafo">Mapa das tabelas</h2>
          <div class="hud-acoes">
            <button type="button" class="hud-botao" id="monitor-pausar" aria-pressed="false"><i class="bi bi-pause-fill" aria-hidden="true"></i> Pausar</button>
            <button type="button" class="hud-botao" id="grafo-reorganizar"><i class="bi bi-diagram-3" aria-hidden="true"></i> Reorganizar</button>
          </div>
        </div>
        <canvas id="grafo" role="img" aria-label="Mapa das tabelas do banco ligadas pelas chaves estrangeiras. Use a lista de tabelas abaixo para navegar pelo teclado."></canvas>
        <div class="hud-legenda" aria-hidden="true">
          <?php foreach ($grupos as $chave => $rotulo): ?><span><i class="grupo-<?= $chave ?>"></i><?= $rotulo ?></span><?php endforeach; ?>
          <span class="hud-dica">Arraste nós · role para zoom · clique para inspecionar</span>
        </div>
      </section>

      <section class="hud-painel hud-fluxo" aria-labelledby="titulo-fluxo">
        <div class="hud-painel-topo">
          <h2 id="titulo-fluxo">Pipeline</h2>
          <span class="hud-mono hud-fraco" id="registro-novos">aguardando eventos…</span>
        </div>
        <ol class="hud-eventos" id="corpo-registro" aria-label="Alterações no banco, mais recentes primeiro"></ol>
      </section>
    </div>

    <section class="hud-painel hud-inspetor" id="inspetor" aria-live="polite" aria-labelledby="titulo-inspetor">
      <div class="hud-painel-topo"><h2 id="titulo-inspetor">Inspetor</h2></div>
      <div id="inspetor-corpo" class="hud-inspetor-corpo">
        <p class="hud-fraco">Clique numa tabela no mapa (ou na lista abaixo) para ver registros, ligações e colunas.</p>
      </div>
    </section>

    <details class="hud-painel hud-lista">
      <summary>Lista de tabelas (<?= count($tabelas) ?>)</summary>
      <ul>
        <?php foreach ($tabelas as $t): ?>
        <li><button type="button" class="hud-link" data-selecionar="<?= limpar($t['tabela']) ?>"><?= limpar($t['tabela']) ?></button>
          <span class="hud-mono hud-fraco"><?= number_format((int)$t['registros'], 0, ',', '.') ?></span></li>
        <?php endforeach; ?>
      </ul>
    </details>

    <section class="hud-painel" id="conteudo" aria-labelledby="titulo-conteudo">
      <div class="hud-painel-topo">
        <h2 id="titulo-conteudo">Conteúdo<?= $tabelaAberta ? ' · <span class="hud-mono">' . limpar($tabelaAberta) . '</span>' : '' ?></h2>
        <form method="GET" class="monitor-escolha" action="dono_banco.php#conteudo">
          <label for="escolher-tabela" class="apenas-leitores">Tabela</label>
          <select id="escolher-tabela" name="tabela">
            <option value="">Escolha uma tabela…</option>
            <?php foreach ($tabelas as $t): ?><option <?= $tabelaAberta === $t['tabela'] ? 'selected' : '' ?>><?= limpar($t['tabela']) ?></option><?php endforeach; ?>
          </select>
          <button class="hud-botao">Abrir</button>
        </form>
      </div>
      <?php if ($erroTabela): ?><div class="alerta alerta-erro"><?= limpar($erroTabela) ?></div><?php endif; ?>
      <?php if ($conteudo): ?>
        <p class="hud-fraco hud-mono monitor-legenda"><?= number_format($conteudo['total'], 0, ',', '.') ?> registro(s) · mais recentes primeiro · página <?= $conteudo['pagina'] ?>/<?= $conteudo['paginas'] ?></p>
        <div class="tabela-wrapper hud-tabela">
          <table aria-label="Conteúdo da tabela <?= limpar($tabelaAberta) ?>">
            <thead><tr><?php foreach ($conteudo['colunas'] as $c): ?><th><?= limpar($c) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php if (!$conteudo['linhas']): ?><tr><td colspan="<?= count($conteudo['colunas']) ?>">Tabela vazia.</td></tr><?php endif; ?>
            <?php foreach ($conteudo['linhas'] as $linha): ?>
              <tr><?php foreach ($conteudo['colunas'] as $c): ?><td><?= $linha[$c] === null ? '<span class="hud-fraco">nulo</span>' : limpar(is_bool($linha[$c]) ? ($linha[$c] ? 'sim' : 'não') : (string)$linha[$c]) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($conteudo['paginas'] > 1): ?>
        <nav class="monitor-paginas" aria-label="Páginas">
          <?php if ($conteudo['pagina'] > 1): ?><a class="hud-botao" href="?tabela=<?= urlencode($tabelaAberta) ?>&amp;pagina=<?= $conteudo['pagina'] - 1 ?>#conteudo">← Mais recentes</a><?php endif; ?>
          <?php if ($conteudo['pagina'] < $conteudo['paginas']): ?><a class="hud-botao" href="?tabela=<?= urlencode($tabelaAberta) ?>&amp;pagina=<?= $conteudo['pagina'] + 1 ?>#conteudo">Mais antigos →</a><?php endif; ?>
        </nav>
        <?php endif; ?>
      <?php else: ?>
        <p class="hud-fraco monitor-legenda">Escolha uma tabela para ver os registros, 50 por página.</p>
      <?php endif; ?>
    </section>

  </main>
</div>
<?php include 'includes/a11y_bar.php'; ?>
<script type="application/json" id="dados-banco"><?= json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="js/monitor-banco.js?v=<?= filemtime(__DIR__ . '/js/monitor-banco.js') ?>" defer></script>
</body>
</html>
