<?php
// formulário completo para criar um novo agendamento
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('recepcionista');

$tituloPagina = 'Novo Agendamento';

// pré-preenche o nome do paciente se veio da lista de pacientes
$pacientePreenchido = limpar($_GET['paciente'] ?? '');

$sucesso='';$erro='';
if (isset($_POST['confirmar_agendamento'])) {
    try { $id=criarAgendamento($_POST); $sucesso='Agendamento registrado: #'.$id.'.'; }
    catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$pdo=\App\Config\Database::getConnection();
$salas=$pdo->query('SELECT * FROM sala ORDER BY nome')->fetchAll();
$formas=$pdo->query('SELECT * FROM forma_pagamento ORDER BY nome')->fetchAll();
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1><i class="bi bi-plus-circle-fill" aria-hidden="true"></i> Novo Agendamento</h1>
        <p>Preencha os dados para criar uma nova consulta</p>
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
        <form method="POST" aria-label="Formulário completo de agendamento">
          <?= csrfCampo() ?>

          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="ag-paciente">Paciente (nome ou CPF)</label>
              <input type="text" id="ag-paciente" name="paciente" list="pacientes-cadastrados"
                     value="<?= $pacientePreenchido ?>"
                     placeholder="Buscar paciente..." required>
              <datalist id="pacientes-cadastrados"><?php foreach ($listaPacientes as $p): ?><option value="<?= limpar($p['cpf']) ?>"><?= limpar($p['nome']) ?></option><?php endforeach; ?></datalist>
            </div>
            <div class="grupo-campo">
              <label for="ag-procedimento">Procedimento</label>
              <select id="ag-procedimento" name="procedimento" required>
                <option value="">Selecione o procedimento...</option>
                <?php foreach ($listaProcedimentos as $procedimento): ?>
                  <option><?= limpar($procedimento) ?></option>
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
                  <option value="<?= $dentista['id'] ?>"><?= limpar($dentista['nome']) ?> — <?= limpar($dentista['especialidade']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="ag-sala">Sala</label>
              <select id="ag-sala" name="sala">
                <option value="">Sala padrão do dentista</option>
                <?php foreach ($salas as $sala): ?><option><?= limpar($sala['nome']) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="ag-data">Data da consulta</label>
              <input type="date" id="ag-data" name="data"
                     min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="grupo-campo">
              <label for="ag-horario">Horário</label>
              <select id="ag-horario" name="horario" required>
                <?php foreach ($listaHorarios as $horario): ?>
                  <option><?= $horario ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grupo-campo"><label for="ag-valor">Valor estimado (R$)</label>
          <input type="number" id="ag-valor" name="valor" step="0.01" min="0" placeholder="Preço do catálogo se vazio"></div>
          <div class="grupo-campo">
            <label for="ag-obs">Observações</label>
            <textarea id="ag-obs" name="obs" rows="3"
                      placeholder="Informações relevantes sobre o paciente ou o procedimento..."></textarea>
          </div>

          <div style="display:flex;gap:10px;">
            <button type="submit" name="confirmar_agendamento" class="btn btn-primario">
              <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Confirmar Agendamento
            </button>
            <a href="recepcionista_dashboard.php" class="btn btn-fantasma">Cancelar</a>
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
            foreach ($listaDentistas as $d) {
                $livres=horariosDisponiveis($d['id'],date('Y-m-d'));
                $stmt=$pdo->prepare("SELECT count(*) FROM agendamentos WHERE id_dentista=:id AND data=CURRENT_DATE AND status<>'Cancelado'");
                $stmt->execute(['id'=>$d['id']]);
                $disponibilidade[]=[$d['nome'],$d['especialidade'],(string)$stmt->fetchColumn(),$livres[0] ?? 'Sem horário',$livres ? 'Disponível' : 'Lotado'];
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
</body>
</html>
