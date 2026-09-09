<?php
// Tela 1 de recuperação de senha — o usuário informa o Gmail cadastrado
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';

$erro    = '';
$sucesso = '';

// Contas cadastradas no sistema (em produção viria do banco)
$contasCadastradas = [
    'cliente@gmail.com'   => ['nome' => 'Maria Oliveira',    'perfil' => 'Paciente'],
    'recepcao@dentai.com' => ['nome' => 'João Recepção',     'perfil' => 'Recepção'],
    'dentista@dentai.com' => ['nome' => 'Dr. Carlos Mendes', 'perfil' => 'Dentista'],
    'dono@dentai.com'     => ['nome' => 'Arthur Sousa',      'perfil' => 'Dono'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recuperar'])) {
    $emailInformado = trim(strtolower($_POST['email'] ?? ''));

    $erros = [];

    if ($emailInformado === '') {
        $erros[] = 'Informe seu e-mail cadastrado.';
    } elseif (!filter_var($emailInformado, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Formato de e-mail inválido. Ex: nome@gmail.com';
    } elseif (!isset($contasCadastradas[$emailInformado])) {
        $erros[] = 'Nenhuma conta encontrada com esse e-mail. Verifique e tente novamente.';
    }

    if (!empty($erros)) {
        $erro = implode(' ', $erros);
    } else {
        // Salva o e-mail na sessão para validar na próxima tela
        $_SESSION['recuperacao_email'] = $emailInformado;
        $_SESSION['recuperacao_nome']  = $contasCadastradas[$emailInformado]['nome'];
        header('Location: nova_senha.php');
        exit;
    }
}

$tituloPagina = 'Esqueci minha senha';
include 'includes/head.php';
?>

<main class="tela-login" id="conteudo-principal">

  <!-- LADO ESQUERDO -->
  <div class="login-esquerda" role="complementary" aria-label="Recuperação de senha DENT IA">
    <a href="login.php" class="login-marca" aria-label="DENT IA — voltar ao login">DENT<span>IA</span></a>
    <p class="login-slogan">Recupere o acesso à sua conta em poucos passos.</p>
    <div style="max-width:290px;position:relative;z-index:1;margin-top:24px;" aria-hidden="true">
      <div style="background:rgba(255,255,255,0.08);border-radius:16px;padding:24px;border:1px solid rgba(255,255,255,0.15);">
        <div style="font-size:2.5rem;text-align:center;margin-bottom:12px;"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i></div>
        <p style="font-size:0.85rem;line-height:1.6;opacity:0.85;text-align:center;">
          Informe o <strong>Gmail</strong> que você usou no cadastro.<br><br>
          Depois você poderá criar uma nova senha segura.
        </p>
        <div style="margin-top:18px;padding:12px;background:rgba(255,255,255,0.06);border-radius:10px;font-size:0.78rem;opacity:0.75;text-align:center;">
          <strong>Passo 1 de 2</strong><br>
          Localizar sua conta
        </div>
      </div>
    </div>
  </div>

  <!-- LADO DIREITO — formulário -->
  <div class="login-direita">
    <div class="caixa-login">
      <h2>Esqueci minha senha <i class="bi bi-key-fill" aria-hidden="true"></i></h2>
      <p>Informe o Gmail da sua conta para continuar</p>

      <?php if ($erro): ?>
        <div class="alerta alerta-perigo" role="alert" aria-live="assertive">
          <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= limpar($erro) ?>
        </div>
      <?php endif; ?>

      <?php if ($sucesso): ?>
        <div class="alerta alerta-sucesso" role="status">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate aria-label="Formulário de recuperação de senha" id="form-esqueci">

        <div class="grupo-campo">
          <label for="rec-email">E-mail da conta (Gmail) <span style="color:var(--perigo)">*</span></label>
          <input type="email" id="rec-email" name="email"
                 placeholder="exemplo@gmail.com"
                 required aria-required="true"
                 autocomplete="email"
                 value="<?= limpar($_POST['email'] ?? '') ?>">
          <span class="msg-erro-campo" id="erro-rec-email" role="alert"></span>
          <small style="color:var(--texto-secundario);font-size:0.76rem;margin-top:4px;display:block;">
            Use o mesmo e-mail informado no cadastro.
          </small>
        </div>

        <button type="submit" name="recuperar" class="btn btn-primario"
                style="width:100%;justify-content:center;padding:13px;font-size:0.97rem;margin-top:8px;">
          Localizar minha conta →
        </button>
      </form>

      <p style="text-align:center;margin-top:24px;color:var(--texto-secundario);font-size:0.87rem;">
        Lembrou a senha? <a href="login.php" style="font-weight:600;">Voltar ao login</a>
      </p>
    </div>
  </div>
</main>

<style>
.msg-erro-campo { display:block;font-size:0.76rem;color:var(--perigo,#ef4444);margin-top:4px;min-height:1rem; }
input[aria-invalid="true"] { border-color:var(--perigo,#ef4444) !important;box-shadow:0 0 0 2px rgba(239,68,68,0.15) !important; }
</style>

<script>
var formEsqueci = document.getElementById('form-esqueci');
if (formEsqueci) {
  var reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  formEsqueci.addEventListener('submit', function(e) {
    var ok   = true;
    var eEl  = document.getElementById('rec-email');
    var eErr = document.getElementById('erro-rec-email');

    if (!eEl.value.trim()) {
      eErr.textContent = 'Informe seu e-mail cadastrado.';
      eEl.setAttribute('aria-invalid','true');
      ok = false;
    } else if (!reEmail.test(eEl.value.trim())) {
      eErr.textContent = 'Formato inválido. Use: nome@gmail.com';
      eEl.setAttribute('aria-invalid','true');
      ok = false;
    } else {
      eErr.textContent = '';
      eEl.removeAttribute('aria-invalid');
    }
    if (!ok) e.preventDefault();
  });

  document.getElementById('rec-email').addEventListener('input', function() {
    document.getElementById('erro-rec-email').textContent = '';
    this.removeAttribute('aria-invalid');
  });
}
</script>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
