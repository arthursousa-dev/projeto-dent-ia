<?php
// Cadastre-se: o paciente cria o próprio acesso ao portal. Regras em cadastrarPacientePortal() (includes/operacoes.php).
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['perfil'], $_SESSION['usuario'])) {
    header('Location: ' . (PERFIS[$_SESSION['perfil']]['painel'] ?? 'index.php'));
    exit;
}

$erro = '';
$v = fn(string $campo): string => limpar((string)($_POST[$campo] ?? ''));
// CSRF e envio único já foram conferidos em includes/functions.php.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $usuario = cadastrarPacientePortal($_POST);
        iniciarSessaoUsuario($usuario, (string)$_POST['email']);
        redirecionar($usuario['redirecionar'], 'Cadastro concluído. Bem-vindo(a) ao portal! Você já pode agendar sua consulta.');
    } catch (Throwable $e) {
        $erro = mensagemOperacao($e);
    }
}
$hoje = date('Y-m-d');
?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastre-se — DENT IA</title>
  <script src="js/accessibility.js"></script>
  <script src="js/app.js?v=<?= filemtime(__DIR__ . '/js/app.js') ?>" defer></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" integrity="sha384-CK2SzKma4jA5H/MXDUU7i1TqZlCFaD4T01vtyDFvPlD97JQyS+IsSh1nI2EFbpyk" crossorigin="anonymous">
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
  <style>:root{--cor-role:#2b6cb0;--cor-painel:#12324f;--cor-marca:#8ec5f0;}</style>
</head>
<body>
<a href="#conteudo-principal" class="pular-conteudo">Pular para o conteúdo</a>
<main class="login-wrap" id="conteudo-principal">

  <div class="login-esq" role="complementary">
    <div class="logo">DENT<span>IA</span></div>
    <div class="logo-sub">Gestão Odontológica Inteligente</div>
    <div class="role-badge"><i class="bi bi-person-plus-fill" aria-hidden="true"></i> Novo paciente</div>
    <ul class="login-desc">
      <li><i class="bi bi-check2" aria-hidden="true"></i> Crie seu acesso em um minuto</li>
      <li><i class="bi bi-check2" aria-hidden="true"></i> Agende consultas pelo portal</li>
      <li><i class="bi bi-check2" aria-hidden="true"></i> Acompanhe suas consultas</li>
      <li><i class="bi bi-check2" aria-hidden="true"></i> Já é paciente? Peça o link de acesso à clínica</li>
    </ul>
  </div>

  <div class="login-dir">
    <div class="caixa caixa-cadastro">
      <h1>Cadastre-se <i class="bi bi-person-plus" aria-hidden="true"></i></h1>
      <p>Preencha seus dados. O e-mail e a senha serão o seu acesso ao portal.</p>

      <?= exibirFlash() ?>
      <?php if ($erro): ?>
        <div class="erro" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= limpar($erro) ?></div>
      <?php endif; ?>

      <form method="POST" id="form-cadastro" novalidate aria-label="Cadastro de paciente">
        <?= csrfCampo() ?>
        <div class="grupo">
          <label for="nome">Nome completo</label>
          <input type="text" id="nome" name="nome" required minlength="3" maxlength="150" autocomplete="name" value="<?= $v('nome') ?>">
        </div>
        <div class="linha-cadastro">
          <div class="grupo">
            <label for="cpf">CPF</label>
            <input type="text" id="cpf" name="cpf" required inputmode="numeric" maxlength="14" placeholder="000.000.000-00" autocomplete="off" value="<?= $v('cpf') ?>" data-mascara="cpf">
          </div>
          <div class="grupo">
            <label for="nascimento">Data de nascimento</label>
            <input type="date" id="nascimento" name="nascimento" required min="1900-01-01" max="<?= $hoje ?>" value="<?= $v('nascimento') ?>">
          </div>
        </div>
        <div class="linha-cadastro">
          <div class="grupo">
            <label for="telefone">Celular com DDD</label>
            <input type="tel" id="telefone" name="telefone" required inputmode="tel" maxlength="16" placeholder="(67) 99999-0000" autocomplete="tel" value="<?= $v('telefone') ?>" data-mascara="telefone">
          </div>
          <div class="grupo">
            <label for="email">E-mail (será o seu login)</label>
            <input type="email" id="email" name="email" required maxlength="150" autocomplete="email" placeholder="seu@email.com" value="<?= $v('email') ?>">
          </div>
        </div>
        <div class="linha-cadastro">
          <div class="grupo">
            <label for="senha">Senha</label>
            <div class="campo-senha">
              <input type="password" id="senha" name="senha" required minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password" aria-describedby="dica-senha">
              <button type="button" class="btn-olho" data-alternar-senha="senha" aria-label="Mostrar senha" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>
            <span class="dica-campo" id="dica-senha">Pelo menos <?= SENHA_MINIMA ?> caracteres.</span>
          </div>
          <div class="grupo">
            <label for="confirmar_senha">Confirme a senha</label>
            <div class="campo-senha">
              <input type="password" id="confirmar_senha" name="confirmar_senha" required autocomplete="new-password">
              <button type="button" class="btn-olho" data-alternar-senha="confirmar_senha" aria-label="Mostrar senha" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>
          </div>
        </div>
        <label class="aceite" for="aceite">
          <input type="checkbox" id="aceite" name="aceite" value="1" required aria-describedby="texto-aceite" <?= !empty($_POST['aceite']) ? 'checked' : '' ?>>
          <span id="texto-aceite">Autorizo a clínica a usar meus dados para cadastro, agendamento e prontuário, conforme a LGPD. Posso pedir correção ou exclusão na recepção.</span>
        </label>
        <p class="erro-campo" id="erro-cadastro" role="alert"></p>
        <button type="submit" class="btn-entrar">Criar meu acesso</button>
      </form>

      <p class="login-cadastro">Já tem acesso? <a href="login_paciente.php">Entrar</a></p>
      <a href="index.php" class="link-volta">← Voltar ao portal de acesso</a>
    </div>
  </div>

</main>
<?php include 'includes/a11y_bar.php'; ?>
<script>
(function () {
  // Máscaras simples: só formatam o que foi digitado; a validação de verdade é no servidor.
  function mascarar(campo, tipo) {
    var d = campo.value.replace(/\D/g, '');
    if (tipo === 'cpf') {
      d = d.slice(0, 11);
      campo.value = d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
      d = d.slice(0, 11);
      campo.value = d.length > 10 ? d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3')
        : d.replace(/(\d{2})(\d{0,4})(\d{0,4})/, function (m, a, b, c) { return '(' + a + (b ? ') ' + b : '') + (c ? '-' + c : ''); });
    }
  }
  document.querySelectorAll('[data-mascara]').forEach(function (campo) {
    campo.addEventListener('input', function () { mascarar(campo, campo.getAttribute('data-mascara')); });
  });
  document.getElementById('form-cadastro').addEventListener('submit', function (e) {
    var msg = '';
    var f = this;
    if (f.nome.value.trim().length < 3) msg = 'Informe o nome completo.';
    else if (f.cpf.value.replace(/\D/g, '').length !== 11) msg = 'O CPF precisa ter 11 números.';
    else if (!f.nascimento.value) msg = 'Informe a data de nascimento.';
    else if (f.telefone.value.replace(/\D/g, '').length < 10) msg = 'Informe o celular com DDD.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value.trim())) msg = 'Informe um e-mail válido.';
    else if (f.senha.value.length < <?= SENHA_MINIMA ?>) msg = 'A senha deve ter pelo menos <?= SENHA_MINIMA ?> caracteres.';
    else if (f.senha.value !== f.confirmar_senha.value) msg = 'As senhas não coincidem.';
    else if (!f.aceite.checked) msg = 'Para criar a conta, aceite o uso dos seus dados pela clínica.';
    document.getElementById('erro-cadastro').textContent = msg;
    if (msg) e.preventDefault();
  });
})();
</script>
</body>
</html>
