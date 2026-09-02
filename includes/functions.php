<?php
// Funções auxiliares — formatação, autenticação e carregamento dos dados
// (arquivos JSON em /dados/*.json).

require_once __DIR__ . '/db.php';

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
    $usuarios = lerJson('usuarios.json');
    $email    = strtolower(trim($email));

    $destinos = [
        'cliente'       => 'cliente_dashboard.php',
        'recepcionista' => 'recepcionista_dashboard.php',
        'dentista'      => 'dentista_dashboard.php',
        'dono'          => 'dono_dashboard.php',
    ];

    foreach ($usuarios as $u) {
        if (
            strtolower($u['email']) === $email
            && password_verify($senha, $u['senha'])
            && $u['tipo']          === $perfilEsperado
            && ($u['status'] ?? 'ativo') === 'ativo'
        ) {
            return [
                'nome'         => $u['nome'],
                'perfil'       => $u['tipo'],
                'id'           => $u['id'],
                'redirecionar' => $destinos[$u['tipo']] ?? 'index.php',
            ];
        }
    }
    return false;
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

// Lê cada arquivo JSON e normaliza chaves para compatibilidade com os templates.

$_raw_dentistas  = lerJson('dentistas.json');
$_raw_pacientes  = lerJson('pacientes.json');
$_raw_agend      = lerJson('agendamentos.json');

// Normaliza dentistas: garante chave 'atendimentos' (pode vir como 'atendimentos_mes')
$listaDentistas = array_map(function($d) {
    $d['atendimentos']   = $d['atendimentos_mes'] ?? $d['atendimentos'] ?? 0;
    return $d;
}, $_raw_dentistas);

// Usa fallback se arquivo estiver vazio
if (empty($listaDentistas)) {
    $listaDentistas = [
        ['id'=>1,'nome'=>'Dr. Carlos Mendes',   'especialidade'=>'Clínico Geral', 'cro'=>'CRO-MS 12345','atendimentos'=>52,'status'=>'Ativo','email'=>'carlos@dentai.com',  'telefone'=>'(67) 99111-0001','admissao'=>'2020-03-01'],
        ['id'=>2,'nome'=>'Dra. Ana Silva',       'especialidade'=>'Ortodontia',    'cro'=>'CRO-MS 23456','atendimentos'=>40,'status'=>'Ativo','email'=>'ana@dentai.com',      'telefone'=>'(67) 99111-0002','admissao'=>'2021-06-15'],
        ['id'=>3,'nome'=>'Dr. Paulo Ramos',      'especialidade'=>'Cirurgia Oral', 'cro'=>'CRO-MS 34567','atendimentos'=>16,'status'=>'Ativo','email'=>'paulo@dentai.com',    'telefone'=>'(67) 99111-0003','admissao'=>'2022-01-10'],
        ['id'=>4,'nome'=>'Dra. Luciana Torres',  'especialidade'=>'Endodontia',    'cro'=>'CRO-MS 45678','atendimentos'=>32,'status'=>'Ativo','email'=>'luciana@dentai.com',  'telefone'=>'(67) 99111-0004','admissao'=>'2019-09-20'],
    ];
}

// Normaliza pacientes: garante chave 'ultimaConsulta' (pode vir como 'ultima_consulta')
$listaPacientes = array_map(function($p) {
    $raw = $p['ultima_consulta'] ?? $p['ultimaConsulta'] ?? '—';
    // Converte de YYYY-MM-DD para DD/MM/YYYY se necessário
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $raw = date('d/m/Y', strtotime($raw));
    }
    $p['ultimaConsulta'] = $raw;
    return $p;
}, $_raw_pacientes);

if (empty($listaPacientes)) {
    $listaPacientes = [
        ['id'=>1,'nome'=>'Maria Oliveira', 'cpf'=>'111.222.333-44','telefone'=>'(67) 99211-1111','email'=>'maria@email.com',    'nascimento'=>'1985-04-12','ultimaConsulta'=>'10/02/2026','status'=>'Ativo'],
        ['id'=>2,'nome'=>'João Santos',    'cpf'=>'222.333.444-55','telefone'=>'(67) 99211-2222','email'=>'joao@email.com',     'nascimento'=>'1990-07-23','ultimaConsulta'=>'08/02/2026','status'=>'Ativo'],
        ['id'=>3,'nome'=>'Carla Pereira',  'cpf'=>'333.444.555-66','telefone'=>'(67) 99211-3333','email'=>'carla@email.com',    'nascimento'=>'1978-11-05','ultimaConsulta'=>'05/02/2026','status'=>'Ativo'],
        ['id'=>4,'nome'=>'Pedro Alves',    'cpf'=>'444.555.666-77','telefone'=>'(67) 99211-4444','email'=>'pedro@email.com',    'nascimento'=>'1995-01-30','ultimaConsulta'=>'01/02/2026','status'=>'Ativo'],
        ['id'=>5,'nome'=>'Fernanda Costa', 'cpf'=>'555.666.777-88','telefone'=>'(67) 99211-5555','email'=>'fernanda@email.com', 'nascimento'=>'1988-09-17','ultimaConsulta'=>'28/01/2026','status'=>'Inativo'],
        ['id'=>6,'nome'=>'Ricardo Souza',  'cpf'=>'666.777.888-99','telefone'=>'(67) 99211-6666','email'=>'ricardo@email.com',  'nascimento'=>'2000-03-08','ultimaConsulta'=>'20/01/2026','status'=>'Ativo'],
    ];
}

// Normaliza: o JSON usa "servico", os templates esperam "procedimento"
if (!empty($_raw_agend)) {
    $_raw_agend = array_map(function($a) {
        $a['procedimento'] = $a['servico'] ?? $a['procedimento'] ?? '—';
        return $a;
    }, $_raw_agend);
}

$listaAgendamentos = !empty($_raw_agend) ? $_raw_agend : [
    ['id'=>1,'paciente'=>'Maria Oliveira', 'procedimento'=>'Limpeza Dental',  'dentista'=>'Dr. Carlos Mendes',  'data'=>'2026-04-10','hora'=>'08:00','sala'=>'Sala 1','status'=>'Confirmado'],
    ['id'=>2,'paciente'=>'João Santos',    'procedimento'=>'Clareamento',     'dentista'=>'Dra. Ana Silva',     'data'=>'2026-04-10','hora'=>'09:00','sala'=>'Sala 2','status'=>'Em atendimento'],
    ['id'=>3,'paciente'=>'Carla Pereira',  'procedimento'=>'Ortodontia',      'dentista'=>'Dra. Ana Silva',     'data'=>'2026-04-10','hora'=>'10:00','sala'=>'Sala 2','status'=>'Aguardando'],
    ['id'=>4,'paciente'=>'Pedro Alves',    'procedimento'=>'Extração',        'dentista'=>'Dr. Paulo Ramos',    'data'=>'2026-04-10','hora'=>'11:00','sala'=>'Sala 3','status'=>'Aguardando'],
    ['id'=>5,'paciente'=>'Fernanda Costa', 'procedimento'=>'Consulta Rotina', 'dentista'=>'Dr. Carlos Mendes',  'data'=>'2026-04-10','hora'=>'14:00','sala'=>'Sala 1','status'=>'Agendado'],
    ['id'=>6,'paciente'=>'Ricardo Souza',  'procedimento'=>'Canal Radicular', 'dentista'=>'Dra. Luciana Torres','data'=>'2026-04-11','hora'=>'09:00','sala'=>'Sala 4','status'=>'Agendado'],
    ['id'=>7,'paciente'=>'Ana Ferreira',   'procedimento'=>'Limpeza Dental',  'dentista'=>'Dr. Carlos Mendes',  'data'=>'2026-04-11','hora'=>'10:00','sala'=>'Sala 1','status'=>'Agendado'],
];

$listaProcedimentos = [];
$_raw_procs = lerJson('procedimentos.json');
if (!empty($_raw_procs)) {
    $listaProcedimentos = array_column($_raw_procs, 'nome');
}
if (empty($listaProcedimentos)) {
    $listaProcedimentos = [
        'Limpeza Dental','Clareamento Dental','Ortodontia','Extração','Restauração',
        'Canal Radicular','Implante','Consulta de Rotina','Periodontia','Prótese',
        'Selante','Placa Miorrelaxante',
    ];
}

$listaHorarios = [
    '07:30','08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30',
    '13:00','13:30','14:00','14:30','15:00','15:30','16:00','16:30','17:00','17:30',
];
