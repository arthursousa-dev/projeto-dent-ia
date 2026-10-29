<?php
// gerenciamento da equipe de dentistas
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');carregarListas();

$tituloPagina = 'Dentistas';
$erro='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (isset($_POST['salvar_dentista'])) { cadastrarDentista($_POST);redirecionar(paginaAtual(),'Dentista cadastrado. Para o primeiro acesso, gere o link de definição de senha em Usuários e envie ao dentista.'); }
        elseif (isset($_POST['excluir'])) {
            $nome=definirStatusDentista(idParametro($_POST['id'] ?? 0),false);
            redirecionar(paginaAtual(),$nome.' foi inativado(a); os registros clínicos foram preservados.');
        }
    } catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
// Depois de um erro no cadastro, o formulário reabre com o que foi digitado.
$novo=isset($_POST['salvar_dentista']) ? $_POST : [];
$pdo=\App\Config\Database::getConnection();
$especialidades=$pdo->query('SELECT nome FROM especialidade ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);
$salas=$pdo->query('SELECT nome FROM sala ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Dentistas</h1>
        <p>Gerencie a equipe odontológica da clínica</p>
      </div>
      <!-- botão que mostra/esconde o formulário de cadastro -->
      <button class="btn btn-primario"
              onclick="alternarFormulario()"
              aria-expanded="<?= $novo ? 'true' : 'false' ?>"
              aria-controls="form-novo-dentista"
              id="botao-novo-dentista">
        + Novo Dentista
      </button>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>

    <!-- formulário de cadastro (escondido por padrão) -->
    <div class="cartao" id="form-novo-dentista" style="<?= $novo ? '' : 'display:none;' ?>margin-bottom:22px;"
         aria-label="Formulário de cadastro de novo dentista">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-plus-circle-fill" aria-hidden="true"></i> Cadastrar Novo Dentista</h3>
        <button type="button" class="btn btn-fantasma btn-pequeno"
                onclick="alternarFormulario()"><i class="bi bi-x-lg" aria-hidden="true"></i> Fechar</button>
      </div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de cadastro de dentista">
          <?= csrfCampo() ?>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="dent-nome">Nome completo</label>
              <input type="text" id="dent-nome" name="nome"
                     placeholder="Dr(a). Nome Sobrenome" required value="<?= limpar((string)($novo['nome'] ?? '')) ?>">
            </div>
            <div class="grupo-campo">
              <label for="dent-especialidade">Especialidade</label>
              <select id="dent-especialidade" name="especialidade" required>
                <?php foreach ($especialidades as $esp): ?><option <?= $esp===($novo['especialidade'] ?? '') ? 'selected' : '' ?>><?= limpar($esp) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="dent-cro">Número do CRO</label>
              <input type="text" id="dent-cro" name="cro" placeholder="CRO-SP 00000" value="<?= limpar((string)($novo['cro'] ?? '')) ?>">
            </div>
            <div class="grupo-campo">
              <label for="dent-telefone">Telefone</label>
              <input type="tel" id="dent-telefone" name="telefone"
                     placeholder="(00) 00000-0000" value="<?= limpar((string)($novo['telefone'] ?? '')) ?>">
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="dent-email">E-mail</label>
              <input type="email" id="dent-email" name="email"
                     placeholder="dentista@email.com" value="<?= limpar((string)($novo['email'] ?? '')) ?>">
            </div>
            <div class="grupo-campo">
              <label for="dent-admissao">Data de admissão</label>
              <input type="date" id="dent-admissao" name="admissao"
                     value="<?= limpar((string)($novo['admissao'] ?? date('Y-m-d'))) ?>">
            </div>
          </div>
          <div class="linha-campos"><div class="grupo-campo"><label>CPF</label><input name="cpf" required value="<?= limpar((string)($novo['cpf'] ?? '')) ?>"></div><div class="grupo-campo"><label>Nascimento</label><input type="date" name="nascimento" required value="<?= limpar((string)($novo['nascimento'] ?? '')) ?>"></div><div class="grupo-campo"><label>Sala</label><select name="sala" required><?php foreach ($salas as $sala): ?><option <?= $sala===($novo['sala'] ?? 'Sala 1') ? 'selected' : '' ?>><?= limpar($sala) ?></option><?php endforeach; ?></select></div></div>
          <div class="grupo-campo"><label>Procedimentos habilitados</label><?php foreach ($listaProcedimentos as $proc): ?><label><input type="checkbox" name="procedimentos[]" value="<?= limpar($proc) ?>" <?= in_array($proc,(array)($novo['procedimentos'] ?? []),true) ? 'checked' : '' ?>> <?= limpar($proc) ?></label><?php endforeach; ?></div>
          <button type="submit" name="salvar_dentista" class="btn btn-primario">
            Salvar dentista
          </button>
        </form>
      </div>
    </div>

    <!-- listagem dos dentistas cadastrados -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Equipe cadastrada (<?= count($listaDentistas) ?> dentistas)</h3>
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

                  <a class="btn btn-fantasma btn-pequeno" href="dono_editar_dentista.php?id=<?= $dentista['id'] ?>">Editar</a>
                  <!-- inativar com confirmação -->
                  <form method="POST" style="display:inline;"
                        data-confirmar="Tem certeza que deseja inativar <?= limpar($dentista['nome']) ?>?">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $dentista['id'] ?>">
                    <button type="submit" name="excluir" class="btn btn-perigo btn-pequeno"
                            aria-label="Inativar <?= limpar($dentista['nome']) ?>">
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
