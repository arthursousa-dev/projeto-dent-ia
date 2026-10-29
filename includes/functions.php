<?php
// Funções auxiliares — formatação, autenticação e carregamento dos dados
// (agora vindos do PostgreSQL via includes/db.php, que expõe a mesma
// interface lerJson()/salvarJson() usada historicamente pelas páginas).

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/operacoes.php';
require_once __DIR__ . '/monitor_banco.php';
date_default_timezone_set('America/Cuiaba');

/** Login, painel e perfil de cada tipo de acesso: fonte única para redirecionamentos e menus. */
const PERFIS = [
    'dono'          => ['login' => 'login_dono.php',          'painel' => 'dono_dashboard.php',          'perfil' => 'dono_perfil.php'],
    'dentista'      => ['login' => 'login_dentista.php',      'painel' => 'dentista_dashboard.php',      'perfil' => 'dentista_perfil.php'],
    'recepcionista' => ['login' => 'login_recepcionista.php', 'painel' => 'recepcionista_dashboard.php', 'perfil' => 'recepcionista_perfil.php'],
    'cliente'       => ['login' => 'login_paciente.php',      'painel' => 'cliente_dashboard.php',       'perfil' => 'cliente_perfil.php'],
];

// Todo POST passa por aqui: token CSRF válido e formulário ainda não processado.
// Em vez de uma página crua, a pessoa volta para a mesma tela com um aviso.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        if (empty($_SESSION['sessao_expirada'])) {
            definirFlash('aviso', 'O formulário estava desatualizado (a página ficou aberta muito tempo ou a sessão expirou), então nada foi salvo. Preencha e envie de novo.');
        }
        redirecionar(paginaAtual());
    }
    // Todo formulário do sistema manda "envio" (csrfCampo). Já usado, expirado ou ausente: nada é executado.
    if (!consumirEnvio((string)($_POST['envio'] ?? ''))) {
        definirFlash('aviso', 'Este formulário já foi usado ou ficou desatualizado, então nada foi feito agora. Confira a tela e, se precisar, faça de novo.');
        redirecionar(paginaAtual());
    }
}

function data_pt(string $formato, ?int $timestamp = null): string {
    if ($timestamp === null) $timestamp = time();
    static $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                     'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    static $mesesCurtos = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    static $diasSemana = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira',
                          'Quinta-feira','Sexta-feira','Sábado'];
    $mes = (int)date('n', $timestamp) - 1;
    $resultado = date($formato, $timestamp);
    $resultado = str_replace(date('F', $timestamp), $meses[$mes], $resultado);
    $resultado = str_replace(date('l', $timestamp), $diasSemana[(int)date('w', $timestamp)], $resultado);
    if (str_contains($formato, 'M')) $resultado = str_replace(date('M', $timestamp), $mesesCurtos[$mes], $resultado);
    return $resultado;
}

function limpar(?string $texto): string {
    if ($texto === null) return '';
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function iniciais(string $nome): string {
    $partes = preg_split('/\s+/u', trim($nome)) ?: [''];
    $r = maiuscula(cortarTexto($partes[0], 0, 1));
    if (count($partes) > 1) $r .= maiuscula(cortarTexto(end($partes), 0, 1));
    return $r;
}

function emblema(string $status): string {
    static $mapeamento = [
        'Confirmado'     => 'emblema-sucesso',
        'Em atendimento' => 'emblema-info',
        'Aguardando'     => 'emblema-aviso',
        'Agendado'       => 'emblema-cinza',
        'Cancelado'      => 'emblema-perigo',
        'Faltou'         => 'emblema-aviso',
        'Concluída'      => 'emblema-sucesso',
        'Ativo'          => 'emblema-sucesso',
        'ativo'          => 'emblema-sucesso',
        'Inativo'        => 'emblema-cinza',
        'inativo'        => 'emblema-cinza',
        'Disponível'     => 'emblema-sucesso',
        'Lotado'         => 'emblema-aviso',
        'Receita'        => 'emblema-sucesso',
        'Despesa'        => 'emblema-perigo',
    ];
    $classe = $mapeamento[$status] ?? 'emblema-cinza';
    return '<span class="emblema ' . $classe . '">' . limpar($status) . '</span>';
}

/** Identifica a senha atual sem guardar o hash na sessão: trocar a senha derruba as outras sessões. */
function versaoSenha(string $senhaHash): string {
    return substr(hash('sha256', $senhaHash), 0, 16);
}

// Hash de uma senha que ninguém conhece: e-mail inexistente custa o mesmo tempo que um existente.
const HASH_FICTICIO = '$2y$10$3ulI/ceOKg81dj2CVJbRueYb0tMItcIpi1cljnprVFbX3fQIQqQuS';

/**
 * Autentica um usuário no PostgreSQL.
 * Retorna array com nome/perfil/redirecionar ou false se inválido.
 */
function autenticarUsuario(string $email, string $senha, string $perfilEsperado): array|false {
    $email = strtolower(trim($email));
    $pdo   = \App\Config\Database::getConnection();

    $stmt = $pdo->prepare(
        'SELECT u.id, p.nome, u.tipo, u.status, u.senha_hash, u.tentativas_login,
                u.bloqueado_ate IS NOT NULL AS teve_bloqueio,
                COALESCE(u.bloqueado_ate > now(), false) AS bloqueado
         FROM usuarios u JOIN pessoa p ON p.id = u.id_pessoa
         WHERE u.email = :email AND u.tipo = :tipo'
    );
    $stmt->execute([':email' => $email, ':tipo' => $perfilEsperado]);
    $u = $stmt->fetch();

    if (!$u) {
        password_verify($senha, HASH_FICTICIO); // não revela, nem pelo tempo, se o e-mail existe
        return false;
    }

    // Conta bloqueada por excesso de tentativas incorretas.
    if (booleanoPg($u['bloqueado'])) {
        return false;
    }

    if (($u['status'] ?? 'ativo') !== 'ativo' || !password_verify($senha, $u['senha_hash'])) {
        // Cada tentativa errada soma 1; na 5ª, bloqueia por 15 minutos.
        // Depois que um bloqueio vence, a contagem recomeça do zero.
        $tentativas = (booleanoPg($u['teve_bloqueio']) ? 0 : (int) $u['tentativas_login']) + 1;
        $bloqueio = $tentativas >= 5
            ? "now() + interval '15 minutes'"
            : 'NULL';
        $upd = $pdo->prepare(
            "UPDATE usuarios SET tentativas_login = :tentativas, bloqueado_ate = {$bloqueio} WHERE id = :id"
        );
        $upd->execute([':tentativas' => $tentativas, ':id' => $u['id']]);
        return false;
    }

    // Login certo: zera o contador de tentativas.
    $pdo->prepare('UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL WHERE id = :id')
        ->execute([':id' => $u['id']]);

    return [
        'nome'         => $u['nome'],
        'perfil'       => $u['tipo'],
        'id'           => $u['id'],
        'versao_senha' => versaoSenha($u['senha_hash']),
        'redirecionar' => PERFIS[$u['tipo']]['painel'] ?? 'index.php',
    ];
}

/** Abre a sessão de um usuário autenticado (novo ID de sessão contra fixação). */
function iniciarSessaoUsuario(array $usuario, string $email): void {
    session_regenerate_id(true);
    $_SESSION['usuario']      = $usuario['nome'];
    $_SESSION['perfil']       = $usuario['perfil'];
    $_SESSION['email']        = strtolower(trim($email));
    $_SESSION['versao_senha'] = $usuario['versao_senha'];
    unset($_SESSION['sessao_expirada']);
}

/**
 * Verifica sessão ativa e redireciona se não autorizado.
 * Também encerra a sessão se o acesso foi desativado ou a senha foi trocada em outro lugar.
 */
function verificarSessao(string $perfilNecessario): void {
    $login = PERFIS[$perfilNecessario]['login'] ?? 'index.php';
    if (!isset($_SESSION['usuario']) || $_SESSION['perfil'] !== $perfilNecessario) {
        $expirou = !empty($_SESSION['sessao_expirada']) ? '?expirou=1' : '';
        unset($_SESSION['sessao_expirada']);
        header('Location: ' . $login . $expirou);
        exit;
    }
    $stmt = \App\Config\Database::getConnection()->prepare(
        "SELECT senha_hash FROM usuarios WHERE email = :email AND tipo = :tipo AND status = 'ativo'");
    $stmt->execute(['email' => $_SESSION['email'] ?? '', 'tipo' => $perfilNecessario]);
    $hash = $stmt->fetchColumn();
    if ($hash === false || !hash_equals((string)($_SESSION['versao_senha'] ?? ''), versaoSenha($hash))) {
        $_SESSION = [];
        session_regenerate_id(true);
        definirFlash('aviso', 'Sua sessão foi encerrada porque os dados de acesso (senha ou e-mail) mudaram ou o acesso foi desativado. Entre novamente.');
        header('Location: ' . $login);
        exit;
    }
    marcarUsuarioNoBanco($perfilNecessario . ':' . ($_SESSION['email'] ?? ''));
}

/** Listas usadas pelas telas de agenda/pacientes; chamada só pelas páginas que precisam delas. */
function carregarListas(): void {
    global $listaDentistas, $listaPacientes, $listaProcedimentos, $listaHorarios, $listaAgendamentos;
    $pdo = \App\Config\Database::getConnection();
    $listaDentistas = array_map(function ($d) {
        $d['atendimentos'] = $d['atendimentos_mes']; return $d;
    }, lerJson('dentistas.json'));
    // Cliente e dentista recebem só os próprios registros, filtrados no SQL.
    $filtroAgenda = [];
    $filtroPacientes = [];
    if (in_array($_SESSION['perfil'] ?? '', ['dentista', 'cliente'], true)) {
        $u = usuarioSessao();
        if ($u['tipo'] === 'cliente') {
            $filtroPacientes = ['id_usuario' => $u['id']];
            $pac = lerJson('pacientes.json', $filtroPacientes)[0] ?? null;
            $filtroAgenda = ['id_paciente' => $pac['id'] ?? 0];
        } else {
            $dent = buscarPorCampo($listaDentistas, 'id_usuario', $u['id']);
            $filtroAgenda = ['id_dentista' => $dent['id'] ?? 0];
        }
    }
    $listaAgendamentos = array_map(function ($a) {
        $a['procedimento'] = $a['servico']; return $a;
    }, lerJson('agendamentos.json', $filtroAgenda));
    // Dentista vê só os pacientes da própria agenda (sem cadastro de dentista: nenhum, nunca todos).
    if (($_SESSION['perfil'] ?? '') === 'dentista') $filtroPacientes = ['id' => array_values(array_unique(array_column($listaAgendamentos, 'id_paciente')))];
    $listaPacientes = isset($pac) ? ($pac ? [$pac] : []) : lerJson('pacientes.json', $filtroPacientes);
    // "Última" e "próxima" consulta de cada paciente, com as mesmas regras de status em todas as telas.
    $visitas = $pdo->prepare("SELECT id_paciente,
        MAX(data) FILTER (WHERE status='Concluída') AS ultima,
        MIN(data) FILTER (WHERE " . SQL_CONSULTA_FUTURA . ") AS proxima
        FROM agendamentos WHERE id_paciente = ANY(CAST(:ids AS int[])) GROUP BY id_paciente");
    $visitas->execute(['ids' => '{' . implode(',', array_map('intval', array_column($listaPacientes, 'id'))) . '}']);
    $visitas = $visitas->fetchAll(PDO::FETCH_UNIQUE);
    foreach ($listaPacientes as &$p) {
        $p['ultimaConsulta'] = !empty($visitas[$p['id']]['ultima']) ? date('d/m/Y', strtotime($visitas[$p['id']]['ultima'])) : '—';
        $p['proximaConsulta'] = !empty($visitas[$p['id']]['proxima']) ? date('d/m/Y', strtotime($visitas[$p['id']]['proxima'])) : '—';
    }
    unset($p);
    $listaProcedimentos = array_column(lerJson('procedimentos.json'), 'nome');
    $listaHorarios = $pdo->query("SELECT DISTINCT to_char(hora,'HH24:MI') FROM disponibilidade_dentista ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
}
