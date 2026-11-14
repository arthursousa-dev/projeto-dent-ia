<?php
// Troca obrigatória: quem entrou com senha de demonstração ou fora da regra só passa daqui depois de criar outra.
// (O bloqueio das demais páginas fica em includes/functions.php, função trocaDeSenhaPendente.)
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once __DIR__ . '/includes/functions.php';

$perfil = $_SESSION['perfil'] ?? '';
if (!isset(PERFIS[$perfil])) { header('Location: index.php'); exit; }
verificarSessao($perfil);
if (empty($_SESSION['trocar_senha'])) { header('Location: ' . PERFIS[$perfil]['painel']); exit; }

// Mesmo POST de "Meu perfil": confere a senha atual, valida a nova e libera o acesso.
[$erro] = tratarFormularioPerfil([]);

$tituloPagina = 'Crie uma nova senha';
include 'includes/head.php';
?>
<main class="pagina-erro" id="conteudo-principal">
  <h1>Crie uma nova senha</h1>
  <p>A senha usada para entrar é de demonstração ou não segue a regra de segurança. Para proteger os dados dos pacientes, crie outra antes de continuar.</p>
  <?= exibirFlash() ?>
  <?php if ($erro): ?>
    <div class="alerta alerta-erro" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= limpar($erro) ?></div>
  <?php endif; ?>
  <form method="POST" action="trocar_senha.php" style="max-width:380px;margin:0 auto;text-align:left;">
    <?= csrfCampo() ?>
    <div class="grupo-campo">
      <label for="senha_atual">Senha atual</label>
      <div class="campo-senha">
        <input type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password">
        <button type="button" class="btn-olho" data-alternar-senha="senha_atual" aria-label="Mostrar senha" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
      </div>
    </div>
    <div class="grupo-campo">
      <label for="senha_nova">Nova senha</label>
      <div class="campo-senha">
        <input type="password" id="senha_nova" name="senha_nova" required minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password" aria-describedby="regras-senha_nova">
        <button type="button" class="btn-olho" data-alternar-senha="senha_nova" aria-label="Mostrar senha" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
      </div>
      <?= requisitosSenha('senha_nova') ?>
    </div>
    <div class="grupo-campo">
      <label for="confirmar_senha_nova">Repita a nova senha</label>
      <div class="campo-senha">
        <input type="password" id="confirmar_senha_nova" name="confirmar_senha_nova" required minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password">
        <button type="button" class="btn-olho" data-alternar-senha="confirmar_senha_nova" aria-label="Mostrar senha" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
      </div>
    </div>
    <button type="submit" name="alterar_senha" class="btn btn-primario">Salvar nova senha</button>
  </form>
  <form method="POST" action="logout.php" style="margin-top:16px;">
    <?= csrfCampo(false) ?>
    <button type="submit" class="btn btn-fantasma">Sair</button>
  </form>
</main>
</body>
</html>
