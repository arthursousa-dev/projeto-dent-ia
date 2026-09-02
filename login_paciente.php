<?php
session_start();
require_once 'includes/functions.php';

if (isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'cliente') {
    header('Location: cliente_dashboard.php'); exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $usuario = autenticarUsuario($email, $senha, 'cliente');
    if ($usuario) {
        $_SESSION['usuario'] = $usuario['nome'];
        $_SESSION['perfil']  = 'cliente';
        $_SESSION['email']   = $email;
        header('Location: ' . $usuario['redirecionar']); exit;
    }
    $erro = 'E-mail ou senha incorretos. Verifique e tente novamente.';
}
?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Área do Paciente — DENT IA</title>
  <script src="js/accessibility.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    :root { --cor-role: #3182ce; --cor-role-fundo: rgba(49,130,206,0.10); }
    .login-wrap { min-height:100vh; display:flex; }
    .login-esq {
      width: 400px; flex-shrink: 0;
      background: linear-gradient(160deg, #0b2845 0%, #1a4a7a 100%);
      display: flex; flex-direction: column; align-items: flex-start;
      justify-content: center; padding: 60px 48px; position: relative; overflow: hidden;
    }
    .login-esq::before {
      content:''; position:absolute; bottom:-80px; right:-80px;
      width:300px; height:300px; border-radius:50%;
      background: radial-gradient(circle, rgba(49,130,206,0.25) 0%, transparent 70%);
    }
    .logo { font-family:var(--fonte-titulo); font-size:36px; font-weight:900; color:#fff; margin-bottom:8px; }
    .logo span { color: #63b3ed; }
    .logo-sub { color:rgba(255,255,255,0.65); font-size:14px; margin-bottom:48px; }
    .role-badge {
      display: inline-flex; align-items: center; gap: 10px;
      background: rgba(49,130,206,0.20); border: 1px solid rgba(99,179,237,0.4);
      border-radius: var(--raio-pilula); padding: 10px 18px;
      color: #90cdf4; font-weight: 600; font-size: 14px; margin-bottom: 24px;
    }
    .login-desc { color:rgba(255,255,255,0.70); font-size:14px; line-height:1.7; }
    .login-desc li { margin-bottom: 8px; }
    .login-dir {
      flex: 1; display: flex; align-items: center; justify-content: center;
      background: var(--fundo-corpo); padding: 40px 24px;
    }
    .caixa { background:var(--fundo-cartao); border-radius:var(--raio); padding:44px 40px; width:100%; max-width:420px; box-shadow:var(--sombra-grande); }
    .caixa h1 { font-size:22px; font-weight:700; color:var(--texto-principal); margin-bottom:4px; }
    .caixa p  { color:var(--texto-secundario); font-size:14px; margin-bottom:28px; }
    .grupo { margin-bottom: 18px; }
    .grupo label { display:block; font-size:13px; font-weight:600; color:var(--texto-principal); margin-bottom:6px; }
    .grupo input {
      width:100%; padding:12px 14px; border:2px solid var(--borda); border-radius:var(--raio-pequeno);
      font-family:var(--fonte); font-size:14px; color:var(--texto-principal); background:var(--fundo-campo);
      transition:border-color var(--transicao); box-sizing:border-box;
    }
    .grupo input:focus { outline:none; border-color:var(--cor-role); box-shadow:0 0 0 3px rgba(49,130,206,0.20); }
    .campo-senha { position:relative; }
    .campo-senha input { padding-right: 44px; }
    .btn-olho { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--texto-fraco); padding:4px; border-radius:4px; }
    .btn-olho:hover { color:var(--cor-role); }
    .erro { background:var(--perigo-fundo); border:1px solid var(--perigo); border-radius:var(--raio-pequeno); padding:12px 14px; color:var(--perigo); font-size:13px; margin-bottom:18px; }
    .btn-entrar {
      width:100%; padding:13px; background:var(--cor-role); color:#fff; border:none;
      border-radius:var(--raio-pilula); font-family:var(--fonte); font-size:15px; font-weight:700;
      cursor:pointer; transition:background var(--transicao), transform var(--transicao); margin-bottom:16px;
    }
    .btn-entrar:hover { background:#2b6cb0; transform:translateY(-1px); }
    .btn-entrar:focus-visible { outline: 3px solid var(--cor-role); outline-offset: 2px; }
    .link-volta { display:block; text-align:center; color:var(--texto-secundario); font-size:13px; text-decoration:none; }
    .link-volta:hover { color:var(--cor-role); }
    .credenciais {
      margin-top:28px; padding:14px 16px;
      background:var(--fundo-campo); border:1px solid var(--borda); border-radius:var(--raio-pequeno);
      font-size:12px; color:var(--texto-secundario);
    }
    .credenciais strong { display:block; color:var(--texto-principal); margin-bottom:4px; font-size:12px; }
    .cred-linha { display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px solid var(--borda); }
    .cred-linha:last-child { border-bottom:none; }
    .cred-chave { font-weight:600; color:var(--texto-principal); }
    .cred-val { font-family:monospace; color:var(--cor-role); font-weight:700; }
    @media(max-width:720px){.login-esq{display:none;} .caixa{padding:32px 24px;}}
  </style>
</head>
<body>
<a href="#conteudo-principal" class="pular-conteudo">Pular para o conteúdo</a>
<main class="login-wrap" id="conteudo-principal">

  <div class="login-esq" role="complementary">
    <div class="logo">DENT<span>IA</span></div>
    <div class="logo-sub">Gestão Odontológica Inteligente</div>
    <div class="role-badge"><i class="bi bi-person-fill" aria-hidden="true"></i> Área do Paciente</div>
    <ul class="login-desc">
      <li><i class="bi bi-check2" aria-hidden="true"></i> Agende suas consultas online</li>
      <li><i class="bi bi-check2" aria-hidden="true"></i> Acompanhe seu histórico clínico</li>
      <li><i class="bi bi-check2" aria-hidden="true"></i> Gerencie seus dados pessoais</li>
      <li><i class="bi bi-check2" aria-hidden="true"></i> Receba lembretes de consultas</li>
    </ul>
  </div>

  <div class="login-dir">
    <div class="caixa">
      <h1>Bem-vindo de volta <i class="bi bi-emoji-smile" aria-hidden="true"></i></h1>
      <p>Acesse sua área de paciente para continuar</p>

      <?php if ($erro): ?>
        <div class="erro" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= limpar($erro) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate id="frm">
        <div class="grupo">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" placeholder="seu@email.com" required autocomplete="email" value="<?= limpar($_POST['email'] ?? '') ?>">
          <span id="err-email" style="font-size:12px;color:var(--perigo);display:block;margin-top:3px;"></span>
        </div>
        <div class="grupo">
          <label for="senha">Senha</label>
          <div class="campo-senha">
            <input type="password" id="senha" name="senha" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="btn-olho" onclick="toggleSenha()" aria-label="Mostrar senha">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="ico-olho"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="ico-olho-aberto" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <span id="err-senha" style="font-size:12px;color:var(--perigo);display:block;margin-top:3px;"></span>
        </div>
        <div style="text-align:right;margin:-8px 0 18px">
          <a href="esqueci_senha.php" style="font-size:13px;color:var(--cor-role);font-weight:600;text-decoration:none;">Esqueci minha senha</a>
        </div>
        <button type="submit" class="btn-entrar">Entrar como Paciente →</button>
      </form>

      <a href="index.php" class="link-volta">← Voltar ao portal de acesso</a>

      <div class="credenciais" role="note">
        <strong><i class="bi bi-key-fill" aria-hidden="true"></i> Credenciais de demonstração</strong>
        <div class="cred-linha"><span class="cred-chave">E-mail</span><span class="cred-val">cliente@gmail.com</span></div>
        <div class="cred-linha"><span class="cred-chave">Senha</span><span class="cred-val">123456</span></div>
      </div>
    </div>
  </div>

</main>
<?php include 'includes/a11y_bar.php'; ?>
<script>
function toggleSenha(){
  var c=document.getElementById('senha');
  var a=document.getElementById('ico-olho');
  var b=document.getElementById('ico-olho-aberto');
  if(c.type==='password'){c.type='text';a.style.display='none';b.style.display='inline';}
  else{c.type='password';a.style.display='inline';b.style.display='none';}
}
document.getElementById('frm').addEventListener('submit',function(e){
  var ok=true;
  var el=document.getElementById('email'),er=document.getElementById('err-email');
  var sl=document.getElementById('senha'),sr=document.getElementById('err-senha');
  var re=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if(!el.value.trim()){er.textContent='Informe seu e-mail.';ok=false;}
  else if(!re.test(el.value)){er.textContent='E-mail inválido.';ok=false;}
  else er.textContent='';
  if(!sl.value){sr.textContent='Informe sua senha.';ok=false;}
  else sr.textContent='';
  if(!ok)e.preventDefault();
});
</script>
</body>
</html>
