<?php
require_once __DIR__.'/includes/bootstrap_sessao.php';
require_once __DIR__.'/includes/functions.php';
verificarSessao('dono');
$tituloPagina='Editar dentista';$erro='';$sucesso='';
$id=(int)($_POST['id'] ?? $_GET['id'] ?? 0);
$d=buscarPorCampo(lerJson('dentistas.json'),'id',$id);
if (!$d) { http_response_code(404);exit('Dentista não encontrado.'); }
if (isset($_POST['salvar'])) {
    $novo=array_merge($d,camposPresentes($_POST,['nome','email','telefone','cro','especialidade','sala','titulacao','faculdade','cor','status']));
    $novo['procedimentos']=$_POST['procedimentos'] ?? [];
    if (salvarJson('dentistas.json',[$novo])) { $sucesso='Dentista atualizado.';$d=buscarPorCampo(lerJson('dentistas.json'),'id',$id); }
    else $erro='Não foi possível salvar. Confira os dados; procedimentos com consultas não podem ser removidos.';
}
include 'includes/head.php';
?>
<div class="painel"><?php include 'includes/sidebar.php'; ?><main class="conteudo-principal">
<div class="cabecalho-pagina"><h1>Editar dentista</h1><a href="dono_dentistas.php" class="btn btn-fantasma">Voltar</a></div>
<?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>
<?php if ($sucesso): ?><div class="alerta alerta-sucesso"><?= limpar($sucesso) ?></div><?php endif; ?>
<div class="cartao"><div class="cartao-corpo"><form method="POST"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $id ?>">
<?php foreach (['nome'=>'Nome','email'=>'E-mail de contato','telefone'=>'Telefone','cro'=>'CRO','especialidade'=>'Especialidade','sala'=>'Sala','titulacao'=>'Titulação','faculdade'=>'Faculdade','cor'=>'Cor'] as $campo=>$rotulo): ?>
<div class="grupo-campo"><label><?= $rotulo ?></label><input name="<?= $campo ?>" value="<?= limpar($d[$campo] ?? '') ?>" required></div><?php endforeach; ?>
<div class="grupo-campo"><label>Status</label><select name="status"><option <?= $d['status']==='Ativo'?'selected':'' ?>>Ativo</option><option <?= $d['status']==='Inativo'?'selected':'' ?>>Inativo</option></select></div>
<div class="grupo-campo"><label>Procedimentos habilitados</label><?php foreach ($listaProcedimentos as $proc): ?>
<label><input type="checkbox" name="procedimentos[]" value="<?= limpar($proc) ?>" <?= in_array($proc,$d['procedimentos'],true)?'checked':'' ?>> <?= limpar($proc) ?></label><?php endforeach; ?></div>
<button name="salvar" class="btn btn-primario">Salvar</button></form></div></div>
</main></div><?php include 'includes/a11y_bar.php'; ?>
