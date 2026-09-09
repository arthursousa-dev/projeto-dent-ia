<?php
// gerenciamento da equipe de dentistas
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');

$tituloPagina = 'Dentistas';
$sucesso = '';

// processa as ações do formulário
if (isset($_POST['salvar_dentista'])) {
    $sucesso = 'Dentista cadastrado com sucesso!';
}
if (isset($_POST['excluir'])) {
    // TODO: deletar do banco de dados pelo ID
    $sucesso = 'Dentista removido do sistema.';
}

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i> Dentistas</h1>
        <p>Gerencie a equipe odontológica da clínica</p>
      </div>
      <!-- botão que mostra/esconde o formulário de cadastro -->
      <button class="btn btn-primario"
              onclick="alternarFormulario()"
              aria-expanded="false"
              aria-controls="form-novo-dentista"
              id="botao-novo-dentista">
        + Novo Dentista
      </button>
    </div>

    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>

    <!-- formulário de cadastro (escondido por padrão) -->
    <div class="cartao" id="form-novo-dentista" style="display:none;margin-bottom:22px;"
         aria-label="Formulário de cadastro de novo dentista">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-plus-circle-fill" aria-hidden="true"></i> Cadastrar Novo Dentista</h3>
        <button type="button" class="btn btn-fantasma btn-pequeno"
                onclick="alternarFormulario()"><i class="bi bi-x-lg" aria-hidden="true"></i> Fechar</button>
      </div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de cadastro de dentista">
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="dent-nome">Nome completo</label>
              <input type="text" id="dent-nome" name="nome"
                     placeholder="Dr(a). Nome Sobrenome" required>
            </div>
            <div class="grupo-campo">
              <label for="dent-especialidade">Especialidade</label>
              <select id="dent-especialidade" name="especialidade">
                <option>Clínico Geral</option>
                <option>Ortodontia</option>
                <option>Cirurgia Oral</option>
                <option>Endodontia</option>
                <option>Periodontia</option>
                <option>Implantodontia</option>
              </select>
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="dent-cro">Número do CRO</label>
              <input type="text" id="dent-cro" name="cro" placeholder="CRO-SP 00000">
            </div>
            <div class="grupo-campo">
              <label for="dent-telefone">Telefone</label>
              <input type="tel" id="dent-telefone" name="telefone"
                     placeholder="(00) 00000-0000">
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="dent-email">E-mail</label>
              <input type="email" id="dent-email" name="email"
                     placeholder="dentista@email.com">
            </div>
            <div class="grupo-campo">
              <label for="dent-admissao">Data de admissão</label>
              <input type="date" id="dent-admissao" name="admissao"
                     value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <button type="submit" name="salvar_dentista" class="btn btn-primario">
            Salvar dentista
          </button>
        </form>
      </div>
    </div>

    <!-- listagem dos dentistas cadastrados -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Equipe Ativa (<?= count($listaDentistas) ?> dentistas)</h3>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Lista de dentistas cadastrados">
          <thead>
            <tr>
              <th>Dentista</th>
              <th>Especialidade</th>
              <th>CRO</th>
              <th>Contato</th>
              <th>Atend./Mês</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($listaDentistas as $dentista): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="avatar avatar-pequeno destaque" aria-hidden="true">
                    <?= iniciais($dentista['nome']) ?>
                  </div>
                  <strong><?= limpar($dentista['nome']) ?></strong>
                </div>
              </td>
              <td><?= limpar($dentista['especialidade']) ?></td>
              <td><?= limpar($dentista['cro']) ?></td>
              <td>
                <a href="mailto:<?= limpar($dentista['email']) ?>"
                   style="font-size:0.82rem;">
                  <?= limpar($dentista['email']) ?>
                </a>
              </td>
              <td><strong><?= $dentista['atendimentos'] ?></strong></td>
              <td><?= emblema($dentista['status']) ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <!-- editar — TODO: abrir modal com dados do dentista -->
                  <button class="btn btn-fantasma btn-pequeno"
                          onclick="alert('Em produção abriria o formulário de edição com os dados do banco.')"
                          aria-label="Editar <?= limpar($dentista['nome']) ?>">
                    <i class="bi bi-pencil-fill" aria-hidden="true"></i>
                  </button>
                  <!-- remover com confirmação -->
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Tem certeza que deseja remover <?= limpar($dentista['nome']) ?>?')">
                    <input type="hidden" name="id" value="<?= $dentista['id'] ?>">
                    <button type="submit" name="excluir" class="btn btn-perigo btn-pequeno"
                            aria-label="Remover <?= limpar($dentista['nome']) ?>">
                      <i class="bi bi-trash-fill" aria-hidden="true"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<script>
// mostra ou esconde o formulário de novo dentista
function alternarFormulario() {
  var formulario = document.getElementById('form-novo-dentista');
  var botao      = document.getElementById('botao-novo-dentista');
  var estaAberto = formulario.style.display === 'none';

  formulario.style.display = estaAberto ? 'block' : 'none';
  botao.setAttribute('aria-expanded', estaAberto ? 'true' : 'false');

  // move o foco pro primeiro campo quando abre
  if (estaAberto) {
    var primeiroCampo = formulario.querySelector('input');
    if (primeiroCampo) primeiroCampo.focus();
  }
}
</script>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
