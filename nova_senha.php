<?php
// Tela 2 de recuperação de senha — o usuário define a nova senha
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';

// Se não passou pela tela 1, redireciona
if (empty($_SESSION['recuperacao_email'])) {
    header('Location: esqueci_senha.php');
    exit;
}

$emailRecuperacao = $_SESSION['recuperacao_email'];
$nomeUsuario      = $_SESSION['recuperacao_nome'] ?? 'Usuário';
$erro    = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_senha'])) {
    $novaSenha     = $_POST['nova_senha']      ?? '';
    $confirmaSenha = $_POST['confirmar_senha'] ?? '';

    $erros = [];

    // nova senha: obrigatória, mínimo 6 caracteres
    if ($novaSenha === '') {
        $erros[] = 'Informe a nova senha.';
    } elseif (mb_strlen($novaSenha) < 6) {
        $erros[] = 'A nova senha deve ter pelo menos 6 caracteres.';
    }

    // confirmação: obrigatória e deve coincidir
    if ($confirmaSenha === '') {
        $erros[] = 'Confirme a nova senha.';
    } elseif ($novaSenha !== $confirmaSenha) {
        $erros[] = 'As senhas não coincidem. Verifique e tente novamente.';
    }

    if (!empty($erros)) {
        $erro = implode(' ', $erros);
    } else {
        // TODO: em produção, atualizar a senha no banco com password_hash()
        // Limpa dados de recuperação da sessão
        unset($_SESSION['recuperacao_email'], $_SESSION['recuperacao_nome']);
        $sucesso = 'Senha alterada com sucesso! Faça login com sua nova senha.';
    }
}

$tituloPagina = 'Nova Senha';
include 'includes/head.php';
?>

<main class="tela-login" id="conteudo-principal">

  <!-- LADO ESQUERDO -->
  <div class="login-esquerda" role="complementary" aria-label="Nova senha DENT IA">
    <a href="login.php" class="login-marca" aria-label="DENT IA — voltar ao login">DENT<span>IA</span></a>
    <p class="login-slogan">Crie uma senha forte para proteger sua conta.</p>
    <div style="max-width:290px;position:relative;z-index:1;margin-top:24px;" aria-hidden="true">
      <div style="background:rgba(255,255,255,0.08);border-radius:16px;padding:24px;border:1px solid rgba(255,255,255,0.15);">
        <div style="font-size:2.5rem;text-align:center;margin-bottom:12px;"><i class="bi bi-lock-fill" aria-hidden="true"></i></div>
        <p style="font-size:0.85rem;line-height:1.6;opacity:0.85;text-align:center;">
          Conta localizada para:<br>
          <strong style="opacity:1;"><?= limpar($nomeUsuario) ?></strong>
        </p>
        <div style="margin-top:18px;padding:12px;background:rgba(255,255,255,0.06);border-radius:10px;font-size:0.78rem;opacity:0.75;">
          <strong>Dicas para uma senha segura:</strong>
          <ul style="margin:8px 0 0 16px;line-height:1.7;">
            <li>Mínimo de 6 caracteres</li>
            <li>Misture letras e números</li>
            <li>Use pelo menos um caractere especial</li>
            <li>Evite sequências óbvias (123456, abcdef)</li>
          </ul>
        </div>
        <div style="margin-top:12px;padding:10px;background:rgba(255,255,255,0.06);border-radius:10px;font-size:0.78rem;opacity:0.75;text-align:center;">
          <strong>Passo 2 de 2</strong><br>
          Criar nova senha
        </div>
      </div>
    </div>
  </div>

  <!-- LADO DIREITO — formulário -->
  <div class="login-direita">
    <div class="caixa-login">
      <h2>Criar nova senha <i class="bi bi-lock-fill" aria-hidden="true"></i></h2>
      <p>
        Conta: <strong style="color:var(--primario);"><?= limpar($emailRecuperacao) ?></strong>
      </p>

      <?php if ($erro): ?>
        <div class="alerta alerta-perigo" role="alert" aria-live="assertive">
          <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= limpar($erro) ?>
        </div>
      <?php endif; ?>

      <?php if ($sucesso): ?>
        <div class="alerta alerta-sucesso" role="status" aria-live="polite">
          <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?>
          <div style="margin-top:12px;">
            <a href="login.php" class="btn btn-primario" style="display:inline-flex;padding:10px 22px;font-size:0.9rem;text-decoration:none;">
              Ir para o login →
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!$sucesso): ?>
      <form method="POST" novalidate aria-label="Formulário de nova senha" id="form-nova-senha">

        <!-- Nova senha -->
        <div class="grupo-campo">
          <label for="nova-senha">Nova senha <span style="color:var(--perigo)">*</span></label>
          <div class="campo-senha-wrapper">
            <input type="password" id="nova-senha" name="nova_senha"
                   placeholder="Mínimo 6 caracteres"
                   required aria-required="true"
                   autocomplete="new-password">
            <button type="button" class="btn-olhinho" aria-label="Mostrar nova senha"
                    onclick="alternarSenha('nova-senha',this)">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="icone-olho">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="icone-olho-aberto" style="display:none;">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <span class="msg-erro-campo" id="erro-nova-senha" role="alert"></span>
          <!-- Barra de força da senha -->
          <div id="forca-wrap" style="margin-top:8px;display:none;">
            <div style="height:5px;border-radius:3px;background:var(--borda,#e5e7eb);overflow:hidden;">
              <div id="forca-barra" style="height:100%;width:0;transition:width .3s,background .3s;border-radius:3px;"></div>
            </div>
            <span id="forca-texto" style="font-size:0.72rem;color:var(--texto-secundario);margin-top:4px;display:block;"></span>
          </div>
        </div>

        <!-- Confirmar nova senha -->
        <div class="grupo-campo">
          <label for="conf-senha">Confirmar nova senha <span style="color:var(--perigo)">*</span></label>
          <div class="campo-senha-wrapper">
            <input type="password" id="conf-senha" name="confirmar_senha"
                   placeholder="Repita a nova senha"
                   required aria-required="true"
                   autocomplete="new-password">
            <button type="button" class="btn-olhinho" aria-label="Mostrar confirmação de senha"
                    onclick="alternarSenha('conf-senha',this)">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="icone-olho">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="icone-olho-aberto" style="display:none;">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <span class="msg-erro-campo" id="erro-conf-senha" role="alert"></span>
        </div>

        <button type="submit" name="salvar_senha" class="btn btn-primario"
                style="width:100%;justify-content:center;padding:13px;font-size:0.97rem;margin-top:8px;">
          Salvar nova senha <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        </button>
      </form>

      <p style="text-align:center;margin-top:20px;color:var(--texto-secundario);font-size:0.87rem;">
        <a href="esqueci_senha.php" style="font-weight:600;">← Informar outro e-mail</a>
        &nbsp;·&nbsp;
        <a href="login.php" style="font-weight:600;">Cancelar</a>
      </p>
      <?php endif; ?>
    </div>
  </div>
</main>

<style>
.campo-senha-wrapper { position:relative;display:flex;align-items:center; }
.campo-senha-wrapper input { flex:1;padding-right:42px !important; }
.btn-olhinho { position:absolute;right:10px;background:none;border:none;padding:4px;cursor:pointer;color:var(--texto-fraco,#9ca3af);display:flex;align-items:center;justify-content:center;border-radius:4px;transition:color 0.2s;line-height:0; }
.btn-olhinho:hover,.btn-olhinho:focus-visible { color:var(--primario,#0ea5e9);outline:2px solid var(--primario,#0ea5e9);outline-offset:2px; }
.msg-erro-campo { display:block;font-size:0.76rem;color:var(--perigo,#ef4444);margin-top:4px;min-height:1rem; }
input[aria-invalid="true"] { border-color:var(--perigo,#ef4444) !important;box-shadow:0 0 0 2px rgba(239,68,68,0.15) !important; }
</style>

<script>
function alternarSenha(id, btn) {
  var c=document.getElementById(id), f=btn.querySelector('.icone-olho'), a=btn.querySelector('.icone-olho-aberto');
  if (c.type==='password'){c.type='text';f.style.display='none';a.style.display='inline';btn.setAttribute('aria-label','Ocultar senha');}
  else{c.type='password';f.style.display='inline';a.style.display='none';btn.setAttribute('aria-label','Mostrar senha');}
}

// Barra de força da senha
var novaSenhaEl = document.getElementById('nova-senha');
if (novaSenhaEl) {
  novaSenhaEl.addEventListener('input', function() {
    var v = this.value;
    var wrap = document.getElementById('forca-wrap');
    var barra = document.getElementById('forca-barra');
    var texto = document.getElementById('forca-texto');

    if (!v) { wrap.style.display='none'; return; }
    wrap.style.display = 'block';

    var forca = 0;
    if (v.length >= 6)  forca++;
    if (v.length >= 10) forca++;
    if (/[A-Z]/.test(v)) forca++;
    if (/\d/.test(v))    forca++;
    if (/[^A-Za-z0-9]/.test(v)) forca++;

    var niveis = [
      { pct:'20%', cor:'#ef4444', label:'Muito fraca' },
      { pct:'40%', cor:'#f97316', label:'Fraca' },
      { pct:'60%', cor:'#eab308', label:'Média' },
      { pct:'80%', cor:'#22c55e', label:'Forte' },
      { pct:'100%',cor:'#16a34a', label:'Muito forte' },
    ];
    var nivel = niveis[Math.min(forca, 4)];
    barra.style.width = nivel.pct;
    barra.style.background = nivel.cor;
    texto.textContent = 'Força da senha: ' + nivel.label;
    texto.style.color = nivel.cor;
  });
}

// Validação NOVA SENHA
var formNovaSenha = document.getElementById('form-nova-senha');
if (formNovaSenha) {
  formNovaSenha.addEventListener('submit', function(e) {
    var ok = true;
    var nEl  = document.getElementById('nova-senha'),  nErr = document.getElementById('erro-nova-senha');
    var cEl  = document.getElementById('conf-senha'),  cErr = document.getElementById('erro-conf-senha');

    // nova senha
    if (!nEl.value) {
      nErr.textContent = 'Informe a nova senha.';
      nEl.setAttribute('aria-invalid','true'); ok = false;
    } else if (nEl.value.length < 6) {
      nErr.textContent = 'Mínimo de 6 caracteres.';
      nEl.setAttribute('aria-invalid','true'); ok = false;
    } else {
      nErr.textContent = ''; nEl.removeAttribute('aria-invalid');
    }

    // confirmação
    if (!cEl.value) {
      cErr.textContent = 'Confirme a nova senha.';
      cEl.setAttribute('aria-invalid','true'); ok = false;
    } else if (cEl.value !== nEl.value) {
      cErr.textContent = 'As senhas não coincidem.';
      cEl.setAttribute('aria-invalid','true'); ok = false;
    } else {
      cErr.textContent = ''; cEl.removeAttribute('aria-invalid');
    }

    if (!ok) e.preventDefault();
  });

  [['nova-senha','erro-nova-senha'],['conf-senha','erro-conf-senha']].forEach(function(p){
    var el=document.getElementById(p[0]);
    if(el)el.addEventListener('input',function(){document.getElementById(p[1]).textContent='';this.removeAttribute('aria-invalid');});
  });
}
</script>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
