<?php
require_once __DIR__.'/includes/bootstrap_sessao.php';
require_once __DIR__.'/includes/functions.php';
verificarSessao('dono');$tituloPagina='Usuários e recuperação';$erro='';$link='';
if (isset($_POST['emitir'])) {
    try { $link='nova_senha.php?token='.emitirRecuperacao((int)$_POST['id_usuario']); }
    catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$usuarios=lerJson('usuarios.json');
$pedidos=\App\Config\Database::getConnection()->query('SELECT DISTINCT id_usuario FROM recuperacao_senha WHERE token_hash IS NULL AND utilizado_em IS NULL')->fetchAll(PDO::FETCH_COLUMN);
include 'includes/head.php';
?>
<div class="painel"><?php include 'includes/sidebar.php'; ?><main class="conteudo-principal">
<div class="cabecalho-pagina"><h1>Usuários e recuperação</h1></div>
<?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>
<?php if ($link): ?><div class="alerta alerta-sucesso">Link válido por 30 minutos. Compartilhe com o titular: <a href="<?= limpar($link) ?>"><?= limpar($link) ?></a></div><?php endif; ?>
<div class="cartao"><div class="tabela-wrapper"><table><thead><tr><th>Nome</th><th>Login</th><th>Perfil</th><th>Solicitação</th><th>Recuperação</th></tr></thead><tbody>
<?php foreach ($usuarios as $u): ?><tr><td><?= limpar($u['nome']) ?></td><td><?= limpar($u['email']) ?></td><td><?= limpar($u['tipo']) ?></td><td><?= in_array($u['id'],$pedidos,true)?'Pendente':'—' ?></td><td><form method="POST"><?= csrfCampo() ?><input type="hidden" name="id_usuario" value="<?= $u['id'] ?>"><button class="btn btn-pequeno btn-primario" name="emitir">Gerar link</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></main></div><?php include 'includes/a11y_bar.php'; ?>
