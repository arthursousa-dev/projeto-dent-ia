<?php
// includes/login_form.php — tela de login única dos 4 perfis.
// Cada login_*.php define $login (textos e cores do perfil) e inclui este arquivo.
require_once __DIR__ . '/bootstrap_sessao.php';
require_once __DIR__ . '/functions.php';

$perfilLogin = $login['perfil'];
if (($_SESSION['perfil'] ?? '') === $perfilLogin && isset($_SESSION['usuario'])) {
    header('Location: ' . PERFIS[$perfilLogin]['painel']);
    exit;
}
\App\Config\Database::getConnection(); // também carrega o .env (APP_DEMO)

$erro = isset($_GET['expirou']) ? 'Sua sessão foi encerrada após 60 minutos sem uso. Entre novamente.' : '';
// CSRF e envio único já foram conferidos em includes/functions.php.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    if (limiteAtingido('login', 20, 15)) {
        $erro = 'Muitas tentativas de acesso deste computador. Aguarde 15 minutos e tente de novo.';
    } elseif ($email === '' || $senha === '') {
        $erro = 'Informe e-mail e senha.';
    } else {
        marcarUsuarioNoBanco('login:' . $perfilLogin . ':' . strtolower($email));
        $usuario = autenticarUsuario($email, $senha, $perfilLogin);
        if ($usuario) {
            iniciarSessaoUsuario($usuario, $email);
            header('Location: ' . $usuario['redirecionar'], true, 303);
            exit;
        }
        registrarTentativa('login');
        $erro = 'E-mail ou senha incorretos. Depois de 5 tentativas erradas, a conta fica bloqueada por 15 minutos.';
    }
}
$mostrarDemo = getenv('APP_DEMO') === 'true';
?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= limpar($login['titulo']) ?> — DENT IA</title>
  <script src="js/accessibility.js"></script>
  <script src="js/app.js?v=<?= filemtime(dirname(__DIR__) . '/js/app.js') ?>" defer></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" integrity="sha384-CK2SzKma4jA5H/MXDUU7i1TqZlCFaD4T01vtyDFvPlD97JQyS+IsSh1nI2EFbpyk" crossorigin="anonymous">
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(dirname(__DIR__) . '/css/style.css') ?>">
  <style>:root{<?= $login['cores'] ?>}</style>
</head>
<body>
<a href="#conteudo-principal" class="pular-conteudo">Pular para o conteúdo</a>
<main class="login-wrap" id="conteudo-principal">

  <div class="login-esq" role="complementary">
    <div class="logo">DENT<span>IA</span></div>
    <div class="logo-sub">Gestão Odontológica Inteligente</div>
    <div class="role-badge"><i class="bi <?= $login['icone'] ?>" aria-hidden="true"></i> <?= limpar($login['titulo']) ?></div>
    <ul class="login-desc">
      <?php foreach ($login['itens'] as $item): ?>
      <li><i class="bi bi-check2" aria-hidden="true"></i> <?= limpar($item) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="login-dir">
    <div class="caixa">
      <h1><?= limpar($login['chamada']) ?> <i class="bi <?= $login['icone'] ?>" aria-hidden="true"></i></h1>
      <p><?= limpar($login['subtitulo']) ?></p>

      <?= exibirFlash() ?>
      <?php if ($erro): ?>
        <div class="erro" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= limpar($erro) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate id="form-login">
        <?= csrfCampo() ?>
        <div class="grupo">
          <label for="email"><?= limpar($login['rotulo_email']) ?></label>
          <input type="email" id="email" name="email" placeholder="<?= limpar($login['exemplo_email']) ?>" required maxlength="150" autocomplete="email" value="<?= limpar((string)($_POST['email'] ?? '')) ?>">
          <span class="erro-campo" id="err-email"></span>
        </div>
        <div class="grupo">
          <label for="senha">Senha</label>
          <div class="campo-senha">
            <input type="password" id="senha" name="senha" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="btn-olho" data-alternar-senha="senha" aria-label="Mostrar senha" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
          </div>
          <span class="erro-campo" id="err-senha"></span>
        </div>
        <div class="login-links"><a href="esqueci_senha.php">Esqueci minha senha</a></div>
        <button type="submit" class="btn-entrar"><?= limpar($login['botao']) ?></button>
      </form>

      <?php if (!empty($login['cadastro'])): ?>
      <p class="login-cadastro">Ainda não tem acesso? <a href="cadastro_paciente.php">Cadastre-se</a></p>
      <?php endif; ?>

      <a href="index.php" class="link-volta">← Voltar ao portal de acesso</a>

      <?php if ($mostrarDemo): ?>
      <div class="credenciais" role="note">
        <strong><i class="bi bi-key-fill" aria-hidden="true"></i> Credenciais de demonstração</strong>
        <div class="cred-linha"><span class="cred-chave">E-mail</span><span class="cred-val"><?= limpar($login['demo']) ?></span></div>
        <div class="cred-linha"><span class="cred-chave">Senha</span><span class="cred-val">123456</span></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

</main>
<?php include __DIR__ . '/a11y_bar.php'; ?>
<script>
document.getElementById('form-login').addEventListener('submit', function (e) {
  var email = document.getElementById('email'), senha = document.getElementById('senha');
  var erroEmail = document.getElementById('err-email'), erroSenha = document.getElementById('err-senha');
  var ok = true;
  erroEmail.textContent = !email.value.trim() ? 'Informe seu e-mail.' : (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()) ? 'E-mail inválido.' : '');
  erroSenha.textContent = senha.value ? '' : 'Informe sua senha.';
  if (erroEmail.textContent || erroSenha.textContent) { ok = false; }
  if (!ok) e.preventDefault();
});
</script>
</body>
</html>
