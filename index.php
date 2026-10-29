<?php
// Portal DENT IA — escolha o tipo de acesso
require_once __DIR__ . '/includes/bootstrap_sessao.php';
// se já logado, redireciona para o dashboard correto
require_once __DIR__ . '/includes/functions.php';
if (isset(PERFIS[$_SESSION['perfil'] ?? '']['painel'])) {
    header('Location: ' . PERFIS[$_SESSION['perfil']]['painel']);
    exit;
}
?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DENT IA — Portal de Acesso</title>
  <script src="js/accessibility.js"></script>
  <script src="js/app.js?v=<?= filemtime(__DIR__ . "/js/app.js") ?>" defer></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" integrity="sha384-CK2SzKma4jA5H/MXDUU7i1TqZlCFaD4T01vtyDFvPlD97JQyS+IsSh1nI2EFbpyk" crossorigin="anonymous">
  <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . "/css/style.css") ?>">
  <style>
    .portal-wrapper {
      min-height: 100vh;
      background: var(--fundo-corpo);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }
    .portal-logo {
      font-family: var(--fonte-titulo);
      font-size: 40px;
      font-weight: 700;
      color: var(--primario);
      margin-bottom: 6px;
      letter-spacing: -1px;
    }
    .portal-logo span { color: var(--destaque); }
    .portal-sub {
      font-size: 15px;
      color: var(--texto-secundario);
      margin-bottom: 48px;
      text-align: center;
    }
    .portal-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      max-width: 640px;
      width: 100%;
    }
    .portal-card {
      background: var(--fundo-cartao);
      border: 1px solid var(--borda);
      border-left: 4px solid var(--cor-card);
      border-radius: var(--raio);
      padding: 20px 22px;
      display: grid;
      grid-template-columns: auto 1fr;
      column-gap: 16px;
      align-items: start;
      text-decoration: none;
      color: var(--texto-principal);
      transition: background var(--transicao), border-color var(--transicao);
    }
    .portal-card:hover { background: var(--fundo-tabela-cabeca); border-color: var(--cor-card); color: var(--texto-principal); }
    .portal-card:focus-visible { outline: none; box-shadow: var(--anel-foco); }
    .portal-icon {
      font-size: 26px;
      color: var(--cor-card);
      grid-row: span 2;
      line-height: 1;
    }
    .portal-card-titulo {
      font-size: 1.05rem;
      font-weight: 600;
      color: var(--texto-principal);
      margin-bottom: 6px;
    }
    .portal-card-desc {
      font-size: 13px;
      color: var(--texto-secundario);
      line-height: 1.5;
    }
    /* mesmas cores de cada perfil nas telas de login */
    .portal-card-paciente { --cor-card: #2b6cb0; }
    .portal-card-recepcao { --cor-card: #a85410; }
    .portal-card-dentista { --cor-card: #08806f; }
    .portal-card-dono     { --cor-card: #0e2a3f; }
    [data-tema="dark"] .portal-card-dono { --cor-card: #9fb4c7; }
    .portal-footer {
      margin-top: 36px;
      font-size: 12px;
      color: var(--texto-fraco);
      text-align: center;
    }
    @media (max-width: 520px) {
      .portal-grid { grid-template-columns: 1fr; max-width: 320px; }
      .portal-logo { font-size: 32px; }
    }
  </style>
</head>
<body>
<main class="portal-wrapper" id="conteudo-principal">
  <div class="portal-logo">DENT<span>IA</span></div>
  <p class="portal-sub">Plataforma inteligente de gestão odontológica<br>Selecione o tipo de acesso para continuar</p>

  <nav class="portal-grid" aria-label="Opções de acesso ao sistema">

    <a href="login_paciente.php" class="portal-card portal-card-paciente" aria-label="Área do Paciente">
      <span class="portal-icon" aria-hidden="true"><i class="bi bi-person-fill" aria-hidden="true"></i></span>
      <div class="portal-card-titulo">Paciente</div>
      <div class="portal-card-desc">Agende consultas, veja seu histórico e gerencie seu perfil</div>
    </a>

    <a href="login_recepcionista.php" class="portal-card portal-card-recepcao" aria-label="Área da Recepção">
      <span class="portal-icon" aria-hidden="true"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
      <div class="portal-card-titulo">Recepcionista</div>
      <div class="portal-card-desc">Gerencie agendamentos, pacientes e a agenda do dia</div>
    </a>

    <a href="login_dentista.php" class="portal-card portal-card-dentista" aria-label="Área do Dentista">
      <span class="portal-icon" aria-hidden="true"><i class="bi bi-person-badge-fill" aria-hidden="true"></i></span>
      <div class="portal-card-titulo">Dentista</div>
      <div class="portal-card-desc">Acesse sua agenda, prontuários e histórico clínico</div>
    </a>

    <a href="login_dono.php" class="portal-card portal-card-dono" aria-label="Área do Gestor">
      <span class="portal-icon" aria-hidden="true"><i class="bi bi-award-fill" aria-hidden="true"></i></span>
      <div class="portal-card-titulo">Gestor / Dono</div>
      <div class="portal-card-desc">Dashboard financeiro, relatórios e administração da clínica</div>
    </a>

  </nav>

  <p class="portal-footer">DENT IA © 2026 — Todos os direitos reservados</p>
</main>
<?php include 'includes/a11y_bar.php'; ?>
</body>
</html>
