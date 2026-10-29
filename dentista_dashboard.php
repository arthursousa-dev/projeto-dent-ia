<?php
// painel principal do dentista — visão do dia de trabalho
require_once __DIR__ . '/includes/bootstrap_sessao.php';
require_once 'includes/functions.php';
verificarSessao('dentista');carregarListas(['agendamentos', 'pacientes', 'procedimentos']);

$tituloPagina = 'Painel do Dentista';

$d=dentistaSessao(); $erro='';
if (isset($_POST['salvar_atendimento'])) {
    try { registrarEvolucao($_POST);redirecionar(paginaAtual(),'Evolução clínica registrada.'); }
    catch (Throwable $e) { $erro=mensagemOperacao($e); }
}
$agendaHoje=array_values(array_filter($listaAgendamentos,fn($a)=>$a['data']===date('Y-m-d')));
$atendidos=count(array_filter($agendaHoje,fn($a)=>$a['status']==='Concluída'));
$restantes=count(array_filter($agendaHoje,fn($a)=>in_array($a['status'],STATUS_EM_ABERTO,true)));
$mes=count(array_filter($listaAgendamentos,fn($a)=>substr($a['data'],0,7)===date('Y-m') && $a['status']==='Concluída'));
$proxima=null;
// Próximo paciente do dia: o dentista também espera quem está atrasado (por isso não usa consultaFutura(), que olha a hora).
foreach ($agendaHoje as $a) if (in_array($a['status'],STATUS_FUTURO,true)) { $proxima=$a;break; }
$listaProcedimentos=$d['procedimentos'] ?? [];
include 'includes/head.php';
?>

<div class="painel">
  <?php include 'includes/sidebar.php'; ?>

  <main class="conteudo-principal" id="conteudo-principal">

    <div class="cabecalho-pagina">
      <div>
        <h1>Olá, <?= limpar($d['nome'] ?? $_SESSION['usuario']) ?>! <i class="bi bi-person-badge-fill" aria-hidden="true"></i></h1>
        <p><?= limpar(($d['especialidade'] ?? '').' — '.($d['cro'] ?? '')) ?> — <?= data_pt('d \d\e F \d\e Y') ?></p>
      </div>
      <a href="dentista_agenda.php" class="btn btn-primario"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Ver Agenda Completa</a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>

    <!-- estatísticas do dentista -->
    <div class="grade-estatisticas">
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde" aria-hidden="true"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= $atendidos ?></strong><span>Atendidos hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica amarelo" aria-hidden="true">⏳</div>
        <div class="info-estatistica"><strong><?= $restantes ?></strong><span>Restantes hoje</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica azul" aria-hidden="true"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= $mes ?></strong><span>Atendimentos no mês</span></div>
      </div>
      <div class="cartao-estatistica">
        <div class="icone-estatistica verde-agua" aria-hidden="true"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
        <div class="info-estatistica"><strong><?= count($listaPacientes) ?></strong><span>Total de pacientes</span></div>
      </div>
    </div>

    <!-- destaque do próximo paciente -->
    <div style="background:var(--primario);border-radius:var(--raio);padding:26px 30px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px;"
         role="region" aria-label="Próximo paciente">
      <div style="color:#fff;">
        <p style="color:rgba(255,255,255,0.55);font-size:0.78rem;margin-bottom:7px;">
          <i class="bi bi-bell-fill" aria-hidden="true"></i> Próximo paciente — <?= limpar($proxima['hora'] ?? 'Sem consulta pendente') ?>
        </p>
        <h2 style="font-family:var(--fonte-titulo);font-size:1.5rem;margin-bottom:5px;"><?= limpar($proxima['paciente'] ?? 'Agenda atualizada') ?></h2>
        <p style="color:rgba(255,255,255,0.7);font-size:0.9rem;">
          Procedimento: <strong style="color:var(--destaque);"><?= limpar($proxima['procedimento'] ?? '—') ?></strong>
        </p>
        <p style="color:rgba(255,255,255,0.55);font-size:0.82rem;margin-top:3px;">
          Sala: <?= limpar($proxima['sala'] ?? '—') ?>
        </p>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="dentista_prontuario.php?paciente=<?= (int)($proxima['id_paciente'] ?? 0) ?>"
           class="btn btn-primario"><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Prontuário</a>
        <a href="dentista_agenda.php" class="btn btn-contorno">Ver Agenda</a>
      </div>
    </div>

    <!-- agenda resumida do dia -->
    <div class="cartao">
      <div class="cartao-cabecalho">
        <h3><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Agenda de Hoje — <?= data_pt('d/m/Y') ?></h3>
        <a href="dentista_agenda.php" class="cartao-link">Ver completa</a>
      </div>
      <div class="tabela-wrapper">
        <table aria-label="Agenda resumida do dia do dentista">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Paciente</th>
              <th>Procedimento</th>
              <th>Sala</th>
              <th>Status</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($agendaHoje, 0, 5) as $agendamento): ?>
            <tr>
              <td><strong><?= limpar($agendamento['hora']) ?></strong></td>
              <td><?= limpar($agendamento['paciente']) ?></td>
              <td><?= limpar($agendamento['procedimento']) ?></td>
              <td><?= limpar($agendamento['sala']) ?></td>
              <td><?= emblema($agendamento['status']) ?></td>
              <td>
                <a href="dentista_prontuario.php?paciente=<?= (int)$agendamento['id_paciente'] ?>"
                   class="cartao-link" style="font-size:0.8rem;">
                  <i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Prontuário
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- registro rápido de atendimento -->
    <div class="cartao">
      <div class="cartao-cabecalho"><h3><i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i> Registrar Atendimento Rápido</h3></div>
      <div class="cartao-corpo">
        <form method="POST" aria-label="Formulário de registro rápido de atendimento">
          <?= csrfCampo() ?>

          <div class="linha-campos">
            <div class="grupo-campo">
              <label for="rap-paciente">Paciente</label>
              <select id="rap-paciente" name="paciente">
                <?php foreach ($listaPacientes as $paciente): ?>
                  <option value="<?= (int)$paciente['id'] ?>"><?= limpar($paciente['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="grupo-campo">
              <label for="rap-procedimento">Procedimento realizado</label>
              <select id="rap-procedimento" name="procedimento">
                <?php foreach ($listaProcedimentos as $proc): ?>
                  <option><?= limpar($proc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grupo-campo">
            <label for="rap-obs">Observações clínicas</label>
            <textarea id="rap-obs" name="obs" rows="3"
                      placeholder="Descreva o procedimento, materiais utilizados, observações importantes..."></textarea>
          </div>

          <div class="grupo-campo">
            <label for="rap-retorno">Próximo retorno</label>
            <input type="date" id="rap-retorno" name="retorno" min="<?= date('Y-m-d') ?>">
          </div>

          <button type="submit" name="salvar_atendimento" class="btn btn-primario">
            <i class="bi bi-save-fill" aria-hidden="true"></i> Salvar Prontuário
          </button>
        </form>
      </div>
    </div>

  </main>
</div>

<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
