<?php
session_start();
require_once 'includes/functions.php';
verificarSessao('dono');
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
    if (!$uSenha || $uSenha['senha'] !== $senhaAtual) $erro = 'Senha atual incorreta.';
    elseif (strlen($senhaNova) < 6) $erro = 'Nova senha deve ter pelo menos 6 caracteres.';
    elseif ($senhaNova !== $senhaConf) $erro = 'As senhas não coincidem.';
    else {
        foreach ($usuarios as &$u) { if (strtolower($u['email'])===$emailSessao) { $u['senha']=$senhaNova; break; } }
        salvarJson('usuarios.json', $usuarios);
        $sucesso = 'Senha alterada com sucesso!';
    }
}

// Estatísticas rápidas do gestor
$dentistas  = lerJson('dentistas.json');
$pacientes  = lerJson('pacientes.json');
$fat        = lerJsonObjeto('faturamento.json');
$mesAtual   = $fat['mes_atual'] ?? [];
include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">
    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-award-fill" aria-hidden="true"></i> Meu Perfil</h1><p>Dados do gestor e visão geral da clínica</p></div>
    </div>
    <?php if ($sucesso): ?><div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?=limpar($sucesso)?></div><?php endif; ?>
    <?php if ($erro):    ?><div class="alerta alerta-erro"   role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?=limpar($erro)?></div><?php endif; ?>

    <!-- Cabeçalho gestor -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
          <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#f5a623,#e08c00);display:flex;align-items:center;justify-content:center;color:#0b2845;font-size:24px;font-weight:900;flex-shrink:0;">
            <?=iniciais($dados['nome']??'GE')?>
          </div>
          <div style="flex:1;">
            <h2 style="font-size:20px;margin-bottom:4px;"><?=limpar($dados['nome']??'Gestor')?></h2>
            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px;color:var(--texto-secundario);">
              <span><i class="bi bi-award-fill" aria-hidden="true"></i> <?=limpar($dados['cargo']??'Proprietário')?></span>
              <span><i class="bi bi-envelope-fill" aria-hidden="true"></i> <?=limpar($emailSessao)?></span>
              <span><i class="bi bi-telephone-fill" aria-hidden="true"></i> <?=limpar($dados['telefone']??'—')?></span>
            </div>
          </div>
          <span style="background:rgba(245,166,35,.15);color:#b7791f;border:1px solid #f5a623;border-radius:999px;padding:4px 14px;font-size:12px;font-weight:700;"><i class="bi bi-award-fill" aria-hidden="true"></i> Proprietário</span>
        </div>
      </div>
    </div>

    <!-- Visão rápida da clínica -->
    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho"><h3><i class="bi bi-hospital-fill" aria-hidden="true"></i> Visão da Clínica — <?=limpar($mesAtual['mes']??date('F Y'))?></h3></div>
      <div class="cartao-corpo">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;">
          <?php
          $stats = [
            ['Faturamento','<i class="bi bi-cash-coin" aria-hidden="true"></i>','R$ '.number_format($mesAtual['receita_total']??0,0,',','.')],
            ['Procedimentos','<i class="bi bi-calendar-event-fill" aria-hidden="true"></i>',$mesAtual['procedimentos_realizados']??0],
            ['Ticket Médio','<i class="bi bi-bullseye" aria-hidden="true"></i>','R$ '.number_format($mesAtual['ticket_medio']??0,2,',','.')],
            ['Dentistas','<i class="bi bi-emoji-smile-fill" aria-hidden="true"></i>',count($dentistas)],
            ['Pacientes Ativos','<i class="bi bi-people-fill" aria-hidden="true"></i>',count(filtrarPorCampo($pacientes,'status','Ativo'))],
            ['Crescimento','<i class="bi bi-graph-up-arrow" aria-hidden="true"></i>','+'.number_format($mesAtual['crescimento_percentual']??0,1,',','.').'%'],
          ];
          foreach ($stats as [$lbl,$icon,$val]): ?>
          <div style="padding:16px;background:var(--fundo-campo);border-radius:var(--raio-pequeno);border:1px solid var(--borda);text-align:center;">
            <div style="font-size:22px;margin-bottom:6px;"><?=$icon?></div>
            <div style="font-size:17px;font-weight:700;color:var(--texto-principal);margin-bottom:2px;"><?=limpar((string)$val)?></div>
            <div style="font-size:11px;color:var(--texto-fraco);text-transform:uppercase;letter-spacing:.5px;"><?=$lbl?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Dados pessoais -->
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
              <label for="pf-cpf">CPF</label>
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
          <button type="submit" name="salvar_perfil" class="btn btn-primario"><i class="bi bi-save-fill" aria-hidden="true"></i> Salvar Alterações</button>
        </form>
      </div>
    </div>

    <!-- Alterar senha -->
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
