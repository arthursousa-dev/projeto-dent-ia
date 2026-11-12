<?php
// gerenciamento de pacientes pela recepcionista
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('recepcionista');carregarListas(['pacientes']);

$tituloPagina = 'Pacientes';

$erro='';$cadastro=[];
if (isset($_POST['salvar_paciente'])) {
    $enviado=$_POST+['observacoes'=>$_POST['obs'] ?? ''];
    try { salvarCadastroPaciente($enviado);redirecionar('recepcionista_pacientes.php','Paciente salvo.'.(filter_var(trim((string)($_POST['email'] ?? '')),FILTER_VALIDATE_EMAIL) ? ' Se o paciente ainda não tem senha, o gestor gera o link de acesso em Usuários. Se o e-mail foi alterado, o login do portal passa a ser o novo e-mail.' : '')); }
    catch (Throwable $e) { $erro=mensagemOperacao($e);$cadastro=$enviado; }
}
if (!$cadastro && isset($_GET['editar'])) $cadastro=buscarPorCampo($listaPacientes,'id',idParametro($_GET['editar'])) ?? [];
$termoBusca=trim((string)($_GET['busca'] ?? ''));
$digitosBusca=soDigitos($termoBusca);
$listaBuscada=array_values(array_filter($listaPacientes,fn($p)=>$termoBusca==='' || str_contains(semAcento($p['nome']),semAcento($termoBusca)) || stripos((string)$p['cpf'],$termoBusca)!==false
    || ($digitosBusca!=='' && str_contains(soDigitos($p['cpf']),$digitosBusca))));
$paginaPacientes = paginarLista($listaBuscada);
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-people-fill" aria-hidden="true"></i> Pacientes</h1>
        <p>Cadastro e busca de pacientes da clínica</p>
      </div>
      <button class="btn btn-primario"
              data-acao="alternar-formulario"
              id="botao-cadastrar"
              aria-expanded="false"
              aria-controls="form-paciente">
        + Novo Paciente
      </button>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>

    <!-- formulário de cadastro de novo paciente -->
    <div class="cartao" id="form-paciente" style="display:<?= $cadastro || $erro ? 'block' : 'none' ?>;margin-bottom:22px;">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-plus-circle-fill" aria-hidden="true"></i> Cadastrar Novo Paciente</h3>
        <button class="btn btn-fantasma btn-pequeno" data-acao="alternar-formulario"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      </div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de cadastro de paciente">
          <?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)($cadastro['id'] ?? 0) ?>">
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="pac-nome">Nome completo</label>
              <input type="text" id="pac-nome" name="nome" value="<?= limpar($cadastro['nome'] ?? '') ?>" required autocomplete="name">
            </div>
            <div class="grupo-campo">
              <label for="pac-cpf">CPF</label>
              <input type="text" id="pac-cpf" name="cpf" value="<?= limpar($cadastro['cpf'] ?? '') ?>" placeholder="000.000.000-00">
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="pac-telefone">Telefone</label>
              <input type="tel" id="pac-telefone" name="telefone" value="<?= limpar($cadastro['telefone'] ?? '') ?>" autocomplete="tel">
            </div>
            <div class="grupo-campo">
              <label for="pac-email">E-mail</label>
              <input type="email" id="pac-email" name="email" value="<?= limpar($cadastro['email'] ?? '') ?>" autocomplete="email">
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="pac-nascimento">Data de Nascimento</label>
              <input type="date" id="pac-nascimento" name="nascimento" value="<?= limpar($cadastro['nascimento'] ?? '') ?>" autocomplete="bday">
            </div>
            <div class="grupo-campo">
              <label for="pac-plano">Plano de Saúde</label>
              <select id="pac-plano" name="plano"><?php foreach (\App\Config\Database::getConnection()->query('SELECT nome FROM plano_saude ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN) as $plano): ?><option <?= ($cadastro['plano'] ?? 'Particular')===$plano ? 'selected' : '' ?>><?= limpar($plano) ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="grupo-campo">
            <label for="pac-obs">Alergias / Observações médicas</label>
            <textarea id="pac-obs" name="obs" rows="2"
                      placeholder="Alergias a medicamentos ou condições de saúde relevantes..."><?= limpar($cadastro['observacoes'] ?? '') ?></textarea>
          </div>
          <?php $aceiteAtual = !empty($cadastro['id']) ? consentimentoDoPaciente((int)$cadastro['id']) : null; ?>
          <div class="grupo-campo">
            <?php if (($aceiteAtual['versao_termo'] ?? '') === TERMO_LGPD_VERSAO): ?>
              <span class="dica-campo"><i class="bi bi-shield-check" aria-hidden="true"></i> Termo de uso de dados (versão <?= limpar(TERMO_LGPD_VERSAO) ?>) aceito em <?= limpar($aceiteAtual['aceito_em']) ?>.</span>
            <?php else: ?>
              <label class="aceite" for="pac-consentimento">
                <input type="checkbox" id="pac-consentimento" name="consentimento_lgpd" value="1" <?= !empty($cadastro['consentimento_lgpd']) ? 'checked' : '' ?>>
                <span>O paciente leu e assinou o termo de uso de dados (versão <?= limpar(TERMO_LGPD_VERSAO) ?>): “<?= limpar(TERMO_LGPD_TEXTO) ?>”</span>
              </label>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" name="salvar_paciente" class="btn btn-primario">Salvar</button>
            <button type="button" class="btn btn-fantasma" data-acao="alternar-formulario">Cancelar</button>
          </div>
        </form>
      </div>
    </div>

    <!-- busca de pacientes -->
    <div class="cartao">
      <div class="cartao-corpo">
        <form method="GET" role="search" aria-label="Buscar paciente" style="display:flex;gap:10px;">
          <label for="busca-paciente" class="apenas-leitores">Buscar por nome ou CPF</label>
          <input type="search" id="busca-paciente" name="busca"
                 value="<?= limpar($termoBusca) ?>"
                 placeholder="Buscar por nome ou CPF..."
                 style="flex:1;padding:11px 14px;border:2px solid var(--borda);border-radius:var(--raio-pilula);font-family:var(--fonte);font-size:0.92rem;color:var(--texto-principal);background:var(--fundo-campo);outline:none;">
          <button type="submit" class="btn btn-primario">Buscar</button>
          <?php if ($termoBusca): ?>
            <a href="recepcionista_pacientes.php" class="btn btn-fantasma">Limpar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- lista de pacientes -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3>Lista de Pacientes</h3>
        <span style="color:var(--texto-fraco);font-size:0.82rem;"><?= count($listaBuscada) ?> encontrados</span>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Lista de pacientes">
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
            <?php foreach ($paginaPacientes['itens'] as $paciente): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:9px;">
                  <div class="avatar avatar-pequeno"
                       style="background:var(--informacao);"
                       aria-hidden="true">
                    <?= iniciais($paciente['nome']) ?>
                  </div>
                  <strong><?= limpar($paciente['nome']) ?></strong>
                </div>
              </td>
              <td><?= limpar($paciente['cpf']) ?></td>
              <td><?= limpar($paciente['telefone']) ?></td>
              <td><?= limpar($paciente['ultimaConsulta']) ?></td>
              <td><?= emblema($paciente['status']) ?></td>
              <td>
                <div style="display:flex;gap:5px;">
                  <a href="recepcionista_novo_agendamento.php?paciente_id=<?= (int)$paciente['id'] ?>"
                     class="btn btn-primario btn-pequeno"
                     aria-label="Agendar consulta para <?= limpar($paciente['nome']) ?>">
                    <i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agendar
                  </a>
                  <a class="btn btn-fantasma btn-pequeno" href="?editar=<?= $paciente['id'] ?>">Editar</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= controlesPaginacao($paginaPacientes) ?>
    </div>

  </main>
</div>

<script nonce="<?= cspNonce() ?>">
function alternarFormulario() {
  var formulario = document.getElementById('form-paciente');
  var botao      = document.getElementById('botao-cadastrar');
  var estaAberto = formulario.style.display === 'none';
  formulario.style.display = estaAberto ? 'block' : 'none';
  botao.setAttribute('aria-expanded', estaAberto ? 'true' : 'false');
  if (estaAberto) {
    var primeiroCampo = formulario.querySelector('input');
    if (primeiroCampo) primeiroCampo.focus();
  }
}

// Ligado aqui, e não em onclick: a CSP do sistema não aceita JavaScript dentro de atributos.
document.querySelectorAll('[data-acao="alternar-formulario"]').forEach(function (b) { b.addEventListener('click', alternarFormulario); });
</script>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
