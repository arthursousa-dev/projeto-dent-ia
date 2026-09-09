<?php
// Portal DENT IA — escolha o tipo de acesso
require_once __DIR__ . '/includes/bootstrap_sessao.php';
// se já logado, redireciona para o dashboard correto
if (isset($_SESSION['perfil'])) {
    $destinos = [
        'cliente'       => 'cliente_dashboard.php',
        'recepcionista' => 'recepcionista_dashboard.php',
        'dentista'      => 'dentista_dashboard.php',
        'dono'          => 'dono_dashboard.php',
    ];
    if (isset($destinos[$_SESSION['perfil']])) {
        header('Location: ' . $destinos[$_SESSION['perfil']]);
        exit;
    }
}
?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DENT IA — Portal de Acesso</title>
  <script src="js/accessibility.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
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
      font-size: 42px;
      font-weight: 900;
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
      border: 2px solid var(--borda);
      border-radius: var(--raio);
      padding: 32px 24px;
      text-align: center;
      text-decoration: none;
      color: var(--texto-principal);
      transition: transform var(--transicao), box-shadow var(--transicao), border-color var(--transicao);
      cursor: pointer;
      box-shadow: var(--sombra-pequena);
    }
    .portal-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--sombra-grande);
      border-color: var(--destaque);
    }
    .portal-card:focus-visible {
      outline: none;
      box-shadow: var(--anel-foco), var(--sombra);
    }
    .portal-icon {
      font-size: 48px;
      margin-bottom: 14px;
      display: block;
      line-height: 1;
    }
    .portal-card-titulo {
      font-size: 18px;
      font-weight: 700;
      color: var(--texto-principal);
      margin-bottom: 6px;
    }
    .portal-card-desc {
      font-size: 13px;
      color: var(--texto-secundario);
      line-height: 1.5;
    }
    .portal-card-paciente  { border-top: 4px solid #3182ce; }
    .portal-card-recepcao  { border-top: 4px solid #f5a623; }
    .portal-card-dentista  { border-top: 4px solid var(--destaque); }
    .portal-card-dono      { border-top: 4px solid var(--primario); }
    .portal-card-paciente:hover  { border-color: #3182ce; }
    .portal-card-recepcao:hover  { border-color: #f5a623; }
    .portal-card-dentista:hover  { border-color: var(--destaque); }
    .portal-card-dono:hover      { border-color: var(--primario); }
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
      <span class="portal-icon" aria-hidden="true"><i class="bi bi-emoji-smile-fill" aria-hidden="true"></i></span>
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
