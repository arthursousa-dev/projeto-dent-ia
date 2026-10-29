<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('cliente');
$tituloPagina = 'Meu Perfil';
$emailSessao  = strtolower($_SESSION['email'] ?? '');
$erro = '';

$usuarioAtual  = lerJson('usuarios.json',['email'=>$emailSessao])[0] ?? [];
$pacienteAtual = lerJson('pacientes.json',['id_usuario'=>$usuarioAtual['id'] ?? 0])[0] ?? [];
$dados = array_merge($usuarioAtual, $pacienteAtual);

// Salvar perfil e trocar senha: regra única em tratarFormularioPerfil() (includes/operacoes.php).
[$erro,$dados]=tratarFormularioPerfil($dados);
$planos=\App\Config\Database::getConnection()->query('SELECT nome FROM plano_saude ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">
    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-person-fill" aria-hidden="true"></i> Meu Perfil</h1><p>Dados pessoais e preferências</p></div>
    </div>
    <?php if ($erro):    ?><div class="alerta alerta-erro"   role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?=limpar($erro)?></div><?php endif; ?>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
          <div style="width:72px;height:72px;border-radius:50%;background:var(--destaque);display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;font-weight:700;flex-shrink:0;">
            <?=iniciais($dados['nome']??'PA')?>
          </div>
          <div style="flex:1;">
            <h2 style="font-size:20px;margin-bottom:4px;"><?=limpar($dados['nome']??'Paciente')?></h2>
            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:13px;color:var(--texto-secundario);">
              <span><i class="bi bi-envelope-fill" aria-hidden="true"></i> <?=limpar($emailSessao)?></span>
              <span><i class="bi bi-telephone-fill" aria-hidden="true"></i> <?=limpar($dados['telefone']??'—')?></span>
              <span><i class="bi bi-hospital-fill" aria-hidden="true"></i> <?=limpar($dados['plano']??'Particular')?></span>
            </div>
          </div>
          <?=emblema(ucfirst($dados['status']??'Ativo'))?>
        </div>
      </div>
    </div>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho"><h3><i class="bi bi-pencil-fill" aria-hidden="true"></i> Editar Dados Pessoais</h3></div>
      <div class="cartao-corpo">
        <form method="POST" novalidate>
          <?= csrfCampo() ?>
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
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="pf-plano">Convênio / Plano</label>
              <select id="pf-plano" name="plano"><?php foreach ($planos as $plano): ?><option <?= $plano===($dados['plano']??'Particular')?'selected':'' ?>><?=limpar($plano)?></option><?php endforeach; ?></select>
            </div>
            <div class="grupo-campo">
              <label for="pf-alergias">Alergias / Condições relevantes</label>
              <input type="text" id="pf-alergias" name="alergias" placeholder="Ex: alergia à Lidocaína, hipertensão..." value="<?=limpar($dados['alergias']??'')?>">
            </div>
          </div>
          <div class="grupo-campo">
            <label for="pf-obs">Observações adicionais</label>
            <textarea id="pf-obs" name="observacoes" rows="2" placeholder="Outras informações para o atendimento..."><?=limpar($dados['observacoes']??'')?></textarea>
          </div>
          <button type="submit" name="salvar_perfil" class="btn btn-primario"><i class="bi bi-save-fill" aria-hidden="true"></i> Salvar Alterações</button>
        </form>
      </div>
    </div>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-cabecalho"><h3><i class="bi bi-lock-fill" aria-hidden="true"></i> Alterar Senha</h3></div>
      <div class="cartao-corpo">
        <form method="POST" novalidate id="frm-senha">
          <?= csrfCampo() ?>
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

    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-universal-access" aria-hidden="true"></i> Acessibilidade</h3></div>
      <div class="cartao-corpo">
        <p style="font-size:13px;color:var(--texto-secundario);margin-bottom:14px;">Use o botão <strong><i class="bi bi-universal-access" aria-hidden="true"></i></strong> no canto inferior direito para personalizar o sistema.</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;">
          <?php foreach([['<i class="bi bi-sun-fill" aria-hidden="true"></i>/<i class="bi bi-moon-stars-fill" aria-hidden="true"></i>','Tema claro/escuro'],['A','Tamanho de fonte'],['<i class="bi bi-brightness-high-fill" aria-hidden="true"></i>','Alto contraste'],['<i class="bi bi-palette-fill" aria-hidden="true"></i>','Modo daltônico'],['<i class="bi bi-book-fill" aria-hidden="true"></i>','Fonte dislexia'],['<i class="bi bi-arrow-left-right" aria-hidden="true"></i>','Espaçamento'],['<i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>','Sem animações'],['⌨','Teclado']] as [$i,$d]): ?>
          <div style="padding:10px 12px;background:var(--fundo-campo);border:1px solid var(--borda);border-radius:var(--raio-pequeno);display:flex;align-items:center;gap:8px;font-size:13px;color:var(--texto-secundario);">
            <span><?=$i?></span><?=$d?>
          </div>
          <?php endforeach; ?>
        </div>
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
