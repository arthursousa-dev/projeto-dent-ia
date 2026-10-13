<?php
// painel da recepcionista — controle do dia a dia da recepção
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('recepcionista');

$tituloPagina = 'Painel da Recepção';

// processa as ações rápidas da tabela de agenda
$sucesso='';$erro='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (isset($_POST['agendar_rapido'])) { $novoId=criarAgendamento($_POST); header('Location: recepcionista_agenda.php?data='.urlencode($_POST['data']).'&criado='.$novoId); exit; }
        elseif (isset($_POST['receber'])) confirmarPagamento((int)$_POST['id'],(int)$_POST['forma']);
        else {
            $status=isset($_POST['fazer_checkin']) ? 'Aguardando' : (isset($_POST['cancelar_consulta']) ? 'Cancelado' : ($_POST['status'] ?? ''));
            alterarStatusAgenda((int)($_POST['id'] ?? 0),$status);
        }
        $sucesso='Consulta atualizada.';
    } catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
// Dashboard mostra somente consultas de hoje e consulta o banco a cada acesso.
$pdo = \App\Config\Database::getConnection();
$stmtHoje = $pdo->prepare("SELECT a.id,a.id_paciente,a.id_dentista,to_char(a.data,'YYYY-MM-DD') AS data,to_char(a.hora,'HH24:MI') AS hora,a.status::text AS status,a.valor,a.observacoes,pp.nome AS paciente,pd.nome AS dentista,pr.nome AS servico,pr.nome AS procedimento, s.nome AS sala,(pg.pago_em IS NOT NULL) AS pago,COALESCE(fp.nome,'') AS forma_pagamento FROM agendamentos a JOIN pacientes pac ON pac.id=a.id_paciente JOIN pessoa pp ON pp.id=pac.id_pessoa JOIN dentistas d ON d.id=a.id_dentista JOIN pessoa pd ON pd.id=d.id_pessoa LEFT JOIN procedimentos pr ON pr.id=a.id_procedimento JOIN sala s ON s.id=a.id_sala LEFT JOIN pagamento pg ON pg.id_agendamento=a.id LEFT JOIN forma_pagamento fp ON fp.id=pg.id_forma_pagamento WHERE a.data=:data ORDER BY a.hora, a.id");
$stmtHoje->execute(['data'=>date('Y-m-d')]);
$listaAgendamentos = $stmtHoje->fetchAll(PDO::FETCH_ASSOC);
foreach ($listaAgendamentos as &$registroAgenda) $registroAgenda['pago']=booleanoPg($registroAgenda['pago']);
unset($registroAgenda);
$formas=$pdo->query('SELECT id,nome FROM forma_pagamento ORDER BY id')->fetchAll();
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1>Painel da Recepção <i class="bi bi-folder2-open" aria-hidden="true"></i></h1>
        <p><?= data_pt('l, d \d\e F \d\e Y') ?></p>
      </div>
      <a href="recepcionista_novo_agendamento.php" class="btn btn-primario">
        + Novo Agendamento
      </a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= limpar($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?>
      <div class="alerta alerta-sucesso" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= limpar($sucesso) ?></div>
    <?php endif; ?>

    <!-- estatísticas do dia -->
    <div class="grade-estatisticas">
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= count(array_filter($listaAgendamentos,fn($a)=>$a['status']==='Concluída')) ?></strong><span>Atendidos hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo" aria-hidden="true">⏳</div>
        <div class="info-estatistica"><strong><?= count(array_filter($listaAgendamentos,fn($a)=>$a['status']==='Aguardando')) ?></strong><span>Aguardando</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= count($listaAgendamentos) ?></strong><span>Total hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica vermelho" aria-hidden="true"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= count(array_filter($listaAgendamentos,fn($a)=>$a['status']==='Cancelado')) ?></strong><span>Cancelados</span></div>
      </div>
    </div>

    <!-- agenda do dia com ações de check-in -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agenda de Hoje — <?= data_pt('d/m/Y') ?></h3>
        <a href="recepcionista_agenda.php" class="cartao-link">Ver agenda completa →</a>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Agenda de consultas de hoje">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Paciente</th>
              <th>Procedimento</th>
              <th>Dentista</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($listaAgendamentos, 0, 5) as $agendamento): ?>
            <tr>
              <td><strong><?= limpar($agendamento['hora']) ?></strong></td>
              <td><?= limpar($agendamento['paciente']) ?></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['dentista']) ?></td>
              <td><?= emblema($agendamento['status']) ?></td>
              <td>
                <div style="display:flex;gap:5px;">
                  <?php if (in_array($agendamento['status'], ['Agendado', 'Confirmado'])): ?>
                  <!-- botão de check-in -->
                  <form method="POST" style="display:inline;">
          <?= csrfCampo() ?>
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="fazer_checkin"
                            class="btn btn-primario btn-pequeno"
                            aria-label="Fazer check-in de <?= limpar($agendamento['paciente']) ?>">
                      <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Check-in
                    </button>
                  </form>
                  <!-- botão de cancelar -->
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Cancelar consulta de <?= limpar($agendamento['paciente']) ?>
          <?= csrfCampo() ?>?')">
                    <input type="hidden" name="id" value="<?= $agendamento['id'] ?>">
                    <button type="submit" name="cancelar_consulta"
                            class="btn btn-perigo btn-pequeno"
                            aria-label="Cancelar consulta de <?= limpar($agendamento['paciente']) ?>">
                      <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                    </button>
                  </form>
                  <?php else: ?>
                  <a href="recepcionista_pacientes.php" class="btn btn-fantasma btn-pequeno">Ver</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- formulário de agendamento rápido -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> Agendamento Rápido</h3>
        <a href="recepcionista_novo_agendamento.php" class="cartao-link">Formulário completo →</a>
      </div>
      <div class="cartao-corpo">
        <form method="POST" data-agendamento-dinamico aria-label="Formulário de agendamento rápido">
          <?= csrfCampo() ?>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="rapido-paciente">Paciente</label>
              <select id="rapido-paciente" name="paciente_id" required>
                <option value="">Selecione um paciente...</option>
                <?php foreach ($listaPacientes as $p): if ($p['status'] !== 'Ativo') continue; ?>
                <option value="<?= (int)$p['id'] ?>"><?= limpar($p['nome']) ?> — <?= limpar($p['cpf']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="rapido-procedimento">Procedimento</label>
              <select id="rapido-procedimento" name="procedimento">
                <?php foreach ($listaProcedimentos as $procedimento): ?>
                  <option><?= limpar($procedimento) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="rapido-dentista">Dentista</label>
              <select id="rapido-dentista" name="dentista">
                <?php foreach ($listaDentistas as $dentista): ?>
                  <option value="<?= (int)$dentista['id'] ?>"><?= limpar($dentista['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="rapido-data">Data</label>
              <input type="date" id="rapido-data" name="data"
                     min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="grupo-campo">
              <label for="rapido-horario">Horário</label>
              <select id="rapido-horario" name="horario">
                <?php foreach ($listaHorarios as $horario): ?>
                  <option><?= $horario ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit" name="agendar_rapido" class="btn btn-primario">
            Confirmar agendamento →
          </button>
        </form>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
<script src="js/agenda-horarios.js" defer></script>
</body>
</html>
