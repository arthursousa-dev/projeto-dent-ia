<?php
// includes/sidebar.php — menu lateral reutilizável para todas as páginas

$perfilAtual     = $_SESSION['perfil']  ?? '';
$nomeUsuario     = $_SESSION['usuario'] ?? '';
$iniciaisUsuario = iniciais($nomeUsuario);
$paginaAtual     = basename($_SERVER['PHP_SELF']);

// link da página de perfil de cada role
$linkPerfil = [
    'dono'          => 'dono_perfil.php',
    'recepcionista' => 'recepcionista_perfil.php',
    'dentista'      => 'dentista_perfil.php',
    'cliente'       => 'cliente_perfil.php',
][$perfilAtual] ?? '#';

$menusPorPerfil = [
    'dono' => [
        'Gestão' => [
            ['dono_dashboard.php',     '<i class="bi bi-bar-chart-fill" aria-hidden="true"></i>', 'Painel Geral'],
            ['dono_faturamento.php',   '<i class="bi bi-cash-coin" aria-hidden="true"></i>', 'Faturamento'],
            ['dono_dentistas.php',     '<i class="bi bi-emoji-smile-fill" aria-hidden="true"></i>', 'Dentistas'],
            ['dono_pacientes.php',     '<i class="bi bi-people-fill" aria-hidden="true"></i>', 'Pacientes'],
            ['dono_relatorios.php',    '<i class="bi bi-graph-up-arrow" aria-hidden="true"></i>', 'Relatórios'],
        ],
        'Sistema' => [
            ['dono_perfil.php',        '<i class="bi bi-person-fill" aria-hidden="true"></i>', 'Meu Perfil'],
            ['dono_configuracoes.php', '<i class="bi bi-gear-fill" aria-hidden="true"></i>', 'Configurações'],
            ['logout.php',             '<i class="bi bi-box-arrow-right" aria-hidden="true"></i>', 'Sair'],
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
            ['logout.php',               '<i class="bi bi-box-arrow-right" aria-hidden="true"></i>', 'Sair'],
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
            ['logout.php',          '<i class="bi bi-box-arrow-right" aria-hidden="true"></i>', 'Sair'],
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
            ['logout.php', '<i class="bi bi-box-arrow-right" aria-hidden="true"></i>', 'Sair'],
        ],
    ],
];

$nomesPerfis = [
    'dono'          => 'Proprietário <i class="bi bi-award-fill" aria-hidden="true"></i>',
    'recepcionista' => 'Recepcionista',
    'dentista'      => 'Dentista <i class="bi bi-emoji-smile-fill" aria-hidden="true"></i>',
    'cliente'       => 'Paciente',
];

$estiloAvatarDono = $perfilAtual === 'dono'
    ? 'style="background:linear-gradient(135deg,#f5a623,#e08c00);color:#0b2845;"'
    : '';
?>
<aside class="menu-lateral" id="menu-lateral" role="navigation" aria-label="Menu principal">

  <a href="index.php" class="menu-logo" aria-label="DENT IA — início">
    DENT<span>IA</span>
  </a>

  <?php foreach (($menusPorPerfil[$perfilAtual] ?? []) as $nomeSecao => $itensSecao): ?>
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

<style>
.menu-usuario-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  text-decoration: none;
  border-top: 1px solid rgba(255,255,255,0.06);
  margin: 0 -20px;
  transition: background var(--transicao, 0.2s);
  cursor: pointer;
}
.menu-usuario-link:hover,
.menu-usuario-link.ativo {
  background: rgba(0,201,167,0.12);
}
.menu-usuario-link .info-usuario strong {
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  display: block;
}
.menu-usuario-link .info-usuario span {
  color: rgba(255,255,255,0.5);
  font-size: 11px;
}
.perfil-seta {
  margin-left: auto;
  color: rgba(255,255,255,0.3);
  font-size: 18px;
  line-height: 1;
}
.menu-usuario-link:hover .perfil-seta { color: var(--destaque, #00c9a7); }
</style>
