<?php
// includes/sidebar.php — menu lateral reutilizável para todas as páginas

$perfilAtual     = $_SESSION['perfil']  ?? '';
$nomeUsuario     = $_SESSION['usuario'] ?? '';
$iniciaisUsuario = iniciais($nomeUsuario);
$paginaAtual     = basename($_SERVER['PHP_SELF']);

// link da página de perfil de cada perfil de acesso (mapa único em includes/functions.php)
$linkPerfil = PERFIS[$perfilAtual]['perfil'] ?? '#';

$menusPorPerfil = [
    'dono' => [
        'Gestão' => [
            ['dono_dashboard.php',     '<i class="bi bi-bar-chart-fill" aria-hidden="true"></i>', 'Painel Geral'],
            ['dono_faturamento.php',   '<i class="bi bi-cash-coin" aria-hidden="true"></i>', 'Faturamento'],
            ['dono_dentistas.php',     '<i class="bi bi-person-badge-fill" aria-hidden="true"></i>', 'Dentistas'],
            ['dono_pacientes.php',     '<i class="bi bi-people-fill" aria-hidden="true"></i>', 'Pacientes'],
            ['dono_relatorios.php',    '<i class="bi bi-graph-up-arrow" aria-hidden="true"></i>', 'Relatórios'],
        ],
        'Sistema' => [
            ['dono_usuarios.php', '<i class="bi bi-people-fill" aria-hidden="true"></i>', 'Usuários'],
            ['dono_perfil.php',        '<i class="bi bi-person-fill" aria-hidden="true"></i>', 'Meu Perfil'],
            ['dono_configuracoes.php', '<i class="bi bi-gear-fill" aria-hidden="true"></i>', 'Configurações'],
        ],
    ],
    'recepcionista' => [
        'Recepção' => [
            ['recepcionista_dashboard.php',        '<i class="bi bi-house-door-fill" aria-hidden="true"></i>', 'Painel'],
            ['recepcionista_agenda.php',           '<i class="bi bi-calendar-event-fill" aria-hidden="true"></i>', 'Agenda do Dia'],
            ['recepcionista_pacientes.php',        '<i class="bi bi-people-fill" aria-hidden="true"></i>', 'Pacientes'],
            ['recepcionista_novo_agendamento.php', '<i class="bi bi-plus-circle-fill" aria-hidden="true"></i>', 'Novo Agendamento'],
        ],
        'Sistema' => [
            ['recepcionista_perfil.php', '<i class="bi bi-person-fill" aria-hidden="true"></i>', 'Meu Perfil'],
        ],
    ],
    'dentista' => [
        'Dentista' => [
            ['dentista_dashboard.php',  '<i class="bi bi-house-door-fill" aria-hidden="true"></i>', 'Painel'],
            ['dentista_agenda.php',     '<i class="bi bi-calendar-event-fill" aria-hidden="true"></i>', 'Minha Agenda'],
            ['dentista_pacientes.php',  '<i class="bi bi-people-fill" aria-hidden="true"></i>', 'Meus Pacientes'],
            ['dentista_prontuario.php', '<i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i>', 'Prontuários'],
        ],
        'Sistema' => [
            ['dentista_perfil.php', '<i class="bi bi-person-fill" aria-hidden="true"></i>', 'Meu Perfil'],
        ],
    ],
    'cliente' => [
        'Minha Área' => [
            ['cliente_dashboard.php',   '<i class="bi bi-house-door-fill" aria-hidden="true"></i>', 'Início'],
            ['cliente_agendamento.php', '<i class="bi bi-calendar-event-fill" aria-hidden="true"></i>', 'Agendar Consulta'],
            ['cliente_consultas.php',   '<i class="bi bi-clipboard2-pulse-fill" aria-hidden="true"></i>', 'Minhas Consultas'],
            ['cliente_perfil.php',      '<i class="bi bi-person-fill" aria-hidden="true"></i>', 'Meu Perfil'],
        ],
        'Sistema' => [
        ],
    ],
];

// "Banco de dados" só aparece para o gestor e só onde a tela abre (este computador, ou a rede se liberada no .env).
if ($perfilAtual === 'dono' && monitorPermitido()) {
    $menusPorPerfil['dono']['Sistema'][] = ['dono_banco.php', '<i class="bi bi-database-fill" aria-hidden="true"></i>', 'Banco de dados'];
}

$nomesPerfis = [
    'dono'          => 'Proprietário <i class="bi bi-award-fill" aria-hidden="true"></i>',
    'recepcionista' => 'Recepcionista',
    'dentista'      => 'Dentista',
    'cliente'       => 'Paciente',
];

$estiloAvatarDono = $perfilAtual === 'dono' ? 'style="background:var(--destaque2);color:#0e2a3f;"' : '';
?>
<!-- barra superior: só aparece em telas pequenas, onde o menu vira gaveta -->
<div class="barra-movel">
  <button type="button" class="botao-menu" aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menu">
    <i class="bi bi-list" aria-hidden="true"></i>
  </button>
  <a href="index.php" class="menu-logo" aria-label="DENT IA — início">DENT<span>IA</span></a>
</div>
<div class="fundo-menu" hidden></div>
<aside class="menu-lateral" id="menu-lateral" role="navigation" aria-label="Menu principal">

  <a href="index.php" class="menu-logo" aria-label="DENT IA — início">
    DENT<span>IA</span>
  </a>

  <?php foreach (array_filter($menusPorPerfil[$perfilAtual] ?? []) as $nomeSecao => $itensSecao): ?>
  <p class="menu-secao" aria-hidden="true"><?= $nomeSecao ?></p>
  <ul class="menu-nav" role="list">
    <?php foreach ($itensSecao as [$arquivo, $icone, $rotulo]): ?>
    <li role="listitem">
      <a href="<?= $arquivo ?>"
         class="<?= $paginaAtual === $arquivo ? 'ativo' : '' ?>"
         <?= $paginaAtual === $arquivo ? 'aria-current="page"' : '' ?>>
        <span class="icone-nav" aria-hidden="true"><?= $icone ?></span>
        <?= $rotulo ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endforeach; ?>

  <form method="POST" action="logout.php" class="menu-sair">
    <?= csrfCampo(false) ?>
    <button type="submit"><span class="icone-nav" aria-hidden="true"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></span> Sair</button>
  </form>

  <div class="menu-espacador" aria-hidden="true"></div>

  <!-- Usuário logado — clicável → Meu Perfil -->
  <a href="<?= $linkPerfil ?>"
     class="menu-usuario-link <?= $paginaAtual === basename($linkPerfil) ? 'ativo' : '' ?>"
     aria-label="Ir para meu perfil — <?= limpar($nomeUsuario) ?>">
    <div class="avatar-usuario avatar avatar-medio"
         <?= $estiloAvatarDono ?>
         aria-hidden="true">
      <?= $iniciaisUsuario ?>
    </div>
    <div class="info-usuario">
      <strong><?= limpar($nomeUsuario) ?></strong>
      <span><?= $nomesPerfis[$perfilAtual] ?? $perfilAtual ?></span>
    </div>
    <span class="perfil-seta" aria-hidden="true">›</span>
  </a>

</aside>
<?php $flash = exibirFlash(); if ($flash !== ''): ?>
<div class="area-flash"><?= $flash ?></div>
<?php endif; ?>

