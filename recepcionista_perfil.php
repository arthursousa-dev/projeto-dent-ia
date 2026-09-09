<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('recepcionista');
$tituloPagina = 'Meu Perfil';
$emailSessao  = strtolower($_SESSION['email'] ?? '');
$erro = ''; $sucesso = '';

$usuarios = lerJson('usuarios.json');
$uAtual   = buscarPorCampo($usuarios, 'email', $emailSessao) ?? [];
$dados    = $uAtual;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_perfil'])) {
    $novoNome = trim($_POST['nome'] ?? '');
    if (empty($novoNome)) { $erro = 'O nome não pode ficar em branco.'; }
    else {
        foreach ($usuarios as &$u) {
            if (strtolower($u['email']) === $emailSessao) {
                $u['nome']      = $novoNome;
                $u['telefone']  = trim($_POST['telefone']  ?? '');
                $u['nascimento']= $_POST['nascimento']     ?? '';
                break;
            }
        }
        salvarJson('usuarios.json', $usuarios);
        $_SESSION['usuario'] = $novoNome;
        $sucesso = 'Perfil atualizado em ' . data_pt('d/m/Y \à\s H:i') . '!';
        $dados = array_merge($dados, ['nome'=>$novoNome,'telefone'=>trim($_POST['telefone']??''),'nascimento'=>$_POST['nascimento']??'']);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha'])) {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $senhaNova  = $_POST['senha_nova']  ?? '';
    $senhaConf  = $_POST['confirmar_senha_nova'] ?? '';
    $uSenha = buscarPorCampo($usuarios, 'email', $emailSessao);
    if (!$uSenha || !password_verify($senhaAtual, $uSenha['senha'])) $erro = 'Senha atual incorreta.';
    elseif (strlen($senhaNova) < 6) $erro = 'Nova senha deve ter pelo menos 6 caracteres.';
    elseif ($senhaNova !== $senhaConf) $erro = 'As senhas não coincidem.';
    else {
        foreach ($usuarios as &$u) { if (strtolower($u['email'])===$emailSessao) { $u['senha']=password_hash($senhaNova, PASSWORD_DEFAULT); break; } }
        salvarJson('usuarios.json', $usuarios);
        $sucesso = 'Senha alterada com sucesso!';
    }
}
include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">
    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-person-fill" aria-hidden="true"></i> Meu Perfil</h1><p>Dados pessoais e segurança</p></div>
    </div>
    <?php if ($sucesso): ?><div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?=limpar($sucesso)?></div><?php endif; ?>
    <?php if ($erro):    ?><div class="alerta alerta-erro"   role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?=limpar($erro)?></div><?php endif; ?>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
          <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#d97706,#b45309);display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;font-weight:700;flex-shrink:0;">
            <?=iniciais($dados['nome']??'RE')?>
          </div>
          <div style="flex:1;">
            <h2 style="font-size:20px;margin-bottom:4px;"><?=limpar($dados['nome']??'Recepcionista')?></h2>
            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px;color:var(--texto-secundario);">
              <span><i class="bi bi-folder2-open" aria-hidden="true"></i> <?=limpar($dados['cargo']??'Recepcionista')?></span>
              <span><i class="bi bi-envelope-fill" aria-hidden="true"></i> <?=limpar($emailSessao)?></span>
              <span><i class="bi bi-telephone-fill" aria-hidden="true"></i> <?=limpar($dados['telefone']??'—')?></span>
            </div>
          </div>
          <?=emblema('Ativo')?>
        </div>
      </div>
    </div>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho"><h3><i class="bi bi-pencil-fill" aria-hidden="true"></i> Editar Dados Pessoais</h3></div>
      <div class="cartao-corpo">
        <form method="POST" novalidate>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="pf-nome">Nome completo</label>
              <input type="text" id="pf-nome" name="nome" required value="<?=limpar($dados['nome']??'')?>">
            </div>
            <div class="grupo-campo">
              <label for="pf-cpf">CPF <small style="color:var(--texto-fraco)">(não editável)</small></label>
              <input type="text" id="pf-cpf" value="<?=limpar($dados['cpf']??'—')?>" readonly style="opacity:.7;">
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="pf-tel">Telefone</label>
              <input type="tel" id="pf-tel" name="telefone" value="<?=limpar($dados['telefone']??'')?>">
            </div>
            <div class="grupo-campo">
              <label for="pf-nasc">Data de Nascimento</label>
              <input type="date" id="pf-nasc" name="nascimento" value="<?=limpar($dados['nascimento']??'')?>">
            </div>
          </div>
          <div class="grupo-campo" style="max-width:50%">
            <label for="pf-cargo">Cargo <small style="color:var(--texto-fraco)">(definido pelo gestor)</small></label>
            <input type="text" id="pf-cargo" value="<?=limpar($dados['cargo']??'Recepcionista')?>" readonly style="opacity:.7;">
          </div>
          <button type="submit" name="salvar_perfil" class="btn btn-primario"><i class="bi bi-save-fill" aria-hidden="true"></i> Salvar Alterações</button>
        </form>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-lock-fill" aria-hidden="true"></i> Alterar Senha</h3></div>
      <div class="cartao-corpo">
        <form method="POST" novalidate id="frm-senha">
          <div class="linha-campos">
            <div class="grupo-campo"><label for="sa">Senha atual</label><input type="password" id="sa" name="senha_atual" placeholder="••••••••"></div>
            <div class="grupo-campo"><label for="sn">Nova senha</label><input type="password" id="sn" name="senha_nova" placeholder="••••••••"></div>
          </div>
          <div class="grupo-campo" style="max-width:50%">
            <label for="sc">Confirmar nova senha</label>
            <input type="password" id="sc" name="confirmar_senha_nova" placeholder="••••••••">
            <span id="err-s" style="font-size:12px;color:var(--perigo);display:block;margin-top:3px;min-height:16px;"></span>
          </div>
          <button type="submit" name="alterar_senha" class="btn btn-escuro"><i class="bi bi-key-fill" aria-hidden="true"></i> Alterar Senha</button>
        </form>
      </div>
    </div>
  </main>
</div>
<script>
document.getElementById('frm-senha').addEventListener('submit',function(e){
  var n=document.getElementById('sn').value,c=document.getElementById('sc').value,er=document.getElementById('err-s');
  if(n&&c&&n!==c){er.textContent='As senhas não coincidem.';e.preventDefault();}else er.textContent='';
});
</script>
<?php include 'includes/a11y_bar.php'; ?>
