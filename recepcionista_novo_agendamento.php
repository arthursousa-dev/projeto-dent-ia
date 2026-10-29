<?php
// formulário completo para criar um novo agendamento
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('recepcionista');carregarListas(['dentistas', 'horarios', 'pacientes', 'procedimentos']);

$tituloPagina = 'Novo Agendamento';

// pré-preenche o nome do paciente se veio da lista de pacientes
$pacientePreenchido = idParametro($_POST['paciente_id'] ?? $_GET['paciente_id'] ?? 0);
$dataPreenchida = (string)($_POST['data'] ?? $_GET['data'] ?? date('Y-m-d'));
$validacaoData = DateTimeImmutable::createFromFormat('!Y-m-d', $dataPreenchida);
if (!$validacaoData || $validacaoData->format('Y-m-d') !== $dataPreenchida || $dataPreenchida < date('Y-m-d')) $dataPreenchida = date('Y-m-d');

$sucesso='';$erro='';
$pdo=\App\Config\Database::getConnection();
// remarcação: mesmo formulário, com paciente e procedimento da consulta original
$reagendar=idParametro($_POST['reagendar'] ?? $_GET['reagendar'] ?? 0);
if (($_POST['reagendar'] ?? $_GET['reagendar'] ?? '')!=='' && !$reagendar) paginaErro(404, 'Consulta não encontrada', 'Consulta para remarcar não encontrada.');
$original=null;
if ($reagendar) {
    $stmt=$pdo->prepare('SELECT * FROM vw_agendamentos WHERE id=:id');
    $stmt->execute(['id'=>$reagendar]);
    $original=$stmt->fetch() ?: null;
    // Sem a consulta original não há o que remarcar; nunca cai em criarAgendamento.
    if (!$original) paginaErro(404, 'Consulta não encontrada', 'Consulta para remarcar não encontrada.');
    if (!in_array($original['status'], ['Agendado','Confirmado'], true)) {
        paginaErro(409, 'Consulta não pode ser remarcada', 'Esta consulta está "'.$original['status'].'". Só é possível remarcar consultas agendadas ou confirmadas.');
    }
    else {
        $pacientePreenchido=(int)$original['id_paciente'];
        $_POST+=['procedimento'=>$original['servico'],'dentista'=>$original['id_dentista']];
    }
}
if (isset($_POST['confirmar_agendamento'])) {
    try {
        $id=$reagendar ? reagendarAgendamento($reagendar,$_POST) : criarAgendamento($_POST);
        redirecionar('recepcionista_agenda.php?data='.urlencode((string)($_POST['data'] ?? '')), $reagendar ? 'Consulta remarcada. Novo agendamento #'.$id.'; a consulta anterior foi cancelada.' : 'Agendamento #'.$id.' criado e vinculado ao paciente.');
    }
    catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$salas=[];
$formas=[];
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><?= $original ? 'Remarcar consulta' : 'Novo Agendamento' ?></h1>
        <p><?= $original ? 'Escolha a nova data e horário. A consulta original será cancelada quando a nova for confirmada.' : 'Preencha os dados para criar uma nova consulta' ?></p>
      </div>
      <a href="recepcionista_agenda.php" class="btn btn-fantasma">← Voltar à Agenda</a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status" aria-live="polite">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?>
      </div>
    <?php endif; ?>

    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Dados do Agendamento</h3></div>
      <div class="cartao-corpo">
        <?php if ($original): ?>
        <div class="alerta alerta-info" role="status">
          Remarcando a consulta #<?= (int)$original['id'] ?> de <?= limpar($original['paciente']) ?>:
          <?= limpar($original['servico']) ?> em <?= limpar(date('d/m/Y', strtotime($original['data']))) ?> às <?= limpar($original['hora']) ?>.
        </div>
        <?php endif; ?>
        <form method="POST" data-agendamento-dinamico aria-label="<?= $original ? 'Remarcar consulta' : 'Formulário completo de agendamento' ?>">
          <?= csrfCampo() ?>
          <?php if ($original): ?><input type="hidden" name="reagendar" value="<?= (int)$original['id'] ?>">
          <!-- campos desabilitados não são enviados: estes repassam os valores fixos da consulta original -->
          <input type="hidden" name="paciente_id" value="<?= (int)$original['id_paciente'] ?>">
          <input type="hidden" name="procedimento" value="<?= limpar($original['servico']) ?>"><?php endif; ?>

          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="ag-paciente">Paciente cadastrado</label>
              <select id="ag-paciente" name="paciente_id" required <?= $original ? 'disabled' : '' ?>>
                <option value="">Selecione um paciente...</option>
                <?php foreach ($listaPacientes as $p): if ($p['status'] !== 'Ativo') continue; ?>
                  <option value="<?= (int)$p['id'] ?>" <?= $pacientePreenchido === (int)$p['id'] ? 'selected' : '' ?>><?= limpar($p['nome']) ?> — <?= limpar($p['cpf']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$original): ?><small>Não encontrou? <a href="recepcionista_pacientes.php">Cadastre o paciente primeiro.</a></small><?php endif; ?>
            </div>
            <div class="grupo-campo">
              <label for="ag-procedimento">Procedimento</label>
              <select id="ag-procedimento" name="procedimento" required <?= $original ? 'disabled' : '' ?>>
                <option value="">Selecione o procedimento...</option>
                <?php foreach ($listaProcedimentos as $procedimento): ?>
                  <option <?= ($_POST['procedimento'] ?? '')===$procedimento ? 'selected' : '' ?>><?= limpar($procedimento) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="ag-dentista">Dentista responsável</label>
              <select id="ag-dentista" name="dentista" required>
                <option value="">Selecione o dentista...</option>
                <?php foreach ($listaDentistas as $dentista): ?>
                  <option value="<?= $dentista['id'] ?>" <?= (int)($_POST['dentista'] ?? 0)===(int)$dentista['id'] ? 'selected' : '' ?>><?= limpar($dentista['nome']) ?> — <?= limpar($dentista['especialidade']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="ag-sala">Sala</label>
              <select id="ag-sala" name="sala">
                <option value="">Sala padrão do dentista</option>
                <?php foreach ($listaDentistas as $dentista): if (!empty($dentista['sala'])): ?><option><?= limpar($dentista['sala']) ?></option><?php endif; endforeach; ?>
              </select>
            </div>
          </div>

          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="ag-data">Data da consulta</label>
              <input type="date" id="ag-data" name="data"
                     min="<?= date('Y-m-d') ?>" value="<?= limpar($dataPreenchida) ?>" required>
            </div>
            <div class="grupo-campo">
              <label for="ag-horario">Horário</label>
              <select id="ag-horario" name="horario" required>
                <?php foreach ($listaHorarios as $horario): ?>
                  <option <?= ($_POST['horario'] ?? '')===$horario ? 'selected' : '' ?>><?= $horario ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grupo-campo"><label for="ag-valor">Valor estimado (R$)</label>
          <input type="number" id="ag-valor" name="valor" step="0.01" min="0" placeholder="Preço do catálogo se vazio" value="<?= limpar((string)($_POST['valor'] ?? '')) ?>"></div>
          <div class="grupo-campo">
            <label for="ag-obs">Observações</label>
            <textarea id="ag-obs" name="obs" rows="3"
                      placeholder="Informações relevantes sobre o paciente ou o procedimento..."><?= limpar((string)($_POST['obs'] ?? '')) ?></textarea>
          </div>

          <div style="display:flex;gap:10px;">
            <button type="submit" name="confirmar_agendamento" class="btn btn-primario">
              <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= $original ? 'Confirmar remarcação' : 'Confirmar Agendamento' ?>
            </button>
            <a href="<?= $original ? 'recepcionista_agenda.php?data='.urlencode($original['data']) : 'recepcionista_dashboard.php' ?>" class="btn btn-fantasma"><?= $original ? 'Manter consulta original' : 'Cancelar' ?></a>
          </div>
        </form>
      </div>
    </div>

    <!-- disponibilidade dos dentistas hoje -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-person-badge-fill" aria-hidden="true"></i> Disponibilidade de Hoje</h3></div>
      <div class="tabela-wrapper">
        <table aria-label="Disponibilidade dos dentistas hoje">
          <thead>
            <tr>
              <th>Dentista</th>
              <th>Especialidade</th>
              <th>Consultas</th>
              <th>Próximo horário livre</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $disponibilidade=[];
            $livresHoje=proximosHorariosPorDentista(date('Y-m-d'));
            $consultasHoje=$pdo->query("SELECT id_dentista,count(*) FROM agendamentos WHERE data=CURRENT_DATE AND status<>'Cancelado' GROUP BY id_dentista")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($listaDentistas as $d) {
                $livres=$livresHoje[(int)$d['id']] ?? [];
                $disponibilidade[]=[$d['nome'],$d['especialidade'],(string)($consultasHoje[(int)$d['id']] ?? 0),$livres[0] ?? 'Sem horário',$livres ? 'Disponível' : 'Lotado'];
            }
            foreach ($disponibilidade as [$nome, $especialidade, $consultas, $proximo, $situacao]): ?>
            <tr>
              <td><strong><?= limpar($nome) ?></strong></td>
              <td><?= limpar($especialidade) ?></td>
              <td><?= limpar($consultas) ?></td>
              <td><?= limpar($proximo) ?></td>
              <td><?= emblema($situacao) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
<script src="js/agenda-horarios.js?v=<?= filemtime(__DIR__ . "/js/agenda-horarios.js") ?>" defer></script>
</body>
</html>
