<?php
// Privacidade do paciente (LGPD): para que servem os dados, baixar cópia, pedir correção ou exclusão.
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('cliente');
$tituloPagina = 'Privacidade';

$erro = '';
if (isset($_POST['baixar_dados'])) {
    try {
        $dados = exportarDadosTitular();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="meus-dados-dentia-' . date('Ymd') . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    } catch (Throwable $e) { $erro = mensagemOperacao($e); }
}
if (isset($_POST['enviar_pedido'])) {
    try {
        solicitarDireitoTitular((string)($_POST['tipo'] ?? ''), (string)($_POST['mensagem'] ?? ''));
        redirecionar(paginaAtual(), 'Pedido enviado. A resposta da clínica aparece nesta página.');
    } catch (Throwable $e) { $erro = mensagemOperacao($e); }
}

$paciente = pacienteSessao();
$pedidos = $paciente ? solicitacoesDoPaciente((int)$paciente['id']) : [];
$aceite = $paciente ? consentimentoDoPaciente((int)$paciente['id']) : null;
$clinica = lerJsonObjeto('clinica.json');
$nomesPedido = ['copia' => 'Cópia dos dados', 'correcao' => 'Correção', 'exclusao' => 'Exclusão'];
$nomesStatus = ['aberta' => 'Em análise', 'atendida' => 'Atendido', 'recusada' => 'Recusado'];

include 'includes/head.php';
?>
<div class="painel">
  <?php include 'includes/sidebar.php'; ?>
  <main class="conteudo-principal" id="conteudo-principal">
    <?= exibirFlash() ?>
    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <div class="cabecalho-pagina">
      <div><h1><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Privacidade</h1><p>Seus dados na clínica e os seus direitos (LGPD)</p></div>
    </div>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;">Quais dados guardamos e para quê</h2>
        <div class="tabela-wrapper"><table>
          <thead><tr><th scope="col">Dado</th><th scope="col">Para que serve</th></tr></thead>
          <tbody>
            <tr><td>Nome, CPF e data de nascimento</td><td>Identificar você e o seu prontuário.</td></tr>
            <tr><td>Telefone, e-mail e endereço</td><td>Marcar, confirmar e avisar sobre consultas.</td></tr>
            <tr><td>Alergias, medicamentos, odontograma e evoluções</td><td>Atender você com segurança. Fazem parte do prontuário, guardado por no mínimo 20 anos (Lei 13.787/2018).</td></tr>
            <tr><td>Consultas e pagamentos</td><td>Agenda, cobrança e obrigações fiscais da clínica.</td></tr>
            <tr><td>E-mail de acesso e senha (guardada cifrada)</td><td>Entrar no portal com segurança.</td></tr>
          </tbody>
        </table></div>
        <p style="font-size:14px;margin-top:14px;">
          <strong>Com quem falar (encarregado dos dados):</strong>
          <?= limpar($clinica['nome'] ?? 'a clínica') ?><?= !empty($clinica['email']) ? ' — ' . limpar($clinica['email']) : '' ?><?= !empty($clinica['telefone']) ? ' — ' . limpar($clinica['telefone']) : '' ?>.
        </p>
        <p style="font-size:14px;color:var(--texto-secundario);">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          <?php if ($aceite): ?>
            Você aceitou o termo de uso de dados (versão <?= limpar($aceite['versao_termo']) ?>) em <?= limpar($aceite['aceito_em']) ?>, <?= $aceite['origem'] === 'site' ? 'pelo site' : 'na recepção' ?>.
          <?php else: ?>
            Ainda não há aceite do termo de uso de dados registrado neste sistema. Procure a recepção.
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:8px;">Baixar uma cópia dos meus dados</h2>
        <p style="font-size:14px;margin-bottom:12px;">Um arquivo com o seu cadastro, as suas consultas e o seu prontuário. Guarde em lugar seguro: ele tem dados de saúde.</p>
        <form method="POST">
          <?= csrfCampo(false) ?>
          <button type="submit" name="baixar_dados" class="btn btn-primario"><i class="bi bi-download" aria-hidden="true"></i> Baixar meus dados</button>
        </form>
      </div>
    </div>

    <div class="cartao" style="margin-bottom:20px;">
      <div class="cartao-corpo">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:8px;">Pedir correção ou exclusão</h2>
        <p style="font-size:14px;margin-bottom:8px;">Telefone, nascimento, plano e alergias você mesmo corrige em <a href="cliente_perfil.php">Meu Perfil</a>. Use o pedido de correção para o que não dá para mudar por lá, como o CPF.</p>
        <p style="font-size:14px;margin-bottom:12px;"><?= limpar(TEXTO_EXCLUSAO_LGPD) ?></p>
        <form method="POST">
          <?= csrfCampo() ?>
          <div class="grupo-campo">
            <label for="pedido-tipo">O que você quer pedir</label>
            <select id="pedido-tipo" name="tipo" required>
              <option value="correcao" <?= ($_POST['tipo'] ?? '') === 'correcao' ? 'selected' : '' ?>>Correção de um dado</option>
              <option value="exclusao" <?= ($_POST['tipo'] ?? '') === 'exclusao' ? 'selected' : '' ?>>Exclusão dos meus dados</option>
            </select>
          </div>
          <div class="grupo-campo">
            <label for="pedido-mensagem">Explique o pedido (obrigatório na correção)</label>
            <textarea id="pedido-mensagem" name="mensagem" rows="3" maxlength="1000"><?= limpar($_POST['mensagem'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="enviar_pedido" class="btn btn-escuro">Enviar pedido</button>
        </form>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao-corpo">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;">Meus pedidos</h2>
        <?php if (!$pedidos): ?>
          <p style="font-size:14px;color:var(--texto-secundario);">Você ainda não fez nenhum pedido.</p>
        <?php else: ?>
        <div class="tabela-wrapper"><table>
          <thead><tr><th scope="col">Data</th><th scope="col">Pedido</th><th scope="col">Situação</th><th scope="col">Resposta da clínica</th></tr></thead>
          <tbody>
          <?php foreach ($pedidos as $s): ?>
            <tr>
              <td><?= limpar($s['criada_em']) ?></td>
              <td><?= limpar($nomesPedido[$s['tipo']] ?? $s['tipo']) ?><?= $s['mensagem'] ? ': ' . limpar($s['mensagem']) : '' ?></td>
              <td><?= limpar($nomesStatus[$s['status']] ?? $s['status']) ?></td>
              <td><?= $s['resposta'] ? limpar($s['resposta']) . ' (' . limpar($s['respondida_em']) . ')' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<?php include 'includes/a11y_bar.php'; ?>
