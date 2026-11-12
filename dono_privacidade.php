<?php
// Pedidos dos pacientes sobre os próprios dados (LGPD): o gestor atende ou recusa, sempre com resposta.
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once __DIR__ . '/includes/functions.php';
verificarSessao('dono');
$tituloPagina = 'Pedidos LGPD';

$erro = '';
if (isset($_POST['responder'])) {
    try {
        $atender = $_POST['responder'] === 'atender';
        // A exclusão não tem volta: o botão só vale com a caixa de confirmação marcada.
        if ($atender && ($_POST['tipo'] ?? '') === 'exclusao' && empty($_POST['confirmo'])) {
            throw new InvalidArgumentException('Para atender a exclusão, marque a caixa de confirmação.');
        }
        responderSolicitacaoTitular(idParametro($_POST['id'] ?? 0), $atender, (string)($_POST['resposta'] ?? ''));
        redirecionar(paginaAtual(), $atender ? 'Pedido atendido.' : 'Pedido recusado.');
    } catch (Throwable $e) { $erro = mensagemOperacao($e); }
}
$pedidos = solicitacoesDosTitulares();
$nomesPedido = ['correcao' => 'Correção', 'exclusao' => 'Exclusão'];
$nomesStatus = ['aberta' => 'Aberto', 'atendida' => 'Atendido', 'recusada' => 'Recusado'];

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">
    <?= exibirFlash() ?>
    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Pedidos LGPD</h1><p>Correção e exclusão pedidas pelos pacientes. Responda o quanto antes; o paciente lê a resposta no portal.</p></div>
    </div>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <p style="font-size:14px;"><strong>O que a exclusão faz:</strong> <?= limpar(TEXTO_EXCLUSAO_LGPD) ?> <strong>Não tem como desfazer.</strong></p>
      </div>
    </div>

    <?php if (!$pedidos): ?>
      <div class="cartao"><div class="cartao-corpo"><p style="font-size:14px;color:var(--texto-secundario);">Nenhum pedido até agora.</p></div></div>
    <?php endif; ?>
    <?php foreach ($pedidos as $s): ?>
    <div class="cartao" style="margin-bottom:16px;">
      <div class="cartao-corpo">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:6px;">
          <?= limpar($nomesPedido[$s['tipo']] ?? $s['tipo']) ?> — <?= limpar($s['nome']) ?>
          <span style="font-size:13px;font-weight:400;color:var(--texto-secundario);">(<?= limpar($nomesStatus[$s['status']] ?? $s['status']) ?>, pedido em <?= limpar($s['criada_em']) ?>)</span>
        </h2>
        <p style="font-size:14px;margin-bottom:10px;"><?= $s['mensagem'] ? limpar($s['mensagem']) : 'Sem mensagem do paciente.' ?></p>
        <?php if ($s['status'] !== 'aberta'): ?>
          <p style="font-size:14px;color:var(--texto-secundario);">Resposta em <?= limpar($s['respondida_em']) ?>: <?= limpar($s['resposta']) ?></p>
        <?php else: ?>
        <form method="POST">
          <?= csrfCampo() ?>
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="tipo" value="<?= limpar($s['tipo']) ?>">
          <div class="grupo-campo">
            <label for="resposta-<?= (int)$s['id'] ?>">Resposta ao paciente (ele lê no portal)</label>
            <textarea id="resposta-<?= (int)$s['id'] ?>" name="resposta" rows="2" maxlength="1000" required></textarea>
          </div>
          <?php if ($s['tipo'] === 'exclusao'): ?>
          <div class="grupo-campo">
            <label class="aceite" for="confirmo-<?= (int)$s['id'] ?>">
              <input type="checkbox" id="confirmo-<?= (int)$s['id'] ?>" name="confirmo" value="1">
              <span>Entendi que atender apaga os dados de contato e o acesso deste paciente, sem volta.</span>
            </label>
          </div>
          <?php endif; ?>
          <div style="display:flex;gap:10px;">
            <button type="submit" name="responder" value="atender" class="btn btn-primario"><?= $s['tipo'] === 'exclusao' ? 'Atender e apagar os dados' : 'Marcar como atendido' ?></button>
            <button type="submit" name="responder" value="recusar" class="btn btn-fantasma">Recusar</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </main>
</div>
<?php include 'includes/a11y_bar.php'; ?>
