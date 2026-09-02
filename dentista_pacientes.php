<?php
// lista de pacientes do dentista logado
session_start();
require_once 'includes/functions.php';
verificarSessao('dentista');

$tituloPagina = 'Meus Pacientes';
$termoBusca   = trim($_GET['busca'] ?? '');

// filtra pacientes pelo nome se houver busca ativa
if ($termoBusca) {
    $listaBuscada = array_filter($listaPacientes, function ($paciente) use ($termoBusca) {
        return stripos($paciente['nome'], $termoBusca) !== false;
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
        <h1><i class="bi bi-people-fill" aria-hidden="true"></i> Meus Pacientes</h1>
        <p>Pacientes sob seus cuidados na clínica</p>
      </div>
    </div>

    <!-- campo de busca -->
    <div class="cartao">
      <div class="cartao-corpo">
        <form method="GET" role="search" aria-label="Buscar paciente" style="display:flex;gap:10px;">
          <label for="busca-meus" class="apenas-leitores">Buscar pelo nome</label>
          <input type="search" id="busca-meus" name="busca"
                 value="<?= limpar($termoBusca) ?>"
                 placeholder="Buscar pelo nome do paciente..."
                 style="flex:1;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pilula);font-family:var(--fonte);font-size:0.92rem;color:var(--texto-principal);background:var(--fundo-campo);outline:none;">
          <button type="submit" class="btn btn-primario">Buscar</button>
          <?php if ($termoBusca): ?>
            <a href="dentista_pacientes.php" class="btn btn-fantasma">Limpar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- tabela de pacientes -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3>Lista de Pacientes</h3>
        <span style="color:var(--texto-fraco);font-size:0.82rem;">
          <?= count($listaBuscada) ?> pacientes encontrados
        </span>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Lista dos meus pacientes">
          <thead>
            <tr>
              <th>Paciente</th>
              <th>Contato</th>
              <th>Última Consulta</th>
              <th>Próxima Consulta</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($listaBuscada as $paciente): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:9px;">
                  <div class="avatar avatar-pequeno destaque" aria-hidden="true">
                    <?= iniciais($paciente['nome']) ?>
                  </div>
                  <div>
                    <strong><?= limpar($paciente['nome']) ?></strong><br>
                    <span style="font-size:0.78rem;color:var(--texto-fraco);">
                      <?= limpar($paciente['cpf']) ?>
                    </span>
                  </div>
                </div>
              </td>
              <td>
                <span style="font-size:0.85rem;"><?= limpar($paciente['telefone']) ?></span>
              </td>
              <td><?= limpar($paciente['ultimaConsulta']) ?></td>
              <td style="color:var(--destaque);font-size:0.85rem;">10/04/2026</td>
              <td><?= emblema($paciente['status']) ?></td>
              <td>
                <a href="dentista_prontuario.php?paciente=<?= urlencode($paciente['nome']) ?>"
                   class="btn btn-primario btn-pequeno"
                   aria-label="Ver prontuário de <?= limpar($paciente['nome']) ?>">
                  <i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Prontuário
                </a>
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
