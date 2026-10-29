<?php
require_once __DIR__.'/includes/bootstrap_sessao.php';
require_once __DIR__.'/includes/functions.php';
verificarSessao('dono');carregarListas();
$tituloPagina='Editar dentista';$erro='';
$id=idParametro($_POST['id'] ?? $_GET['id'] ?? 0);
$d=buscarPorCampo(lerJson('dentistas.json'),'id',$id);
if (!$d) paginaErro(404,'Dentista não encontrado','O dentista pedido não existe. Volte à lista de dentistas e escolha outro.');
if (isset($_POST['salvar'])) {
    try {
        salvarEdicaoDentista($id,$_POST);
        redirecionar(paginaAtual(),'Dentista atualizado.');
    } catch (Throwable $e) {
        $erro=mensagemOperacao($e);
        // Mantém na tela o que foi digitado.
        $d=array_merge($d,camposPresentes($_POST,['nome','email','telefone','cro','especialidade','sala','titulacao','faculdade','cor','status']));
        $d['procedimentos']=(array)($_POST['procedimentos'] ?? []);
    }
}
$pdo=\App\Config\Database::getConnection();
$opcoes=['especialidade'=>$pdo->query('SELECT nome FROM especialidade ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN),
    'sala'=>$pdo->query('SELECT nome FROM sala ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN)];
include 'includes/head.php';
?>
<div class="painel"><?php include 'includes/sidebar.php'; ?><main class="conteudo-principal">
<div class="cabecalho-pagina"><h1>Editar dentista</h1><a href="dono_dentistas.php" class="btn btn-fantasma">Voltar</a></div>
<?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>
<div class="cartao"><div class="cartao-corpo"><form method="POST"><?= csrfCampo() ?><input type="hidden" name="id" value="<?= $id ?>">
<?php foreach (['nome'=>'Nome','email'=>'E-mail de contato','telefone'=>'Telefone','cro'=>'CRO','titulacao'=>'Titulação','faculdade'=>'Faculdade'] as $campo=>$rotulo): ?>
<div class="grupo-campo"><label><?= $rotulo ?></label><input name="<?= $campo ?>" value="<?= limpar((string)($d[$campo] ?? '')) ?>" <?= in_array($campo,['titulacao','faculdade'],true) ? '' : 'required' ?>></div><?php endforeach; ?>
<?php foreach (['especialidade'=>'Especialidade','sala'=>'Sala'] as $campo=>$rotulo): ?>
<div class="grupo-campo"><label><?= $rotulo ?></label><select name="<?= $campo ?>" required><?php foreach ($opcoes[$campo] as $op): ?><option <?= $op===($d[$campo] ?? '')?'selected':'' ?>><?= limpar($op) ?></option><?php endforeach; ?></select></div><?php endforeach; ?>
<div class="grupo-campo"><label>Cor</label><input type="color" name="cor" value="<?= limpar((string)($d['cor'] ?? '')) ?>"></div>
<div class="grupo-campo"><label>Status</label><select name="status"><option <?= $d['status']==='Ativo'?'selected':'' ?>>Ativo</option><option <?= $d['status']==='Inativo'?'selected':'' ?>>Inativo</option></select></div>
<div class="grupo-campo"><label>Procedimentos habilitados</label><?php foreach ($listaProcedimentos as $proc): ?>
<label><input type="checkbox" name="procedimentos[]" value="<?= limpar($proc) ?>" <?= in_array($proc,$d['procedimentos'],true)?'checked':'' ?>> <?= limpar($proc) ?></label><?php endforeach; ?></div>
<button name="salvar" class="btn btn-primario">Salvar</button></form></div></div>
</main></div><?php include 'includes/a11y_bar.php'; ?>
