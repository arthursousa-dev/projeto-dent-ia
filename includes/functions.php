<?php
// Funções auxiliares — formatação, autenticação e carregamento dos dados
// (agora vindos do PostgreSQL via includes/db.php, que expõe a mesma
// interface lerJson()/salvarJson() usada historicamente pelas páginas).

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/operacoes.php';
date_default_timezone_set('America/Cuiaba');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !csrfValido($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Sessão expirada. Recarregue a página e tente novamente.');
}

function data_pt(string $formato, ?int $timestamp = null): string {
    if ($timestamp === null) $timestamp = time();
    static $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                     'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    static $diasSemana = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira',
                          'Quinta-feira','Sexta-feira','Sábado'];
    $resultado = date($formato, $timestamp);
    $resultado = str_replace(date('F', $timestamp), $meses[(int)date('n', $timestamp) - 1], $resultado);
    $resultado = str_replace(date('l', $timestamp), $diasSemana[(int)date('w', $timestamp)], $resultado);
    return $resultado;
}

function limpar(?string $texto): string {
    if ($texto === null) return '';
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function iniciais(string $nome): string {
    $partes = explode(' ', trim($nome));
    $r = strtoupper(substr($partes[0], 0, 1));
    if (count($partes) > 1) $r .= strtoupper(substr(end($partes), 0, 1));
    return $r;
}

function emblema(string $status): string {
    static $mapeamento = [
        'Confirmado'     => 'emblema-sucesso',
        'Em atendimento' => 'emblema-info',
        'Aguardando'     => 'emblema-aviso',
        'Agendado'       => 'emblema-cinza',
        'Cancelado'      => 'emblema-perigo',
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

/**
 * Autentica um usuário consultando dados/usuarios.json.
 * Retorna array com nome/perfil/redirecionar ou false se inválido.
 */
function autenticarUsuario(string $email, string $senha, string $perfilEsperado): array|false {
    $email = strtolower(trim($email));
    $pdo   = \App\Config\Database::getConnection();

    $destinos = [
        'cliente'       => 'cliente_dashboard.php',
        'recepcionista' => 'recepcionista_dashboard.php',
        'dentista'      => 'dentista_dashboard.php',
        'dono'          => 'dono_dashboard.php',
    ];

    $stmt = $pdo->prepare(
        'SELECT u.id, p.nome, u.tipo, u.status, u.senha_hash, u.tentativas_login, u.bloqueado_ate
         FROM usuarios u JOIN pessoa p ON p.id = u.id_pessoa
         WHERE u.email = :email AND u.tipo = :tipo'
    );
    $stmt->execute([':email' => $email, ':tipo' => $perfilEsperado]);
    $u = $stmt->fetch();

    if (!$u) {
        return false; // não revela se o e-mail existe ou não
    }

    // Conta bloqueada por excesso de tentativas incorretas.
    if ($u['bloqueado_ate'] !== null && strtotime($u['bloqueado_ate']) > time()) {
        return false;
    }

    if (($u['status'] ?? 'ativo') !== 'ativo' || !password_verify($senha, $u['senha_hash'])) {
        // Cada tentativa errada soma 1; na 5ª, bloqueia por 15 minutos.
        $tentativas = (int) $u['tentativas_login'] + 1;
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
        'redirecionar' => $destinos[$u['tipo']] ?? 'index.php',
    ];
}

/**
 * Verifica sessão ativa e redireciona se não autorizado.
 */
function verificarSessao(string $perfilNecessario): void {
    if (!isset($_SESSION['usuario']) || $_SESSION['perfil'] !== $perfilNecessario) {
        $logins = [
            'cliente'       => 'login_paciente.php',
            'recepcionista' => 'login_recepcionista.php',
            'dentista'      => 'login_dentista.php',
            'dono'          => 'login_dono.php',
        ];
        header('Location: ' . ($logins[$perfilNecessario] ?? 'index.php'));
        exit;
    }
}

$listaDentistas = array_map(function($d) {
    $d['atendimentos']=$d['atendimentos_mes']; return $d;
},lerJson('dentistas.json'));
$listaPacientes = lerJson('pacientes.json');
$listaAgendamentos = array_map(function($a) {
    $a['procedimento']=$a['servico']; return $a;
},lerJson('agendamentos.json'));
if (in_array($_SESSION['perfil'] ?? '',['dentista','cliente'],true)) {
    $u=usuarioSessao();
    if ($u['tipo']==='cliente') {
        $pac=buscarPorCampo($listaPacientes,'id_usuario',$u['id']);
        $listaAgendamentos=array_values(array_filter($listaAgendamentos,fn($a)=>$a['id_paciente']===($pac['id'] ?? 0)));
        $listaPacientes=$pac ? [$pac] : [];
    } else {
        $dent=buscarPorCampo($listaDentistas,'id_usuario',$u['id']);
        $listaAgendamentos=array_values(array_filter($listaAgendamentos,fn($a)=>$a['id_dentista']===($dent['id'] ?? 0)));
        $ids=array_column($listaAgendamentos,'id_paciente');
        $listaPacientes=array_values(array_filter($listaPacientes,fn($p)=>in_array($p['id'],$ids,true)));
    }
}
$visitas=\App\Config\Database::getConnection()->query("SELECT id_paciente,
    MAX(data) FILTER (WHERE status='Concluída') AS ultima,
    MIN(data) FILTER (WHERE data>=CURRENT_DATE AND status IN ('Agendado','Confirmado','Aguardando')) AS proxima
    FROM agendamentos GROUP BY id_paciente")->fetchAll(PDO::FETCH_UNIQUE);
foreach ($listaPacientes as &$p) {
    $p['ultimaConsulta']=!empty($visitas[$p['id']]['ultima']) ? date('d/m/Y',strtotime($visitas[$p['id']]['ultima'])) : '—';
    $p['proximaConsulta']=!empty($visitas[$p['id']]['proxima']) ? date('d/m/Y',strtotime($visitas[$p['id']]['proxima'])) : '—';
}
unset($p);
$listaProcedimentos=array_column(lerJson('procedimentos.json'),'nome');
$listaHorarios=\App\Config\Database::getConnection()->query("SELECT DISTINCT to_char(hora,'HH24:MI') FROM disponibilidade_dentista ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
