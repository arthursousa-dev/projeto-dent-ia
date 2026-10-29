<?php
// Sair só por POST com token CSRF (verificado em includes/functions.php): um link de outro site não desconecta ninguém.
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once __DIR__ . '/includes/functions.php';

$perfil = $_SESSION['perfil'] ?? '';
$destino = PERFIS[$perfil]['login'] ?? 'index.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if ($perfil === '') { header('Location: index.php'); exit; }
    $tituloPagina = 'Sair';
    include 'includes/head.php';
    ?>
<main class="pagina-erro" id="conteudo-principal">
  <h1>Sair do DENT IA?</h1>
  <p>Você será desconectado deste computador.</p>
  <form method="POST" action="logout.php">
    <?= csrfCampo(false) ?>
    <button type="submit" class="btn btn-primario">Sair</button>
    <a href="<?= limpar(PERFIS[$perfil]['painel'] ?? 'index.php') ?>" class="btn btn-fantasma">Continuar no sistema</a>
  </form>
</main>
</body>
</html>
<?php
    exit;
}

$_SESSION = [];
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
header('Location: ' . $destino, true, 303);
exit;
