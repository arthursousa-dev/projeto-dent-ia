<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dentista');

$tituloPagina = 'Prontuários';
$pacienteSelecionado = $_GET['paciente'] ?? '';
$sucesso = isset($_POST['salvar_prontuario']) ? 'Prontuário salvo em ' . data_pt('d/m/Y \à\s H:i') . '!' : '';

$dadosPaciente = null;
foreach ($listaPacientes as $p) {
    if ($p['nome'] === $pacienteSelecionado) { $dadosPaciente = $p; break; }
}

// Carrega odontograma do prontuarios.json para o paciente selecionado
$prontuarios = lerJson('prontuarios.json');
$prontuarioPaciente = null;
foreach ($prontuarios as $pr) {
    if (($pr['paciente'] ?? '') === $pacienteSelecionado) {
        $prontuarioPaciente = $pr;
        break;
    }
}
// Fallback: primeiro prontuário disponível se nenhum paciente selecionado
if (!$prontuarioPaciente && !empty($prontuarios)) {
    $prontuarioPaciente = $prontuarios[0];
}
$odontoDados = $prontuarioPaciente['odontograma'] ?? [];
$historicoClinico = $prontuarioPaciente['historico'] ?? [];
$odontoDadosJson = json_encode($odontoDados ?: new stdClass(), JSON_UNESCAPED_UNICODE);

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Prontuários</h1>
        <p>Histórico clínico e odontograma interativo</p>
      </div>
      <a href="dentista_pacientes.php" class="btn btn-fantasma">← Pacientes</a>
    </div>

    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>

    <!-- Seletor de paciente -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;" aria-label="Selecionar paciente">
          <div style="flex:1;">
            <label for="sel-paciente" style="font-size:12px;font-weight:600;color:var(--texto-secundario);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">Paciente</label>
            <select id="sel-paciente" name="paciente"
              style="width:100%;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pilula);font-family:var(--fonte);font-size:14px;color:var(--texto-principal);background:var(--fundo-campo);">
              <option value="">Selecione um paciente...</option>
              <?php foreach ($listaPacientes as $p): ?>
                <option value="<?= limpar($p['nome']) ?>" <?= $pacienteSelecionado === $p['nome'] ? 'selected' : '' ?>>
                  <?= limpar($p['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primario" style="padding:11px 24px;">Abrir Prontuário</button>
        </form>
      </div>
    </div>

    <?php if ($dadosPaciente): ?>

    <!-- Cabeçalho do paciente -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--destaque),var(--primario-claro));display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:700;flex-shrink:0;">
            <?= iniciais($pacienteSelecionado) ?>
          </div>
          <div style="flex:1;min-width:200px;">
            <h2 style="font-size:20px;margin-bottom:4px;"><?= limpar($pacienteSelecionado) ?></h2>
            <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:13px;color:var(--texto-secundario);">
              <span><i class="bi bi-telephone-fill" aria-hidden="true"></i> <?= limpar($dadosPaciente['telefone']) ?></span>
              <span><i class="bi bi-envelope-fill" aria-hidden="true"></i> <?= limpar($dadosPaciente['email']) ?></span>
              <span><i class="bi bi-cake2-fill" aria-hidden="true"></i> <?= date('d/m/Y', strtotime($dadosPaciente['nascimento'])) ?></span>
              <span>🆔 <?= limpar($dadosPaciente['cpf']) ?></span>
            </div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?= emblema($dadosPaciente['status']) ?>
            <span style="background:var(--informacao-fundo);color:var(--informacao);border-radius:var(--raio-pilula);padding:4px 12px;font-size:12px;font-weight:600;">
              Última consulta: <?= limpar($dadosPaciente['ultimaConsulta'] ?? '—') ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- ODONTOGRAMA -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
          <div>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:3px;"><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> Odontograma Interativo</h3>
            <p style="font-size:13px;color:var(--texto-secundario);">Clique em um dente para ver detalhes e registrar tratamentos</p>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <button id="btn-xray" onclick="toggleXray()" class="btn btn-fantasma" style="font-size:13px;padding:7px 14px;"><i class="bi bi-search" aria-hidden="true"></i> Modo Raio-X</button>
            <button onclick="resetOdontograma()" class="btn btn-fantasma" style="font-size:13px;padding:7px 14px;"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Resetar</button>
          </div>
        </div>

        <!-- Legenda -->
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;padding:10px 16px;background:var(--fundo-campo);border-radius:var(--raio-pequeno);">
          <div style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <div style="width:16px;height:16px;border-radius:50%;background:#f0e8d8;border:2px solid #c8b89a;"></div> Saudável
          </div>
          <div style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <div style="width:16px;height:16px;border-radius:50%;background:#38a169;border:2px solid #276749;"></div> Tratado
          </div>
          <div style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <div style="width:16px;height:16px;border-radius:50%;background:#e53e3e;border:2px solid #9b2c2c;"></div> Precisa Tratamento
          </div>
          <div style="display:flex;align-items:center;gap:6px;font-size:13px;margin-left:auto;color:var(--texto-secundario);">
            Quadrante: <strong>Superior Dir.</strong> 1° | <strong>Superior Esq.</strong> 2° | <strong>Inferior Esq.</strong> 3° | <strong>Inferior Dir.</strong> 4°
          </div>
        </div>

        <?php include __DIR__ . '/includes/odontograma_svg.php'; ?>

        <!-- Painel de detalhe do dente -->
        <div id="dente-painel" style="display:none;margin-top:20px;padding:20px;background:var(--fundo-campo);border-radius:var(--raio);border:2px solid var(--destaque);position:relative;">
          <button onclick="fecharPainel()" style="position:absolute;top:12px;right:12px;background:none;border:none;cursor:pointer;color:var(--texto-fraco);font-size:18px;line-height:1;" aria-label="Fechar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>

          <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
            <div id="dente-icone" style="width:52px;height:52px;border-radius:50%;background:var(--destaque);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:18px;color:#0b2845;flex-shrink:0;"></div>
            <div>
              <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--texto-secundario);margin-bottom:2px;">Dente selecionado</div>
              <div id="dente-titulo" style="font-size:18px;font-weight:700;color:var(--texto-principal);"></div>
              <div id="dente-status-badge" style="margin-top:4px;"></div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
              <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--texto-secundario);margin-bottom:8px;">Procedimentos registrados</div>
              <ul id="dente-procedimentos" style="list-style:none;padding:0;margin:0;font-size:14px;color:var(--texto-principal);"></ul>
            </div>
            <div>
              <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--texto-secundario);margin-bottom:8px;">Observações clínicas</div>
              <div id="dente-notas" style="font-size:14px;color:var(--texto-principal);line-height:1.6;"></div>
            </div>
          </div>

          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--borda);display:flex;gap:8px;flex-wrap:wrap;">
            <button onclick="marcarStatus('saudavel')" class="btn btn-fantasma" style="font-size:13px;padding:7px 14px;"><i class="bi bi-circle" aria-hidden="true"></i> Marcar Saudável</button>
            <button onclick="marcarStatus('tratado')" class="btn btn-fantasma" style="font-size:13px;padding:7px 14px;color:var(--sucesso);border-color:var(--sucesso);"><i class="bi bi-circle-fill" aria-hidden="true"></i> Marcar Tratado</button>
            <button onclick="marcarStatus('precisaTratamento')" class="btn btn-fantasma" style="font-size:13px;padding:7px 14px;color:var(--perigo);border-color:var(--perigo);"><i class="bi bi-circle-fill" aria-hidden="true"></i> Precisa Tratamento</button>
          </div>
        </div>

      </div>
    </div>

    <!-- Histórico Clínico -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
          <h3 style="font-size:16px;font-weight:700;"><i class="bi bi-folder2-open" aria-hidden="true"></i> Histórico Clínico</h3>
          <span style="font-size:13px;color:var(--texto-secundario);">4 registros encontrados</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php
          $historico = [
            ['data'=>'15/03/2026','dentista'=>'Dr. Carlos Mendes','proc'=>'Restauração','dentes'=>'41','obs'=>'Restauração composta fotopolimerizável. Sem intercorrências.','valor'=>220],
            ['data'=>'10/01/2026','dentista'=>'Dr. Carlos Mendes','proc'=>'Clareamento Dental','dentes'=>'11, 12, 21, 22','obs'=>'Clareamento com moldeiras. 3 sessões. Instruído para evitar alimentos pigmentantes 48h.','valor'=>450],
            ['data'=>'20/05/2025','dentista'=>'Dr. Carlos Mendes','proc'=>'Restauração','dentes'=>'15','obs'=>'Restauração de resina classe I. Sem intercorrências.','valor'=>180],
            ['data'=>'12/08/2024','dentista'=>'Dr. Carlos Mendes','proc'=>'Selante Preventivo','dentes'=>'16, 26','obs'=>'Aplicação de selante nos molares. Orientações de higiene bucal reforçadas.','valor'=>160],
          ];
          foreach ($historico as $reg):
          ?>
          <div style="padding:16px;background:var(--fundo-campo);border-radius:var(--raio-pequeno);border-left:3px solid var(--destaque);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:700;color:var(--texto-principal);"><?= limpar($reg['proc']) ?></span>
                <span style="font-size:12px;padding:2px 10px;background:var(--informacao-fundo);color:var(--informacao);border-radius:var(--raio-pilula);font-weight:600;"><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> <?= limpar($reg['dentes']) ?></span>
              </div>
              <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:13px;color:var(--sucesso);font-weight:700;">R$ <?= number_format($reg['valor'],2,',','.') ?></span>
                <span style="font-size:12px;color:var(--texto-fraco);"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> <?= limpar($reg['data']) ?></span>
              </div>
            </div>
            <div style="font-size:13px;color:var(--texto-secundario);margin-bottom:4px;"><i class="bi bi-person-badge-fill" aria-hidden="true"></i> <?= limpar($reg['dentista']) ?></div>
            <div style="font-size:13px;color:var(--texto-principal);"><?= limpar($reg['obs']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Novo registro -->
    <div class="cartao">
      <div class="cartao-corpo">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;"><i class="bi bi-pencil-fill" aria-hidden="true"></i> Novo Registro Clínico</h3>
        <form method="POST" novalidate>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="grupo-campo">
              <label>Procedimento</label>
              <select name="procedimento" style="width:100%;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pequeno);font-family:var(--fonte);font-size:14px;color:var(--texto-principal);background:var(--fundo-campo);">
                <option value="">Selecione...</option>
                <?php foreach ($listaProcedimentos as $proc): ?>
                  <option><?= limpar($proc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label>Dentes (ex: 11, 21, 36)</label>
              <input type="text" name="dentes" placeholder="Números dos dentes tratados" style="width:100%;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pequeno);font-family:var(--fonte);font-size:14px;color:var(--texto-principal);background:var(--fundo-campo);box-sizing:border-box;">
            </div>
          </div>
          <div class="grupo-campo" style="margin-bottom:16px;">
            <label>Observações clínicas</label>
            <textarea name="observacoes" rows="3" placeholder="Descreva os procedimentos, intercorrências, orientações ao paciente..."
              style="width:100%;padding:12px 14px;border:2px solid var(--borda);border-radius:var(--raio-pequeno);font-family:var(--fonte);font-size:14px;color:var(--texto-principal);background:var(--fundo-campo);resize:vertical;box-sizing:border-box;"></textarea>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="submit" name="salvar_prontuario" class="btn btn-primario"><i class="bi bi-save-fill" aria-hidden="true"></i> Salvar Registro</button>
          </div>
        </form>
      </div>
    </div>

    <?php else: ?>

    <div class="cartao">
      <div class="cartao-corpo" style="text-align:center;padding:60px 20px;">
        <div style="font-size:56px;margin-bottom:16px;"><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i></div>
        <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;">Selecione um paciente</h3>
        <p style="color:var(--texto-secundario);">Escolha um paciente acima para ver o prontuário e o odontograma interativo</p>
      </div>
    </div>

    <?php endif; ?>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>

<script>
// Odontograma interativo — numeração FDI, com modo raio-x

// Dados dos dentes do prontuário (vindos do PHP)
const dadosBanco = <?= $odontoDadosJson ?>;

// Posições e metadados dos 32 dentes, organizados por quadrante FDI
const dentes = {
  // Quadrante 1 — superior direito
  18: { nome:'Siso Superior Direito',    tipo:'molar',    cx:55,  cy:88,  rx:12, ry:10, arco:'superior' },
  17: { nome:'2° Molar Superior Dir.',   tipo:'molar',    cx:88,  cy:103, rx:12, ry:11, arco:'superior' },
  16: { nome:'1° Molar Superior Dir.',   tipo:'molar',    cx:120, cy:118, rx:14, ry:12, arco:'superior' },
  15: { nome:'2° Pré-molar Sup. Dir.',   tipo:'premolar', cx:151, cy:131, rx:10, ry:11, arco:'superior' },
  14: { nome:'1° Pré-molar Sup. Dir.',   tipo:'premolar', cx:181, cy:141, rx:10, ry:11, arco:'superior' },
  13: { nome:'Canino Superior Dir.',     tipo:'canino',   cx:210, cy:150, rx:8,  ry:13, arco:'superior' },
  12: { nome:'Inc. Lateral Sup. Dir.',   tipo:'incisor',  cx:238, cy:156, rx:8,  ry:12, arco:'superior' },
  11: { nome:'Inc. Central Sup. Dir.',   tipo:'incisor',  cx:268, cy:160, rx:10, ry:13, arco:'superior' },
  // Quadrante 2 — superior esquerdo
  21: { nome:'Inc. Central Sup. Esq.',   tipo:'incisor',  cx:352, cy:160, rx:10, ry:13, arco:'superior' },
  22: { nome:'Inc. Lateral Sup. Esq.',   tipo:'incisor',  cx:382, cy:156, rx:8,  ry:12, arco:'superior' },
  23: { nome:'Canino Superior Esq.',     tipo:'canino',   cx:410, cy:150, rx:8,  ry:13, arco:'superior' },
  24: { nome:'1° Pré-molar Sup. Esq.',   tipo:'premolar', cx:439, cy:141, rx:10, ry:11, arco:'superior' },
  25: { nome:'2° Pré-molar Sup. Esq.',   tipo:'premolar', cx:469, cy:131, rx:10, ry:11, arco:'superior' },
  26: { nome:'1° Molar Superior Esq.',   tipo:'molar',    cx:500, cy:118, rx:14, ry:12, arco:'superior' },
  27: { nome:'2° Molar Superior Esq.',   tipo:'molar',    cx:532, cy:103, rx:12, ry:11, arco:'superior' },
  28: { nome:'Siso Superior Esquerdo',   tipo:'molar',    cx:565, cy:88,  rx:12, ry:10, arco:'superior' },
  // Quadrante 3 — inferior esquerdo
  31: { nome:'Inc. Central Inf. Esq.',   tipo:'incisor',  cx:352, cy:200, rx:8,  ry:12, arco:'inferior' },
  32: { nome:'Inc. Lateral Inf. Esq.',   tipo:'incisor',  cx:382, cy:204, rx:7,  ry:11, arco:'inferior' },
  33: { nome:'Canino Inferior Esq.',     tipo:'canino',   cx:410, cy:210, rx:8,  ry:13, arco:'inferior' },
  34: { nome:'1° Pré-molar Inf. Esq.',   tipo:'premolar', cx:439, cy:219, rx:10, ry:11, arco:'inferior' },
  35: { nome:'2° Pré-molar Inf. Esq.',   tipo:'premolar', cx:469, cy:229, rx:10, ry:11, arco:'inferior' },
  36: { nome:'1° Molar Inferior Esq.',   tipo:'molar',    cx:500, cy:242, rx:14, ry:12, arco:'inferior' },
  37: { nome:'2° Molar Inferior Esq.',   tipo:'molar',    cx:532, cy:257, rx:12, ry:11, arco:'inferior' },
  38: { nome:'Siso Inferior Esquerdo',   tipo:'molar',    cx:565, cy:272, rx:12, ry:10, arco:'inferior' },
  // Quadrante 4 — inferior direito
  41: { nome:'Inc. Central Inf. Dir.',   tipo:'incisor',  cx:268, cy:200, rx:8,  ry:12, arco:'inferior' },
  42: { nome:'Inc. Lateral Inf. Dir.',   tipo:'incisor',  cx:238, cy:204, rx:7,  ry:11, arco:'inferior' },
  43: { nome:'Canino Inferior Dir.',     tipo:'canino',   cx:210, cy:210, rx:8,  ry:13, arco:'inferior' },
  44: { nome:'1° Pré-molar Inf. Dir.',   tipo:'premolar', cx:181, cy:219, rx:10, ry:11, arco:'inferior' },
  45: { nome:'2° Pré-molar Inf. Dir.',   tipo:'premolar', cx:151, cy:229, rx:10, ry:11, arco:'inferior' },
  46: { nome:'1° Molar Inferior Dir.',   tipo:'molar',    cx:120, cy:242, rx:14, ry:12, arco:'inferior' },
  47: { nome:'2° Molar Inferior Dir.',   tipo:'molar',    cx:88,  cy:257, rx:12, ry:11, arco:'inferior' },
  48: { nome:'Siso Inferior Direito',    tipo:'molar',    cx:55,  cy:272, rx:12, ry:10, arco:'inferior' },
};

let statusDentes = {};
let denteAtivo = null;
let modoXray = false;

function inicializarStatus() {
  Object.keys(dentes).forEach(num => {
    statusDentes[num] = dadosBanco[num] ? dadosBanco[num].status : 'saudavel';
  });
}

function getGradient(status) {
  if (modoXray) {
    return { fill: `url(#g-xray-${status})`, stroke: status==='precisaTratamento'?'#555':status==='tratado'?'#fff':'#aaa' };
  }
  const map = {
    saudavel:           { fill: 'url(#g-saudavel)',           stroke: '#c8b89a' },
    tratado:            { fill: 'url(#g-tratado)',            stroke: '#276749' },
    precisaTratamento:  { fill: 'url(#g-precisaTratamento)', stroke: '#742a2a' },
  };
  return map[status] || map.saudavel;
}

// Renderiza um dente como SVG
function renderDente(num) {
  const d = dentes[num];
  const status = statusDentes[num] || 'saudavel';
  const { fill, stroke } = getGradient(status);
  const isAtivo = denteAtivo == num;

  let forma = '';
  if (d.tipo === 'molar') {
    // Molares: forma mais quadrada com cantos arredondados
    const x = d.cx - d.rx, y = d.cy - d.ry, w = d.rx*2, h = d.ry*2, r = 4;
    forma = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" ry="${r}"
      fill="${fill}" stroke="${stroke}" stroke-width="${isAtivo?2.5:1.5}"
      filter="${isAtivo?'url(#glow-hover)':'url(#sombra-dente)'}"
      style="cursor:pointer;transition:all 0.15s ease;"
      onclick="selecionarDente(${num})" aria-label="Dente ${num}"/>`;
    // Detalhe interno para molares (cúspides)
    if (d.tipo === 'molar' && !modoXray) {
      const lc = `stroke="${stroke}" stroke-width="0.7" opacity="0.4"`;
      forma += `<line x1="${d.cx}" y1="${d.cy-d.ry+2}" x2="${d.cx}" y2="${d.cy+d.ry-2}" ${lc} style="pointer-events:none"/>`;
      forma += `<line x1="${d.cx-d.rx+2}" y1="${d.cy}" x2="${d.cx+d.rx-2}" y2="${d.cy}" ${lc} style="pointer-events:none"/>`;
    }
  } else if (d.tipo === 'canino') {
    // Caninos: forma oval alongada
    forma = `<ellipse cx="${d.cx}" cy="${d.cy}" rx="${d.rx}" ry="${d.ry}"
      fill="${fill}" stroke="${stroke}" stroke-width="${isAtivo?2.5:1.5}"
      filter="${isAtivo?'url(#glow-hover)':'url(#sombra-dente)'}"
      style="cursor:pointer;transition:all 0.15s ease;"
      onclick="selecionarDente(${num})" aria-label="Dente ${num}"/>`;
  } else {
    // Incisivos e pré-molares: elipse suave
    forma = `<ellipse cx="${d.cx}" cy="${d.cy}" rx="${d.rx}" ry="${d.ry}"
      fill="${fill}" stroke="${stroke}" stroke-width="${isAtivo?2.5:1.5}"
      filter="${isAtivo?'url(#glow-hover)':'url(#sombra-dente)'}"
      style="cursor:pointer;transition:all 0.15s ease;"
      onclick="selecionarDente(${num})" aria-label="Dente ${num}"/>`;
  }

  const numY = d.arco === 'superior' ? d.cy - d.ry - 5 : d.cy + d.ry + 12;
  const numCor = isAtivo ? '#f5a623' : (modoXray ? '#ccc' : 'var(--texto-secundario)');
  const numLabel = `<text x="${d.cx}" y="${numY}" text-anchor="middle"
    font-family="DM Sans, sans-serif" font-size="9.5" font-weight="${isAtivo?'700':'500'}"
    fill="${numCor}" style="pointer-events:none;user-select:none;">${num}</text>`;

  let iconeStatus = '';
  if (status === 'precisaTratamento' && !modoXray) {
    iconeStatus = `<text x="${d.cx}" y="${d.cy+3.5}" text-anchor="middle"
      font-size="9" style="pointer-events:none;user-select:none;">⚠</text>`;
  } else if (status === 'tratado' && !modoXray) {
    iconeStatus = `<text x="${d.cx}" y="${d.cy+3.5}" text-anchor="middle"
      font-size="8" style="pointer-events:none;user-select:none;">✓</text>`;
  }

  return `<g class="dente-grupo" data-num="${num}" role="button" tabindex="0"
    aria-label="${d.nome} — ${status === 'saudavel' ? 'Saudável' : status === 'tratado' ? 'Tratado' : 'Precisa Tratamento'}"
    onkeydown="if(event.key==='Enter'||event.key===' ')selecionarDente(${num})">
    ${forma}${iconeStatus}${numLabel}
  </g>`;
}

function renderOdontograma() {
  const svg = document.getElementById('dentes-group');
  if (!svg) return;

  if (modoXray) {
    document.getElementById('odontograma').style.background = '#111';
    document.getElementById('odontograma').style.borderRadius = '12px';
  } else {
    document.getElementById('odontograma').style.background = '';
  }

  let html = '';
  Object.keys(dentes).forEach(num => { html += renderDente(parseInt(num)); });
  svg.innerHTML = html;
}

function selecionarDente(num) {
  denteAtivo = num;
  renderOdontograma();
  mostrarPainel(num);
}

function mostrarPainel(num) {
  const d = dentes[num];
  const dados = dadosBanco[String(num)] || {};
  const status = statusDentes[num] || 'saudavel';

  document.getElementById('dente-painel').style.display = 'block';
  document.getElementById('dente-icone').textContent = num;

  const cores = {
    saudavel: '#c8b89a', tratado: '#48bb78', precisaTratamento: '#e53e3e'
  };
  document.getElementById('dente-icone').style.background = cores[status] || '#c8b89a';

  document.getElementById('dente-titulo').textContent = `Dente ${num} — ${d.nome}`;

  const badges = {
    saudavel:          '<span style="background:rgba(200,184,154,0.20);color:#8b6914;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid #c8b89a;"><i class="bi bi-circle" aria-hidden="true"></i> Saudável</span>',
    tratado:           '<span style="background:rgba(56,161,105,0.12);color:#276749;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid #48bb78;"><i class="bi bi-circle-fill" aria-hidden="true"></i> Tratado</span>',
    precisaTratamento: '<span style="background:rgba(229,62,62,0.12);color:#742a2a;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid #e53e3e;"><i class="bi bi-circle-fill" aria-hidden="true"></i> Precisa Tratamento</span>',
  };
  document.getElementById('dente-status-badge').innerHTML = badges[status] || badges.saudavel;

  const procs = document.getElementById('dente-procedimentos');
  const lista = dados.procedimentos || [];
  if (lista.length) {
    procs.innerHTML = lista.map(p => `<li style="padding:4px 0;border-bottom:1px solid var(--borda);display:flex;align-items:center;gap:8px;"><span style="color:var(--destaque);">•</span>${p}</li>`).join('');
  } else {
    procs.innerHTML = '<li style="color:var(--texto-fraco);font-style:italic;">Nenhum procedimento registrado</li>';
  }

  document.getElementById('dente-notas').textContent = dados.notas || 'Sem observações registradas.';

  document.getElementById('dente-painel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function marcarStatus(novoStatus) {
  if (!denteAtivo) return;
  statusDentes[denteAtivo] = novoStatus;
  if (!dadosBanco[String(denteAtivo)]) dadosBanco[String(denteAtivo)] = {};
  dadosBanco[String(denteAtivo)].status = novoStatus;
  renderOdontograma();
  mostrarPainel(denteAtivo);
}

function toggleXray() {
  modoXray = !modoXray;
  const btn = document.getElementById('btn-xray');
  btn.innerHTML = modoXray ? '<i class="bi bi-palette-fill" aria-hidden="true"></i> Modo Normal' : '<i class="bi bi-search" aria-hidden="true"></i> Modo Raio-X';
  btn.style.color = modoXray ? 'var(--destaque)' : '';
  btn.style.borderColor = modoXray ? 'var(--destaque)' : '';
  renderOdontograma();
}

function fecharPainel() {
  document.getElementById('dente-painel').style.display = 'none';
  denteAtivo = null;
  renderOdontograma();
}

function resetOdontograma() {
  if (!confirm('Resetar o odontograma para os dados originais?')) return;
  inicializarStatus();
  denteAtivo = null;
  document.getElementById('dente-painel').style.display = 'none';
  renderOdontograma();
}

inicializarStatus();
renderOdontograma();
</script>

<style>
/* Estilos do prontuário */
.grupo-campo label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--texto-principal);
  margin-bottom: 6px;
}
#odontograma .dente-grupo:hover ellipse,
#odontograma .dente-grupo:hover rect {
  opacity: 0.85;
}
#odontograma {
  transition: background 0.3s ease;
  border-radius: 12px;
}
</style>
