<?php
// configurações gerais da clínica e da conta do dono
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dono');

$tituloPagina = 'Configurações';
$clinica = lerJsonObjeto('clinica.json');
$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $clinica['nome']      = trim($_POST['nome_clinica'] ?? $clinica['nome'] ?? '');
    $clinica['cnpj']      = trim($_POST['cnpj']         ?? $clinica['cnpj'] ?? '');
    $clinica['telefone']  = trim($_POST['telefone']      ?? $clinica['telefone'] ?? '');
    $clinica['email']     = trim($_POST['email']         ?? $clinica['email'] ?? '');
    $clinica['endereco']  = trim($_POST['endereco']      ?? $clinica['endereco'] ?? '');
    $clinica['horario']   = trim($_POST['horario']       ?? $clinica['horario'] ?? '');
    $clinica['atualizado_em'] = date('Y-m-d');
    salvarJson('clinica.json', $clinica);
    $sucesso = 'Configurações salvas em ' . data_pt('d/m/Y \à\s H:i') . '!';
}

include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-gear-fill" aria-hidden="true"></i> Configurações</h1>
        <p>Dados da clínica, horários e conta do administrador</p>
      </div>
    </div>

    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>

    <!-- dados gerais da clínica -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-hospital-fill" aria-hidden="true"></i> Dados da Clínica</h3></div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de dados da clínica">
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="cl-nome">Nome da clínica</label>
              <input type="text" id="cl-nome" name="nome_clinica" value="<?= limpar($clinica['nome'] ?? 'DENT IA') ?>">
            </div>
            <div class="grupo-campo">
              <label for="cl-cnpj">CNPJ</label>
              <input type="text" id="cl-cnpj" name="cnpj" value="00.000.000/0001-00">
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="cl-telefone">Telefone</label>
              <input type="tel" id="cl-telefone" name="telefone" value="(67) 3000-0000">
            </div>
            <div class="grupo-campo">
              <label for="cl-email">E-mail</label>
              <input type="email" id="cl-email" name="email" value="<?= limpar($clinica['email'] ?? 'contato@dentai.com') ?>">
            </div>
          </div>
          <div class="grupo-campo">
            <label for="cl-endereco">Endereço</label>
            <input type="text" id="cl-endereco" name="endereco"
                   value="<?= limpar($clinica['endereco'] ?? '') ?>">
          </div>
          <button type="submit" name="salvar" class="btn btn-primario">
            Salvar dados da clínica
          </button>
        </form>
      </div>
    </div>

    <!-- horário de funcionamento -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-clock-fill" aria-hidden="true"></i> Horário de Funcionamento</h3></div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de horário de funcionamento">
          <?php
          // dias e horários padrão
          $diasFuncionamento = [
              'Segunda-feira' => ['08:00', '18:00'],
              'Terça-feira'   => ['08:00', '18:00'],
              'Quarta-feira'  => ['08:00', '18:00'],
              'Quinta-feira'  => ['08:00', '18:00'],
              'Sexta-feira'   => ['08:00', '18:00'],
              'Sábado'        => ['08:00', '13:00'],
          ];
          $contador = 0;
          foreach ($diasFuncionamento as $dia => [$abertura, $fechamento]): ?>
          <div class="linha-campos" style="align-items:flex-end;margin-bottom:10px;">
            <div class="grupo-campo" style="margin-bottom:0;">
              <label><?= $dia ?></label>
            </div>
            <div class="grupo-campo" style="margin-bottom:0;">
              <label for="abertura-<?= $contador ?>" class="apenas-leitores">
                Abertura <?= $dia ?>
              </label>
              <input type="time" id="abertura-<?= $contador ?>"
                     name="abertura_<?= $contador ?>" value="<?= $abertura ?>"
                     aria-label="Abertura <?= $dia ?>">
            </div>
            <div style="padding-bottom:13px;color:var(--texto-fraco);font-size:0.85rem;">até</div>
            <div class="grupo-campo" style="margin-bottom:0;">
              <label for="fechamento-<?= $contador ?>" class="apenas-leitores">
                Fechamento <?= $dia ?>
              </label>
              <input type="time" id="fechamento-<?= $contador ?>"
                     name="fechamento_<?= $contador ?>" value="<?= $fechamento ?>"
                     aria-label="Fechamento <?= $dia ?>">
            </div>
          </div>
          <?php $contador++; endforeach; ?>
          <button type="submit" name="salvar" class="btn btn-primario" style="margin-top:10px;">
            Salvar horários
          </button>
        </form>
      </div>
    </div>

    <!-- dados da conta do administrador -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-person-fill" aria-hidden="true"></i> Minha Conta</h3></div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de dados da conta">
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="adm-nome">Nome</label>
              <input type="text" id="adm-nome" name="nome"
                     value="Arthur Sousa" autocomplete="name">
            </div>
            <div class="grupo-campo">
              <label for="adm-email">E-mail</label>
              <input type="email" id="adm-email" name="email"
                     value="<?= limpar($clinica['email'] ?? 'dono@dentai.com') ?>" autocomplete="email">
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="adm-senha">Nova senha</label>
              <input type="password" id="adm-senha" name="senha"
                     placeholder="••••••••" autocomplete="new-password">
              <div class="dica-campo">Deixe em branco para manter a senha atual</div>
            </div>
            <div class="grupo-campo">
              <label for="adm-confirmar">Confirmar nova senha</label>
              <input type="password" id="adm-confirmar" name="confirmar_senha"
                     placeholder="••••••••" autocomplete="new-password">
            </div>
          </div>
          <button type="submit" name="salvar" class="btn btn-primario">
            Atualizar minha conta
          </button>
        </form>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
