<?php
// listagem de pacientes — visão do dono
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');

$tituloPagina = 'Pacientes';

// pega o termo de busca da URL (se existir)
$termoBusca = trim($_GET['busca'] ?? '');

// filtra a lista de pacientes conforme a busca
if ($termoBusca) {
    $listaBuscada = array_filter($listaPacientes, function ($paciente) use ($termoBusca) {
        return stripos($paciente['nome'], $termoBusca) !== false
            || stripos($paciente['cpf'], $termoBusca) !== false;
    });
} else {
    $listaBuscada = $listaPacientes;
}

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-people-fill" aria-hidden="true"></i> Pacientes</h1>
        <p>Base de pacientes cadastrados na clínica</p>
      </div>
    </div>

    <!-- resumo rápido -->
    <div class="grade-estatisticas" style="grid-template-columns:repeat(3,1fr);">
      <div class="cartao-estatistica">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>284</strong><span>Total de pacientes</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong>261</strong><span>Ativos</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo" aria-hidden="true">🆕</div>
        <div class="info-estatistica"><strong>12</strong><span>Novos este mês</span></div>
      </div>
    </div>

    <!-- campo de busca -->
    <div class="cartao">
      <div class="cartao-corpo">
        <form method="GET" role="search" aria-label="Buscar paciente" style="display:flex;gap:10px;">
          <label for="campo-busca" class="apenas-leitores">Buscar por nome ou CPF</label>
          <input type="search" id="campo-busca" name="busca"
                 value="<?= limpar($termoBusca) ?>"
                 placeholder="Buscar por nome ou CPF..."
                 style="flex:1;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pilula);font-family:var(--fonte);font-size:0.92rem;color:var(--texto-principal);background:var(--fundo-campo);outline:none;">
          <button type="submit" class="btn btn-primario">Buscar</button>
          <?php if ($termoBusca): ?>
            <a href="dono_pacientes.php" class="btn btn-fantasma">Limpar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- listagem dos pacientes -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3>Lista de Pacientes</h3>
        <span style="color:var(--texto-fraco);font-size:0.82rem;">
          <?= count($listaBuscada) ?> encontrados
        </span>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Lista de pacientes cadastrados">
          <thead>
            <tr>
              <th>Paciente</th>
              <th>CPF</th>
              <th>Telefone</th>
              <th>Última Consulta</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($listaBuscada as $paciente): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:9px;">
                  <div class="avatar avatar-pequeno"
                       style="background:linear-gradient(135deg,var(--informacao),#2563b0);"
                       aria-hidden="true">
                    <?= iniciais($paciente['nome']) ?>
                  </div>
                  <div>
                    <strong><?= limpar($paciente['nome']) ?></strong><br>
                    <span style="font-size:0.78rem;color:var(--texto-fraco);"><?= limpar($paciente['email']) ?></span>
                  </div>
                </div>
              </td>
              <td><?= limpar($paciente['cpf']) ?></td>
              <td><?= limpar($paciente['telefone']) ?></td>
              <td><?= limpar($paciente['ultimaConsulta']) ?></td>
              <td><?= emblema($paciente['status']) ?></td>
              <td>
                <a href="#" class="cartao-link" style="font-size:0.8rem;">Ver histórico</a>
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
