<?php
// Regras de negócio. Toda escrita no banco passa por aqui, com perfil conferido no servidor.

/** Consulta ainda sem desfecho. Depois que o dia passa, aparece como "sem desfecho" para a recepção. */
const STATUS_EM_ABERTO = ['Agendado', 'Confirmado', 'Aguardando', 'Em atendimento'];
/** Consulta que o paciente ainda vai ter (próxima consulta, consultas futuras). */
const STATUS_FUTURO = ['Agendado', 'Confirmado', 'Aguardando'];
/** Não gera cobrança nem conta como atendimento. */
const STATUS_SEM_COBRANCA = ['Cancelado', 'Faltou'];
/** Valores aceitos pelo tipo status_dente do banco. */
const STATUS_DENTE = ['saudavel', 'tratado', 'precisaTratamento', 'ausente'];
const SENHA_MINIMA = 6;
/** Limite do bcrypt: acima de 72 bytes o resto da senha é ignorado. */
const SENHA_MAXIMA_BYTES = 72;
const REGEX_TELEFONE = '/^(1[1-9]|2[12478]|3[1-578]|4[1-9]|5[1345]|6[1-9]|7[134579]|8[1-9]|9[1-9])([2-5][0-9]{7}|9[0-9]{8})$/';

// Texto UTF-8 sem depender da extensão mbstring (desligada no PHP local; o iconv vem embutido).
function tamanhoTexto(string $texto): int { return (int)iconv_strlen($texto, 'UTF-8'); }
function cortarTexto(string $texto, int $inicio, int $tamanho): string { return (string)iconv_substr($texto, $inicio, $tamanho, 'UTF-8'); }
function maiuscula(string $texto): string {
    return strtr(strtoupper($texto), ['á'=>'Á','à'=>'À','â'=>'Â','ã'=>'Ã','é'=>'É','ê'=>'Ê','í'=>'Í','ó'=>'Ó','ô'=>'Ô','õ'=>'Õ','ú'=>'Ú','ü'=>'Ü','ç'=>'Ç']);
}

/** Lista SQL a partir das constantes acima (valores fixos do código, nunca da entrada do usuário). */
function listaSql(array $valores): string {
    return implode(',', array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $valores));
}

/** Identifica, na conexão com o banco, quem está agindo. O registro de alterações (auditoria_banco) grava esse nome. */
function marcarUsuarioNoBanco(string $quem): void {
    \App\Config\Database::getConnection()->prepare("SELECT set_config('dentia.usuario', :quem, false)")
        ->execute(['quem' => cortarTexto($quem, 0, 200)]);
}

function usuarioSessao(): array {
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare('SELECT u.id,u.tipo,u.id_pessoa,p.nome FROM usuarios u JOIN pessoa p ON p.id=u.id_pessoa
        WHERE u.email=:email AND u.tipo=:tipo AND u.status=\'ativo\'');
    $stmt->execute(['email'=>$_SESSION['email'] ?? '', 'tipo'=>$_SESSION['perfil'] ?? 'cliente']);
    return $stmt->fetch() ?: throw new RuntimeException('Sessão inválida. Faça login novamente.');
}

function exigirPerfil(array $perfis): array {
    $u=usuarioSessao();
    if (!in_array($u['tipo'],$perfis,true)) throw new RuntimeException('Acesso não permitido.');
    return $u;
}

function transacaoOperacao(callable $operacao): mixed {
    $pdo=\App\Config\Database::getConnection();
    $aninhada=$pdo->inTransaction();
    // Uma ação pode fazer outras operações transacionais (ex.: teste com rollback).
    $savepoint='sp_dentia_'.bin2hex(random_bytes(5));
    if ($aninhada) $pdo->exec('SAVEPOINT '.$savepoint);
    else $pdo->beginTransaction();
    try {
        $resultado=$operacao($pdo);
        if ($aninhada) $pdo->exec('RELEASE SAVEPOINT '.$savepoint);
        else $pdo->commit();
        return $resultado;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            if ($aninhada) $pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
            else $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Texto que pode ir para a tela. Só as mensagens das regras (InvalidArgument/Runtime) aparecem como vieram;
 * erro do banco vira uma frase sem SQL, e qualquer outro erro vira uma frase genérica com código para o suporte.
 */
function mensagemOperacao(Throwable $e): string {
    $codigo = bin2hex(random_bytes(4));
    if ($e instanceof PDOException) {
        error_log('[DENTIA]['.$codigo.'] SQLSTATE='.$e->getCode().' '.$e->getMessage());
        if (getenv('APP_DEBUG') === 'true') return 'Erro SQLSTATE '.$e->getCode().' ('.$codigo.'): '.$e->getMessage();
        return match ((string)$e->getCode()) {
            '23505'=>'Já existe um registro com esses dados (CPF, e-mail, CRO ou horário). Confira e tente de novo.',
            '23503'=>'Um dos itens escolhidos não existe mais ou ainda está em uso. Recarregue a página e tente de novo.',
            '23514','23502','22001','22003','22007','22008','22P02','22021'
                =>'Algum campo tem valor fora do permitido (data, número, tamanho do texto ou CPF). Confira e tente de novo.',
            '40001','40P01','55P03'=>'Outra pessoa alterou este registro agora. Recarregue a página e tente de novo.',
            default=>'Não foi possível concluir a operação. Se continuar, informe ao suporte o código '.$codigo.'.',
        };
    }
    if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) return $e->getMessage();
    error_log('[DENTIA]['.$codigo.'] '.get_class($e).': '.$e->getMessage().' em '.$e->getFile().':'.$e->getLine());
    return 'Não foi possível concluir a operação. Se continuar, informe ao suporte o código '.$codigo.'.';
}

// ---------- validação de entrada ----------

/** Data no formato AAAA-MM-DD, existente no calendário e dentro de um intervalo. $campo: "data de nascimento". */
function dataValida(?string $valor, string $campo, string $minimo, string $maximo, bool $obrigatoria=true): ?string {
    $valor=trim((string)$valor);
    if ($valor==='') {
        if ($obrigatoria) throw new InvalidArgumentException('Informe a '.$campo.'.');
        return null;
    }
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$valor);
    if (!$dt || $dt->format('Y-m-d')!==$valor) throw new InvalidArgumentException(ucfirst($campo).' inválida: escolha uma data que exista no calendário.');
    if ($valor<$minimo || $valor>$maximo) {
        throw new InvalidArgumentException(ucfirst($campo).' deve ficar entre '.date('d/m/Y',strtotime($minimo)).' e '.date('d/m/Y',strtotime($maximo)).'.');
    }
    return $valor;
}

/** CPF com os dois dígitos verificadores corretos. */
function cpfValido(?string $cpf): bool {
    $c=soDigitos($cpf);
    if (strlen($c)!==11 || preg_match('/^(\d)\1{10}$/',$c)) return false;
    for ($t=9; $t<11; $t++) {
        $soma=0;
        for ($i=0; $i<$t; $i++) $soma+=(int)$c[$i]*($t+1-$i);
        if ((int)$c[$t]!==((10*$soma)%11)%10) return false;
    }
    return true;
}

function telefoneValido(?string $telefone): bool {
    return (bool)preg_match(REGEX_TELEFONE, soDigitos($telefone));
}

/** Texto obrigatório (ou não) com tamanho máximo em caracteres. */
function textoValido(?string $valor, string $campo, int $maximo, bool $obrigatorio=true): string {
    $valor=trim((string)$valor);
    if ($valor==='' && $obrigatorio) throw new InvalidArgumentException('Informe '.$campo.'.');
    if (tamanhoTexto($valor)>$maximo) throw new InvalidArgumentException(ucfirst($campo).' pode ter no máximo '.$maximo.' caracteres.');
    return $valor;
}

function validarNovaSenha(string $senha, ?string $confirmacao=null): void {
    if (tamanhoTexto($senha)<SENHA_MINIMA) throw new InvalidArgumentException('A senha deve ter pelo menos '.SENHA_MINIMA.' caracteres.');
    if (strlen($senha)>SENHA_MAXIMA_BYTES) throw new InvalidArgumentException('A senha está longa demais: use até 72 caracteres (letras acentuadas e emojis contam como mais de um).');
    if ($confirmacao!==null && $senha!==$confirmacao) throw new InvalidArgumentException('As senhas não coincidem.');
}

/** Numeração FDI: permanentes 11–48 e decíduos 51–85. */
function denteValido(int $numero): bool {
    $quadrante=intdiv($numero,10); $posicao=$numero%10;
    return ($quadrante>=1 && $quadrante<=4 && $posicao>=1 && $posicao<=8)
        || ($quadrante>=5 && $quadrante<=8 && $posicao>=1 && $posicao<=5);
}

function hoje(): string { return date('Y-m-d'); }

/** Id vindo da URL/formulário: só números que cabem no INT do banco; qualquer outra coisa vira 0 (não encontrado). */
function idParametro(mixed $valor): int {
    if (!is_scalar($valor)) return 0;
    $texto = trim((string)$valor);
    return ctype_digit($texto) && strlen($texto) <= 10 && (int)$texto <= 2147483647 ? (int)$texto : 0;
}

/** Texto para busca: minúsculo e sem acento ("JOÃO" e "Joao" encontram "João"). */
function semAcento(string $texto): string {
    return strtolower(strtr($texto, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
        'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','Ä'=>'a','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e','Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
        'Ó'=>'o','Ò'=>'o','Ô'=>'o','Õ'=>'o','Ö'=>'o','Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','Ç'=>'c','Ñ'=>'n']));
}
/** Equivalente SQL de semAcento() para usar em buscas (ILIKE sobre translate). */
const SQL_SEM_ACENTO_DE = 'áàâãäéèêëíìîïóòôõöúùûüçñÁÀÂÃÄÉÈÊËÍÌÎÏÓÒÔÕÖÚÙÛÜÇÑ';
const SQL_SEM_ACENTO_PARA = 'aaaaaeeeeiiiiooooouuuucnaaaaaeeeeiiiiooooouuuucn';

/** CNPJ só com letras maiúsculas e números (tira pontos, barra e traço). */
function cnpjNormalizado(?string $cnpj): string {
    return (string)preg_replace('/[^0-9A-Z]/', '', strtoupper((string)$cnpj));
}

/** CNPJ numérico ou alfanumérico (IN RFB 2.229/2024) com os dois dígitos verificadores corretos. */
function cnpjValido(?string $cnpj): bool {
    $c = cnpjNormalizado($cnpj);
    if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $c) || preg_match('/^(.)\1{13}$/', $c)) return false;
    foreach ([12, 13] as $t) {
        $pesos = $t === 12 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2];
        $soma = 0;
        foreach ($pesos as $i => $p) $soma += (ord($c[$i]) - 48) * $p;
        $dv = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        if ((int)$c[$t] !== $dv) return false;
    }
    return true;
}
function emDias(int $dias): string { return date('Y-m-d', strtotime(($dias>=0?'+':'').$dias.' days')); }

// ---------- limite de tentativas por origem (login e cadastro público) ----------

/**
 * IP de quem fez a requisição. Atrás de um proxy (Railway, nginx), REMOTE_ADDR é o proxy: todos os clientes
 * cairiam na mesma chave. Só nesse caso, declarado no .env com APP_PROXY_CONFIAVEL=true, usa o
 * X-Forwarded-For; sem a variável, o cabeçalho é ignorado (qualquer um poderia forjá-lo).
 * Usa o ÚLTIMO endereço da lista: é o que o proxy confiável acrescentou; os anteriores vêm do cliente e podem ser inventados.
 */
function ipOrigem(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    if (getenv('APP_PROXY_CONFIAVEL') === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $lista = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
        $cliente = (string)end($lista);
        if (filter_var($cliente, FILTER_VALIDATE_IP)) $ip = $cliente;
    }
    return $ip;
}

function chaveOrigem(string $acao): string {
    return $acao.':'.ipOrigem();
}

/** Verdadeiro quando esta origem já fez $maximo tentativas de $acao nos últimos $minutos. */
function limiteAtingido(string $acao, int $maximo, int $minutos): bool {
    $pdo=\App\Config\Database::getConnection();
    if (random_int(1, 50) === 1) $pdo->exec("DELETE FROM tentativa_acesso WHERE momento < now() - interval '1 day'"); // limpeza ocasional
    $stmt=$pdo->prepare('SELECT count(*) FROM tentativa_acesso WHERE chave=:chave AND momento > now() - make_interval(mins=>:minutos)');
    $stmt->execute(['chave'=>chaveOrigem($acao),'minutos'=>$minutos]);
    return (int)$stmt->fetchColumn()>=$maximo;
}

function registrarTentativa(string $acao): void {
    \App\Config\Database::getConnection()->prepare('INSERT INTO tentativa_acesso (chave) VALUES (:chave)')
        ->execute(['chave'=>chaveOrigem($acao)]);
}

// ---------- pacientes e dentistas da sessão ----------

function pacienteSessao(): ?array {
    $u=usuarioSessao();
    return lerJson('pacientes.json',['id_usuario'=>$u['id']])[0] ?? null;
}

function dentistaSessao(): ?array {
    $u=usuarioSessao();
    return lerJson('dentistas.json',['id_usuario'=>$u['id']])[0] ?? null;
}

function localizarPaciente(string $termo): array {
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare("SELECT pac.id,pac.id_pessoa, p.nome, p.cpf FROM pacientes pac JOIN pessoa p ON p.id=pac.id_pessoa
        WHERE pac.id::text=:id OR p.cpf=:cpf LIMIT 1");
    $stmt->execute(['id'=>$termo,'cpf'=>soDigitos($termo)]);
    $p=$stmt->fetch();
    if (!$p) throw new InvalidArgumentException('Paciente não encontrado.');
    return $p;
}

function pacientePermitido(int $id): void {
    $u=exigirPerfil(['dentista','dono','recepcionista']);
    if ($u['tipo']!=='dentista') return;
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare("SELECT 1 FROM dentistas d WHERE d.id_pessoa=:pessoa AND (
        EXISTS(SELECT 1 FROM agendamentos a WHERE a.id_dentista=d.id AND a.id_paciente=:paciente) OR
        EXISTS(SELECT 1 FROM evolucao_clinica e JOIN prontuarios pr ON pr.id=e.id_prontuario WHERE e.id_dentista=d.id AND pr.id_paciente=:pac))");
    $stmt->execute(['pessoa'=>$u['id_pessoa'],'paciente'=>$id,'pac'=>$id]);
    // Mesma mensagem de "não encontrado": o dentista não descobre se o CPF é paciente da clínica.
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Paciente não encontrado.');
}

// ---------- agenda ----------

/**
 * Horários livres para um procedimento numa data: regra única usada pela API da tela de agendamento.
 * Considera expediente, duração, dentista, sala, paciente e, hoje, só horários que ainda não passaram.
 */
function horariosLivres(string $data, string $procedimento, int $dentista=0, int $paciente=0): array {
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare('SELECT 1 FROM procedimentos WHERE nome=:nome');
    $stmt->execute(['nome'=>$procedimento]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Procedimento não cadastrado.');
    $stmt=$pdo->prepare("SELECT (SELECT count(*) FROM agendamentos WHERE data=CAST(:data AS date) AND status<>'Cancelado')
        >= (SELECT limite_agendamentos_dia FROM clinica WHERE id=1)");
    $stmt->execute(['data'=>$data]);
    if ($stmt->fetchColumn()) throw new InvalidArgumentException('A agenda deste dia atingiu o limite de consultas. Escolha outra data.');
    $stmt=$pdo->prepare("SELECT DISTINCT to_char(dd.hora,'HH24:MI') AS horario FROM dentistas d
      JOIN dentista_procedimentos dp ON dp.id_dentista=d.id
      JOIN procedimentos p ON p.id=dp.id_procedimento
      JOIN disponibilidade_dentista dd ON dd.id_dentista=d.id
      JOIN funcionamento_clinica fc ON fc.id_clinica=1 AND fc.id_dia_semana=dd.id_dia_semana
      WHERE p.nome=:proc AND (:dentista=0 OR d.id=:dentista_escolhido) AND d.status='ativo' AND d.id_sala IS NOT NULL
        AND dd.id_dia_semana=EXTRACT(DOW FROM CAST(:data AS date))
        AND dd.hora>=fc.abertura AND dd.hora+make_interval(mins=>p.duracao_min)<=fc.fechamento
        AND (CAST(:data2 AS date)>CURRENT_DATE OR dd.hora>LOCALTIME)
        AND NOT EXISTS (
          SELECT 1 FROM agendamentos a JOIN procedimentos op ON op.id=a.id_procedimento
          WHERE a.data=CAST(:data3 AS date) AND a.status<>'Cancelado'
            AND (a.id_dentista=d.id OR a.id_sala=d.id_sala OR (:paciente > 0 AND a.id_paciente=:paciente2))
            AND tsrange(a.data+a.hora,a.data+a.hora+make_interval(mins=>op.duracao_min),'[)') &&
            tsrange(CAST(:data4 AS date)+dd.hora,CAST(:data5 AS date)+dd.hora+make_interval(mins=>p.duracao_min),'[)')
        ) ORDER BY 1");
    $stmt->execute(['proc'=>$procedimento,'dentista'=>$dentista,'dentista_escolhido'=>$dentista,'data'=>$data,'data2'=>$data,
        'data3'=>$data,'paciente'=>$paciente,'paciente2'=>$paciente,'data4'=>$data,'data5'=>$data]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Próximos horários livres de cada dentista no dia (quadro da recepção), numa consulta só.
 * Mesmas regras de horariosLivres para dentista e sala, com o menor procedimento que ele atende.
 */
function proximosHorariosPorDentista(string $data): array {
    $pdo=\App\Config\Database::getConnection();
    $limite=$pdo->prepare("SELECT (SELECT count(*) FROM agendamentos WHERE data=CAST(:data AS date) AND status<>'Cancelado')
        >= (SELECT limite_agendamentos_dia FROM clinica WHERE id=1)");
    $limite->execute(['data'=>$data]);
    if (booleanoPg($limite->fetchColumn())) return []; // dia lotado: ninguém tem horário
    $stmt=$pdo->prepare("SELECT d.id, to_char(dd.hora,'HH24:MI') AS hora FROM dentistas d
      JOIN disponibilidade_dentista dd ON dd.id_dentista=d.id
      JOIN funcionamento_clinica fc ON fc.id_clinica=1 AND fc.id_dia_semana=dd.id_dia_semana
      CROSS JOIN LATERAL (SELECT min(p.duracao_min) AS duracao FROM dentista_procedimentos dp
          JOIN procedimentos p ON p.id=dp.id_procedimento WHERE dp.id_dentista=d.id) m
      WHERE d.status='ativo' AND d.id_sala IS NOT NULL AND m.duracao IS NOT NULL
        AND dd.id_dia_semana=EXTRACT(DOW FROM CAST(:data AS date))
        AND dd.hora>=fc.abertura AND dd.hora+make_interval(mins=>m.duracao)<=fc.fechamento
        AND (CAST(:data2 AS date)>CURRENT_DATE OR dd.hora>LOCALTIME)
        AND NOT EXISTS (
          SELECT 1 FROM agendamentos a JOIN procedimentos op ON op.id=a.id_procedimento
          WHERE a.data=CAST(:data3 AS date) AND a.status<>'Cancelado' AND (a.id_dentista=d.id OR a.id_sala=d.id_sala)
            AND tsrange(a.data+a.hora,a.data+a.hora+make_interval(mins=>op.duracao_min),'[)') &&
                tsrange(CAST(:data4 AS date)+dd.hora,CAST(:data5 AS date)+dd.hora+make_interval(mins=>m.duracao),'[)'))
      ORDER BY d.id, dd.hora");
    $stmt->execute(['data'=>$data,'data2'=>$data,'data3'=>$data,'data4'=>$data,'data5'=>$data]);
    $porDentista=[];
    foreach ($stmt->fetchAll() as $linha) $porDentista[(int)$linha['id']][]=$linha['hora'];
    return $porDentista;
}

/**
 * Linhas da agenda da recepção, a partir da vw_agendamentos (fonte única das colunas exibidas).
 * $condicao filtra a tabela agendamentos com o apelido "ag" (ex.: "ag.data = CAST(:data AS date)").
 */
function agendaRecepcao(string $condicao, array $params = []): array {
    $stmt=\App\Config\Database::getConnection()->prepare("SELECT v.*, v.servico AS procedimento
        FROM vw_agendamentos v JOIN agendamentos ag ON ag.id=v.id WHERE $condicao ORDER BY ag.data, ag.hora, ag.id");
    $stmt->execute($params);
    $linhas=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($linhas as &$l) $l['pago']=booleanoPg($l['pago']);
    return $linhas;
}

/** Primeira data que o paciente pode escolher no portal (a recepção não tem essa restrição). */
function dataMinimaPaciente(PDO $pdo): string {
    $dias=(int)$pdo->query('SELECT antecedencia_min_dias FROM clinica WHERE id=1')->fetchColumn();
    return date('Y-m-d', strtotime("+{$dias} days"));
}

/** Até quando a agenda aceita marcações (evita consultas em 2099 por erro de digitação). */
const AGENDA_DIAS_A_FRENTE = 365;

/** Aplica "Antecedência mínima para o paciente" e "Limite de consultas por dia" de Configurações. */
function validarRegrasClinica(PDO $pdo, string $data, string $perfil): void {
    if ($perfil==='cliente' && $data<dataMinimaPaciente($pdo)) {
        throw new InvalidArgumentException('Pelo portal, agende a partir de '.date('d/m/Y',strtotime(dataMinimaPaciente($pdo))).'. Para antes disso, fale com a recepção.');
    }
    // Serializa a contagem por data: duas reservas simultâneas não passam juntas do limite.
    $pdo->prepare('SELECT pg_advisory_xact_lock(4000000000 + (CAST(:data AS date) - DATE \'2000-01-01\'))')->execute(['data'=>$data]);
    $stmt=$pdo->prepare("SELECT (SELECT count(*) FROM agendamentos WHERE data=CAST(:data AS date) AND status<>'Cancelado')
        >= (SELECT limite_agendamentos_dia FROM clinica WHERE id=1)");
    $stmt->execute(['data'=>$data]);
    if ($stmt->fetchColumn()) throw new InvalidArgumentException('A agenda deste dia atingiu o limite de consultas definido pela clínica. Escolha outra data.');
}

/** Cria de fato no PostgreSQL e devolve o ID confirmado pelo INSERT ... RETURNING. */
function criarAgendamento(array $dados): int {
    $u=exigirPerfil(['recepcionista','cliente']);
    $data=dataValida($dados['data'] ?? '','data da consulta',hoje(),emDias(AGENDA_DIAS_A_FRENTE));
    $hora=trim((string)($dados['horario'] ?? ''));
    if ($hora==='') throw new InvalidArgumentException('Escolha um horário.');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$hora)) throw new InvalidArgumentException('Horário inválido.');
    if ($data===hoje() && $hora<=date('H:i')) throw new InvalidArgumentException('Escolha um horário futuro.');
    $paciente=idParametro($dados['paciente_id'] ?? 0);
    if ($u['tipo']==='cliente') {
        $meuPaciente=pacienteSessao();
        if (!$meuPaciente) throw new InvalidArgumentException('Seu cadastro de paciente não foi encontrado.');
        if ($paciente && $paciente!==(int)$meuPaciente['id']) throw new RuntimeException('Paciente não autorizado.');
        $paciente=(int)$meuPaciente['id'];
    }
    if ($paciente<1 || $paciente>2147483647) throw new InvalidArgumentException('Selecione um paciente.');
    $nomeProc=trim((string)($dados['procedimento'] ?? ''));
    if ($nomeProc==='') throw new InvalidArgumentException('Selecione um procedimento.');
    $idDentista=idParametro($dados['dentista'] ?? 0);
    if ($idDentista<0 || $idDentista>2147483647) throw new InvalidArgumentException('Dentista inválido.');
    $obs=textoValido($dados['obs'] ?? '','a observação',2000,false);
    return transacaoOperacao(function (PDO $pdo) use($u,$paciente,$idDentista,$nomeProc,$data,$hora,$dados,$obs) {
        $stmt=$pdo->prepare("SELECT id FROM pacientes WHERE id=:id AND status='ativo'");
        $stmt->execute(['id'=>$paciente]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Paciente ativo não encontrado.');
        $stmt=$pdo->prepare('SELECT id,duracao_min,valor_base FROM procedimentos WHERE nome=:nome');
        $stmt->execute(['nome'=>$nomeProc]);
        $proc=$stmt->fetch();
        if (!$proc) throw new InvalidArgumentException('Procedimento não cadastrado.');

        // Sem preferência de dentista: selecione profissional realmente habilitado e
        // com horário cadastrado. Não escolha aleatoriamente um dentista incompatível.
        $stmt=$pdo->prepare("SELECT d.id,d.id_sala FROM dentistas d
            JOIN dentista_procedimentos dp ON dp.id_dentista=d.id AND dp.id_procedimento=:procedimento
            JOIN disponibilidade_dentista dd ON dd.id_dentista=d.id
              AND dd.id_dia_semana=EXTRACT(DOW FROM CAST(:data AS date)) AND dd.hora=CAST(:hora AS time)
            JOIN funcionamento_clinica fc ON fc.id_clinica=1 AND fc.id_dia_semana=dd.id_dia_semana
            WHERE d.status='ativo' AND d.id_sala IS NOT NULL
              AND dd.hora>=fc.abertura
              AND dd.hora+make_interval(mins=>:duracao) <= fc.fechamento
              AND (:dentista=0 OR d.id=:dentista_escolhido)
            ORDER BY d.id");
        $stmt->execute(['procedimento'=>$proc['id'],'data'=>$data,'hora'=>$hora,
            'duracao'=>(int)$proc['duracao_min'],'dentista'=>$idDentista,'dentista_escolhido'=>$idDentista]);
        $possiveis=$stmt->fetchAll();
        if (!$possiveis) {
            $aberto=$pdo->prepare('SELECT 1 FROM funcionamento_clinica WHERE id_clinica=1 AND id_dia_semana=EXTRACT(DOW FROM CAST(:data AS date))');
            $aberto->execute(['data'=>$data]);
            if (!$aberto->fetchColumn()) throw new InvalidArgumentException('A clínica não abre nesse dia da semana. Escolha outra data.');
            throw new InvalidArgumentException('Esse dentista não atende esse procedimento/horário. Escolha um horário disponível.');
        }
        validarRegrasClinica($pdo,$data,$u['tipo']);
        $valor=$proc['valor_base'];
        if ($u['tipo']==='recepcionista' && trim((string)($dados['valor'] ?? ''))!=='') {
            $valor=valorMonetario((string)$dados['valor']);
        }
        $salaNome=$u['tipo']==='recepcionista' ? trim((string)($dados['sala'] ?? '')) : '';
        if ($salaNome!=='') {
            $stmt=$pdo->prepare('SELECT id FROM sala WHERE nome=:nome');
            $stmt->execute(['nome'=>$salaNome]);
            $salaEscolhida=$stmt->fetchColumn();
            if (!$salaEscolhida) throw new InvalidArgumentException('Sala inválida.');
        }
        foreach ($possiveis as $d) {
            $sala=$salaNome!=='' ? (int)$salaEscolhida : (int)$d['id_sala'];
            // Detecta sobreposição para tentar outro profissional se for "sem preferência";
            // a trigger do banco repete a checagem de forma segura contra concorrência.
            $livre=$pdo->prepare("SELECT NOT EXISTS (
                SELECT 1 FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento
                WHERE a.data=CAST(:data AS date) AND a.status<>'Cancelado'
                  AND (a.id_dentista=:dentista OR a.id_paciente=:paciente OR a.id_sala=:sala)
                  AND tsrange(a.data+a.hora,a.data+a.hora+make_interval(mins=>p.duracao_min),'[)') &&
                      tsrange(CAST(:dt AS date)+CAST(:hr AS time),
                        CAST(:dtfim AS date)+CAST(:hrfim AS time)+make_interval(mins=>:duracao),'[)')
            )");
            $livre->execute(['data'=>$data,'dentista'=>$d['id'],'paciente'=>$paciente,'sala'=>$sala,
                'dt'=>$data,'hr'=>$hora,'dtfim'=>$data,'hrfim'=>$hora,'duracao'=>(int)$proc['duracao_min']]);
            if (!$livre->fetchColumn()) continue;
            $stmt=$pdo->prepare('INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor,observacoes)
                VALUES (:paciente,:dentista,:procedimento,:sala,:data,:hora,:valor,:obs) RETURNING id');
            $stmt->execute(['paciente'=>$paciente,'dentista'=>$d['id'],'procedimento'=>$proc['id'],
                'sala'=>$sala,'data'=>$data,'hora'=>$hora,'valor'=>$valor,'obs'=>$obs]);
            $id=(int)$stmt->fetchColumn();
            if ($id<1) throw new RuntimeException('Banco não retornou o número do agendamento.');
            return $id;
        }
        $proprio=$pdo->prepare("SELECT 1 FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento WHERE a.id_paciente=:paciente
            AND a.data=CAST(:data AS date) AND a.status<>'Cancelado'
            AND tsrange(a.data+a.hora,a.data+a.hora+make_interval(mins=>p.duracao_min),'[)') && tsrange(CAST(:d2 AS date)+CAST(:h AS time),CAST(:d3 AS date)+CAST(:h2 AS time)+make_interval(mins=>:dur),'[)')");
        $proprio->execute(['paciente'=>$paciente,'data'=>$data,'d2'=>$data,'d3'=>$data,'h'=>$hora,'h2'=>$hora,'dur'=>(int)$proc['duracao_min']]);
        if ($proprio->fetchColumn()) throw new InvalidArgumentException('O paciente já tem uma consulta nesse horário. Escolha outro horário.');
        throw new InvalidArgumentException('Horário ocupado por outro paciente, dentista ou sala. Escolha outro.');
    });
}

/**
 * Remarca uma consulta: cancela a original e cria outra para o mesmo paciente e procedimento,
 * passando por todas as regras de criarAgendamento. Tudo numa transação: se a nova falhar, a original volta.
 */
function reagendarAgendamento(int $id, array $dados): int {
    exigirPerfil(['recepcionista']);
    return transacaoOperacao(function(PDO $pdo) use($id,$dados) {
        $stmt=$pdo->prepare('SELECT a.id,a.id_paciente,a.id_dentista,a.status,a.observacoes,a.valor,p.nome AS procedimento,
            to_char(a.data,\'DD/MM/YYYY\') AS data_txt,to_char(a.hora,\'HH24:MI\') AS hora_txt
            FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento WHERE a.id=:id FOR UPDATE OF a');
        $stmt->execute(['id'=>$id]);
        $a=$stmt->fetch();
        if (!$a) throw new InvalidArgumentException('Consulta para remarcar não encontrada.');
        if (!in_array($a['status'],['Agendado','Confirmado'],true)) throw new InvalidArgumentException('Só é possível remarcar consultas agendadas ou confirmadas.');
        alterarStatusAgenda($id,'Cancelado'); // também recusa consulta já paga
        $obs=trim('Remarcada da consulta #'.$id.' ('.$a['data_txt'].' '.$a['hora_txt'].'). '.trim((string)($dados['obs'] ?? '') ?: (string)$a['observacoes']));
        return criarAgendamento([
            'paciente_id'=>$a['id_paciente'], 'procedimento'=>$a['procedimento'],
            'dentista'=>(int)($dados['dentista'] ?? 0) ?: $a['id_dentista'],
            'data'=>$dados['data'] ?? '', 'horario'=>$dados['horario'] ?? '',
            'sala'=>$dados['sala'] ?? '', 'obs'=>cortarTexto($obs,0,2000),
            // mantém o valor combinado na consulta original, salvo se a recepção informar outro
            'valor'=>trim((string)($dados['valor'] ?? ''))!=='' ? $dados['valor'] : number_format((float)$a['valor'],2,'.',''),
        ]);
    });
}

function alterarStatusAgenda(int $id, string $status): void {
    $u=exigirPerfil(['recepcionista','dentista','cliente']);
    transacaoOperacao(function(PDO $pdo) use($u,$id,$status) {
        $stmt=$pdo->prepare('SELECT * FROM agendamentos WHERE id=:id FOR UPDATE');
        $stmt->execute(['id'=>$id]);
        $a=$stmt->fetch();
        if ($u['tipo']==='cliente') {
            $p=pacienteSessao();
            if (!$a || !$p || (int)$p['id']!==(int)$a['id_paciente'] || $status!=='Cancelado') throw new RuntimeException('Consulta não encontrada.');
        }
        if (!$a) throw new InvalidArgumentException('Consulta não encontrada.');
        if ($u['tipo']==='cliente') {
            // Depois do horário marcado, o desfecho (atendido ou falta) é registrado pela recepção.
            if (faltaPermitida($a['data'],$a['hora'])) throw new RuntimeException('A consulta já começou ou passou. Para cancelar, fale com a recepção.');
        } elseif ($u['tipo']==='dentista') {
            $d=dentistaSessao();
            if (!$d || (int)$d['id']!==(int)$a['id_dentista']) throw new RuntimeException('Consulta de outro dentista.');
            // O dentista conduz o atendimento; cancelar, confirmar e registrar falta é com a recepção.
            if (!in_array($status,['Em atendimento','Concluída'],true)) throw new RuntimeException('Peça à recepção para cancelar, confirmar ou registrar falta.');
        }
        $transicoes=['Agendado'=>['Confirmado','Aguardando','Cancelado','Faltou'],'Confirmado'=>['Aguardando','Cancelado','Faltou'],
            'Aguardando'=>['Em atendimento','Cancelado'],'Em atendimento'=>['Concluída']];
        if (!in_array($status,$transicoes[$a['status']] ?? [],true)) throw new InvalidArgumentException('Mudança de status não permitida.');
        if ($u['tipo']==='cliente' && !in_array($a['status'],['Agendado','Confirmado'],true)) throw new RuntimeException('A consulta já está em atendimento.');
        if (in_array($status,['Aguardando','Em atendimento','Concluída'],true) && $a['data']>hoje()) {
            throw new InvalidArgumentException('Check-in e atendimento só a partir do dia da consulta.');
        }
        if ($status==='Faltou') {
            if ($u['tipo']!=='recepcionista') throw new RuntimeException('Só a recepção registra faltas.');
            if (!faltaPermitida($a['data'],$a['hora'])) throw new InvalidArgumentException('A falta só pode ser registrada a partir do horário da consulta.');
        }
        if (in_array($status,STATUS_SEM_COBRANCA,true)) {
            $stmt=$pdo->prepare('SELECT 1 FROM pagamento WHERE id_agendamento=:id AND pago_em IS NOT NULL');$stmt->execute(['id'=>$id]);
            if ($stmt->fetchColumn()) throw new InvalidArgumentException('Consulta paga: estorne o pagamento na agenda (botão "Estornar") antes de cancelar, remarcar ou registrar falta.');
        }
        $pdo->prepare('UPDATE agendamentos SET status=:status WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);
        registrarHistoricoStatus($pdo,$id,$a['status'],$status,(int)$u['id']);
    });
}

/**
 * Consulta que o paciente ainda vai ter: "Aguardando" (já chegou) ou agendada/confirmada cujo horário ainda não chegou.
 * Regra única das telas do paciente; no SQL, o equivalente está em SQL_CONSULTA_FUTURA.
 */
function consultaFutura(array $a): bool {
    if ($a['status'] === 'Aguardando') return $a['data'] === hoje();
    return in_array($a['status'], ['Agendado','Confirmado'], true) && !faltaPermitida($a['data'], $a['hora']);
}
const SQL_CONSULTA_FUTURA = "((status='Aguardando' AND data=CURRENT_DATE) OR (status IN ('Agendado','Confirmado') AND data+hora > LOCALTIMESTAMP))";

/** Regra única (tela e servidor): a falta vale a partir do minuto marcado, no relógio da clínica (America/Cuiaba). */
function faltaPermitida(string $data, string $hora, ?DateTimeImmutable $agora=null): bool {
    return $data.' '.substr($hora,0,5) <= ($agora ?? new DateTimeImmutable())->format('Y-m-d H:i');
}

function registrarHistoricoStatus(PDO $pdo, int $id, string $de, string $para, int $usuario): void {
    $pdo->prepare('INSERT INTO historico_status_agendamento (id_agendamento,status_anterior,status_novo,id_usuario)
        VALUES (:id,:de,:para,:usuario)')->execute(['id'=>$id,'de'=>$de,'para'=>$para,'usuario'=>$usuario]);
}

/** Desfaz uma falta lançada por engano: volta ao status anterior registrado no histórico. Nada é apagado. */
function corrigirFalta(int $id): string {
    $u=exigirPerfil(['recepcionista']);
    return transacaoOperacao(function(PDO $pdo) use($u,$id) {
        $stmt=$pdo->prepare('SELECT status::text FROM agendamentos WHERE id=:id FOR UPDATE');$stmt->execute(['id'=>$id]);
        $atual=$stmt->fetchColumn();
        if ($atual===false) throw new InvalidArgumentException('Consulta não encontrada.');
        if ($atual!=='Faltou') throw new InvalidArgumentException('Esta consulta não está com falta registrada; nada foi alterado.');
        $stmt=$pdo->prepare("SELECT status_anterior::text FROM historico_status_agendamento
            WHERE id_agendamento=:id AND status_novo='Faltou' ORDER BY alterado_em DESC,id DESC LIMIT 1");
        $stmt->execute(['id'=>$id]);
        // Faltas anteriores ao histórico (migração 007) não guardam o status de origem.
        $anterior=$stmt->fetchColumn() ?: 'Agendado';
        $pdo->prepare('UPDATE agendamentos SET status=:status WHERE id=:id')->execute(['status'=>$anterior,'id'=>$id]);
        registrarHistoricoStatus($pdo,$id,'Faltou',$anterior,(int)$u['id']);
        return $anterior;
    });
}

// ---------- financeiro ----------

function confirmarPagamento(int $id, int $forma): void {
    exigirPerfil(['recepcionista','dono']);
    transacaoOperacao(function(PDO $pdo) use($id,$forma) {
        $stmt=$pdo->prepare('SELECT id FROM forma_pagamento WHERE id=:id');
        $stmt->execute(['id'=>$forma]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Escolha uma forma de pagamento válida.');
        $stmt=$pdo->prepare('SELECT id,valor,status FROM agendamentos WHERE id=:id FOR UPDATE');
        $stmt->execute(['id'=>$id]);
        $ag=$stmt->fetch();
        if (!$ag || in_array($ag['status'],STATUS_SEM_COBRANCA,true)) throw new InvalidArgumentException('Consulta inexistente, cancelada ou com falta registrada.');
        // Um recebimento confirmado não é reescrito: mudaria a data e o mês da receita já fechada.
        $stmt=$pdo->prepare("SELECT to_char(pago_em,'DD/MM/YYYY') FROM pagamento WHERE id_agendamento=:id AND pago_em IS NOT NULL");
        $stmt->execute(['id'=>$id]);
        $pagoEm=$stmt->fetchColumn();
        if ($pagoEm!==false) throw new InvalidArgumentException('Esta consulta já foi paga em '.$pagoEm.'. O recebimento não foi registrado de novo.');
        $stmt=$pdo->prepare('INSERT INTO pagamento (id_agendamento,id_forma_pagamento,valor,pago_em)
            VALUES (:id,:forma,:valor,now()) ON CONFLICT (id_agendamento)
            DO UPDATE SET id_forma_pagamento=EXCLUDED.id_forma_pagamento,valor=EXCLUDED.valor,pago_em=now()');
        $stmt->execute(['id'=>$id,'forma'=>$forma,'valor'=>$ag['valor']]);
    });
}

/**
 * Desfaz um recebimento (paciente desistiu, pagou adiantado e cancelou, pagou e não veio).
 * O pagamento original fica registrado em pagamento_estorno com quem estornou, quando e por quê.
 * No financeiro (vw_movimentos_financeiros) a receita continua no mês em que entrou e a devolução sai no mês do estorno:
 * um mês já fechado nunca muda;
 * a consulta volta a "não paga" e pode então ser cancelada, remarcada, receber falta ou ser recebida de novo.
 */
function estornarPagamento(int $id, string $motivo): void {
    $u=exigirPerfil(['recepcionista','dono']);
    $motivo=textoValido($motivo,'o motivo do estorno',300);
    transacaoOperacao(function(PDO $pdo) use($u,$id,$motivo) {
        $stmt=$pdo->prepare('SELECT id_forma_pagamento,valor,pago_em FROM pagamento WHERE id_agendamento=:id AND pago_em IS NOT NULL FOR UPDATE');
        $stmt->execute(['id'=>$id]);
        $pg=$stmt->fetch();
        if (!$pg) throw new InvalidArgumentException('Esta consulta não tem pagamento confirmado; nada foi estornado.');
        $pdo->prepare('INSERT INTO pagamento_estorno (id_agendamento,id_forma_pagamento,valor,pago_em,id_usuario,motivo)
            VALUES (:id,:forma,:valor,:pago_em,:usuario,:motivo)')
            ->execute(['id'=>$id,'forma'=>$pg['id_forma_pagamento'],'valor'=>$pg['valor'],'pago_em'=>$pg['pago_em'],'usuario'=>$u['id'],'motivo'=>$motivo]);
        $pdo->prepare('UPDATE pagamento SET pago_em=NULL, atualizado_em=now() WHERE id_agendamento=:id')->execute(['id'=>$id]);
    });
}

function registrarLancamento(array $dados): int {
    $u=exigirPerfil(['dono']);
    $valor=valorMonetario((string)($dados['valor'] ?? '0'));
    $descricao=textoValido($dados['descricao'] ?? '','a descrição',200);
    if ((float)$valor<=0) throw new InvalidArgumentException('Informe um valor positivo.');
    $pdo=\App\Config\Database::getConnection();
    $categoria=(int)($dados['id_categoria'] ?? 0);$tipo=(string)($dados['tipo'] ?? '');
    if (!in_array($tipo,['Receita','Despesa'],true)) throw new InvalidArgumentException('Tipo de lançamento inválido.');
    if (!$categoria) {
        $stmt=$pdo->prepare('SELECT id FROM categoria_lancamento WHERE tipo=:tipo ORDER BY id LIMIT 1');$stmt->execute(['tipo'=>$tipo]);$categoria=(int)$stmt->fetchColumn();
    }
    $stmt=$pdo->prepare('SELECT 1 FROM categoria_lancamento WHERE id=:id AND tipo=:tipo');
    $stmt->execute(['id'=>$categoria,'tipo'=>$tipo]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('A categoria deve corresponder ao tipo de lançamento.');
    $data=dataValida($dados['data'] ?? hoje(),'data do lançamento','2000-01-01',hoje());
    $stmt=$pdo->prepare('INSERT INTO lancamento_financeiro (data,tipo,descricao,valor,id_categoria,id_usuario)
        VALUES (:data,:tipo,:descricao,:valor,:categoria,:usuario) RETURNING id');
    $stmt->execute(['data'=>$data,'tipo'=>$tipo,'descricao'=>$descricao,'valor'=>$valor,'categoria'=>$categoria,'usuario'=>$u['id']]);
    return (int)$stmt->fetchColumn();
}

// ---------- cadastros ----------

/** Campos do cadastro de paciente que a recepção pode enviar. Qualquer outro campo (id_usuario, senha…) é ignorado. */
const CAMPOS_CADASTRO_PACIENTE = ['id','nome','cpf','email','telefone','nascimento','plano','observacoes'];

/** Confere os dados de pessoa comuns aos cadastros. Devolve os campos limpos. */
function validarDadosPessoa(array $dados, bool $telefoneObrigatorio=false): array {
    $dados['nome']=textoValido($dados['nome'] ?? '','o nome',150);
    if (tamanhoTexto($dados['nome'])<3) throw new InvalidArgumentException('Informe o nome completo.');
    if (array_key_exists('cpf',$dados) && !cpfValido($dados['cpf'])) throw new InvalidArgumentException('CPF inválido. Confira os 11 números.');
    if (array_key_exists('email',$dados)) {
        $dados['email']=strtolower(trim((string)$dados['email']));
        if ($dados['email']!=='' && (!filter_var($dados['email'],FILTER_VALIDATE_EMAIL) || tamanhoTexto($dados['email'])>150)) {
            throw new InvalidArgumentException('E-mail inválido.');
        }
    }
    if (array_key_exists('telefone',$dados)) {
        $dados['telefone']=trim((string)$dados['telefone']);
        if ($dados['telefone']==='' && $telefoneObrigatorio) throw new InvalidArgumentException('Informe o telefone com DDD.');
        if ($dados['telefone']!=='' && !telefoneValido($dados['telefone'])) {
            throw new InvalidArgumentException('Telefone inválido. Use DDD + número, por exemplo (67) 99999-0000.');
        }
    }
    if (array_key_exists('nascimento',$dados)) {
        $dados['nascimento']=dataValida($dados['nascimento'],'data de nascimento','1900-01-01',hoje(),false) ?? '';
    }
    return $dados;
}

/** E-mail de acesso já usado por outra pessoa? */
function emailDeAcessoEmUso(PDO $pdo, string $email, ?int $pessoa): bool {
    $stmt=$pdo->prepare('SELECT 1 FROM usuarios WHERE email=:email AND id_pessoa IS DISTINCT FROM :pessoa');
    $stmt->execute(['email'=>$email,'pessoa'=>$pessoa]);
    return (bool)$stmt->fetchColumn();
}

function salvarCadastroPaciente(array $dados): int {
    exigirPerfil(['recepcionista','dono']);
    $dados=camposPresentes($dados,CAMPOS_CADASTRO_PACIENTE);
    $idTexto=trim((string)($dados['id'] ?? ''));
    $id=idParametro($idTexto) ?: null;
    if (!$id && !in_array($idTexto, ['', '0'], true)) throw new InvalidArgumentException('Paciente não encontrado.');
    unset($dados['id']); // formulário de paciente novo manda id=0
    if ($id) $dados['id']=$id;
    $emailAnterior=null;
    if ($id) {
        $p=lerJson('pacientes.json',['id'=>$id])[0] ?? null;
        if (!$p) throw new InvalidArgumentException('Paciente não encontrado.');
        $emailAnterior=strtolower(trim((string)($p['email'] ?? '')));
        $dados=array_merge(camposPresentes($p,CAMPOS_CADASTRO_PACIENTE),$dados);
    }
    $dados=validarDadosPessoa($dados);
    $dados['observacoes']=textoValido($dados['observacoes'] ?? '','as observações',2000,false);
    return transacaoOperacao(function(PDO $pdo) use($dados,$emailAnterior) {
        salvarJson('pacientes.json',[$dados]);
        $stmt=$pdo->prepare('SELECT pac.id,pac.id_pessoa FROM pacientes pac JOIN pessoa p ON p.id=pac.id_pessoa WHERE p.cpf=:cpf');
        $stmt->execute(['cpf'=>soDigitos($dados['cpf'])]);
        $pac=$stmt->fetch();
        $pdo->prepare('INSERT INTO prontuarios (id_paciente) VALUES (:id) ON CONFLICT (id_paciente) DO NOTHING')->execute(['id'=>$pac['id']]);
        // Paciente com e-mail ganha conta de acesso ao portal; a senha é definida pelo link emitido em Usuários.
        $email=$dados['email'] ?? '';
        if ($email!=='') {
            if (emailDeAcessoEmUso($pdo,$email,(int)$pac['id_pessoa'])) {
                throw new InvalidArgumentException('Este e-mail já é usado por outro acesso ao sistema. Informe outro e-mail ou deixe em branco.');
            }
            $stmt=$pdo->prepare("SELECT id,email FROM usuarios WHERE id_pessoa=:pessoa AND tipo='cliente'");
            $stmt->execute(['pessoa'=>$pac['id_pessoa']]);
            $acesso=$stmt->fetch();
            if (!$acesso) {
                $pdo->prepare("INSERT INTO usuarios (id_pessoa,email,senha_hash,tipo) VALUES (:pessoa,:email,:senha,'cliente')")
                    ->execute(['pessoa'=>$pac['id_pessoa'],'email'=>$email,'senha'=>senhaSemAcesso()]);
            } elseif ($emailAnterior!==null && $emailAnterior!==$email && $acesso['email']!==$email) {
                // E-mail corrigido pela recepção: o login do portal passa a ser o novo e-mail.
                $pdo->prepare('UPDATE usuarios SET email=:email WHERE id=:id')->execute(['email'=>$email,'id'=>$acesso['id']]);
            }
        }
        return (int)$pac['id'];
    });
}

/**
 * Cadastro feito pelo próprio paciente no portal ("Cadastre-se").
 * Cria pessoa, paciente, prontuário e acesso do tipo cliente numa transação.
 * Se CPF ou e-mail já existem, a resposta é genérica: não revela qual dado está cadastrado.
 */
function cadastrarPacientePortal(array $dados): array {
    // Todos os campos entram na validação, mesmo que o formulário (ou um POST montado à mão) não os envie.
    $pessoa=validarDadosPessoa(array_map('strval',camposPresentes($dados,['nome','cpf','email','telefone','nascimento'])
        +['nome'=>'','cpf'=>'','email'=>'','telefone'=>'','nascimento'=>'']),true);
    if (($pessoa['email'] ?? '')==='') throw new InvalidArgumentException('Informe o e-mail. Ele será o seu login.');
    if (($pessoa['nascimento'] ?? '')==='') throw new InvalidArgumentException('Informe a data de nascimento.');
    validarNovaSenha((string)($dados['senha'] ?? ''),(string)($dados['confirmar_senha'] ?? ''));
    if (empty($dados['aceite'])) throw new InvalidArgumentException('Para criar a conta, aceite o uso dos seus dados pela clínica.');
    $pessoa['cpf']=soDigitos($pessoa['cpf']);
    if (limiteAtingido('cadastro',10,60)) {
        throw new RuntimeException('Muitos cadastros feitos deste computador na última hora. Tente mais tarde ou procure a clínica.');
    }
    marcarUsuarioNoBanco('cadastro-publico:'.$pessoa['email']);
    // Conta só as tentativas que chegam a consultar o banco (as que poderiam descobrir CPF/e-mail cadastrados).
    registrarTentativa('cadastro');
    $jaCadastrado='Não foi possível concluir o cadastro com esses dados. Se você já é paciente, peça à clínica o link de acesso ao portal.';
    try {
        return transacaoOperacao(function(PDO $pdo) use($pessoa,$dados,$jaCadastrado) {
            $stmt=$pdo->prepare('SELECT 1 FROM pessoa WHERE cpf=:cpf UNION ALL SELECT 1 FROM usuarios WHERE email=:email LIMIT 1');
            $stmt->execute(['cpf'=>$pessoa['cpf'],'email'=>$pessoa['email']]);
            if ($stmt->fetchColumn()) throw new InvalidArgumentException($jaCadastrado);
            salvarJson('pacientes.json',[$pessoa+['plano'=>'Particular']]);
            $stmt=$pdo->prepare('SELECT pac.id,pac.id_pessoa FROM pacientes pac JOIN pessoa p ON p.id=pac.id_pessoa WHERE p.cpf=:cpf');
            $stmt->execute(['cpf'=>$pessoa['cpf']]);
            $pac=$stmt->fetch();
            $pdo->prepare('INSERT INTO prontuarios (id_paciente) VALUES (:id) ON CONFLICT (id_paciente) DO NOTHING')->execute(['id'=>$pac['id']]);
            $hash=password_hash((string)$dados['senha'],PASSWORD_BCRYPT);
            $stmt=$pdo->prepare("INSERT INTO usuarios (id_pessoa,email,senha_hash,tipo) VALUES (:pessoa,:email,:senha,'cliente') RETURNING id");
            $stmt->execute(['pessoa'=>$pac['id_pessoa'],'email'=>$pessoa['email'],'senha'=>$hash]);
            return ['id'=>(int)$stmt->fetchColumn(),'id_paciente'=>(int)$pac['id'],'nome'=>$pessoa['nome'],'perfil'=>'cliente',
                'versao_senha'=>versaoSenha($hash),'redirecionar'=>PERFIS['cliente']['painel']];
        });
    } catch (PDOException $e) {
        // Corrida com outro cadastro igual: mesma resposta genérica.
        if ($e->getCode()==='23505') throw new InvalidArgumentException($jaCadastrado);
        throw $e;
    }
}

function registrarEvolucao(array $dados): int {
    $u=exigirPerfil(['dentista']); $d=dentistaSessao();
    if (!$d) throw new RuntimeException('Cadastro de dentista ausente.');
    $p=localizarPaciente((string)($dados['paciente'] ?? ''));pacientePermitido((int)$p['id']);
    $obs=textoValido($dados['observacoes'] ?? $dados['obs'] ?? '','a evolução clínica',5000);
    $retorno=dataValida($dados['retorno'] ?? '','data de retorno',hoje(),emDias(730),false);
    $dentes=[];
    foreach (array_unique(preg_split('/[\s,;]+/',trim((string)($dados['dentes'] ?? '')),-1,PREG_SPLIT_NO_EMPTY)) as $numero) {
        if (!ctype_digit($numero) || strlen($numero)>2 || !denteValido((int)$numero)) {
            throw new InvalidArgumentException('Dente "'.cortarTexto($numero,0,10).'" inválido. Use a numeração FDI (11 a 48; decíduos 51 a 85).');
        }
        $dentes[]=(int)$numero;
    }
    return transacaoOperacao(function(PDO $pdo) use($u,$dados,$d,$p,$obs,$retorno,$dentes) {
        if (trim((string)($dados['procedimento'] ?? ''))==='') throw new InvalidArgumentException('Selecione o procedimento realizado.');
        $stmt=$pdo->prepare('SELECT id FROM procedimentos WHERE nome=:nome');$stmt->execute(['nome'=>$dados['procedimento']]);$proc=$stmt->fetchColumn();
        if (!$proc || !in_array($dados['procedimento'],$d['procedimentos'],true)) throw new InvalidArgumentException('Procedimento não habilitado para o dentista.');
        $stmt=$pdo->prepare('INSERT INTO prontuarios (id_paciente) VALUES (:id) ON CONFLICT (id_paciente) DO UPDATE SET atualizado_em=now() RETURNING id');
        $stmt->execute(['id'=>$p['id']]);$pr=$stmt->fetchColumn();
        // Vincula a evolução à consulta mais recente compatível, quando ela existe.
        // Atendimentos avulsos continuam permitidos sem agendamento.
        // Consulta de outro dia sem check-in não é dada como concluída: o desfecho dela fica para a recepção.
        $stmt=$pdo->prepare("SELECT a.id,a.status::text AS status FROM agendamentos a WHERE a.id_paciente=:paciente
            AND a.id_dentista=:dentista AND a.id_procedimento=:procedimento
            AND a.data<=CURRENT_DATE AND a.status NOT IN (".listaSql(STATUS_SEM_COBRANCA).")
            AND (a.data=CURRENT_DATE OR a.status IN ('Aguardando','Em atendimento','Concluída'))
            AND NOT EXISTS (SELECT 1 FROM evolucao_clinica e WHERE e.id_agendamento=a.id)
            ORDER BY a.data DESC,a.hora DESC LIMIT 1 FOR UPDATE OF a");
        $stmt->execute(['paciente'=>$p['id'],'dentista'=>$d['id'],'procedimento'=>$proc]);
        $vinculo=$stmt->fetch() ?: null;
        $consulta=$vinculo['id'] ?? null;
        $stmt=$pdo->prepare('INSERT INTO evolucao_clinica (id_prontuario,id_dentista,id_procedimento,id_agendamento,observacoes,retorno)
            VALUES (:pr,:dent,:proc,:ag,:obs,:retorno) RETURNING id');
        $stmt->execute(['pr'=>$pr,'dent'=>$d['id'],'proc'=>$proc,'ag'=>$consulta,'obs'=>$obs,'retorno'=>$retorno]);
        $id=(int)$stmt->fetchColumn();
        if ($consulta && $vinculo['status']!=='Concluída') {
            $pdo->prepare("UPDATE agendamentos SET status='Concluída' WHERE id=:id")->execute(['id'=>$consulta]);
            registrarHistoricoStatus($pdo,(int)$consulta,$vinculo['status'],'Concluída',(int)$u['id']);
        }
        foreach ($dentes as $numero) {
            $stmt=$pdo->prepare("INSERT INTO odontograma_dentes (id_prontuario,numero_dente,status,notas) VALUES (:pr,:num,'tratado',:obs)
                ON CONFLICT (id_prontuario,numero_dente) DO UPDATE SET status=EXCLUDED.status,notas=EXCLUDED.notas RETURNING id");
            $stmt->execute(['pr'=>$pr,'num'=>$numero,'obs'=>$obs]);$dente=$stmt->fetchColumn();
            $pdo->prepare('INSERT INTO evolucao_dente VALUES (:ev,:dent)')->execute(['ev'=>$id,'dent'=>$dente]);
            $pdo->prepare('INSERT INTO odontograma_procedimentos (id_dente,descricao) VALUES (:id,:descricao)')->execute(['id'=>$dente,'descricao'=>$dados['procedimento']]);
        }
        return $id;
    });
}

/** Grava o odontograma enviado pela tela. $dentes: {"11": {"status": "tratado", "notas": "..."}, ...} */
function salvarOdontograma(int $paciente, mixed $dentes): void {
    exigirPerfil(['dentista']);pacientePermitido($paciente);
    if (!is_array($dentes)) throw new InvalidArgumentException('Odontograma em formato inválido. Recarregue a página.');
    foreach ($dentes as $num=>$info) {
        if (!is_array($info) || !ctype_digit((string)$num) || !denteValido((int)$num)) throw new InvalidArgumentException('Odontograma com dente inválido. Recarregue a página.');
        if (!in_array($info['status'] ?? 'saudavel',STATUS_DENTE,true)) throw new InvalidArgumentException('Situação do dente '.$num.' inválida.');
        if (!is_string($info['notas'] ?? '') || tamanhoTexto($info['notas'] ?? '')>500) throw new InvalidArgumentException('As notas do dente '.$num.' podem ter até 500 caracteres.');
    }
    transacaoOperacao(function(PDO $pdo) use($paciente,$dentes) {
        $stmt=$pdo->prepare('INSERT INTO prontuarios (id_paciente) VALUES (:id) ON CONFLICT (id_paciente) DO UPDATE SET atualizado_em=now() RETURNING id');
        $stmt->execute(['id'=>$paciente]);
        $pr=$stmt->fetchColumn();
        $stmt=$pdo->prepare('INSERT INTO odontograma_dentes (id_prontuario,numero_dente,status,notas) VALUES (:pr,:num,:status,:notas)
            ON CONFLICT (id_prontuario,numero_dente) DO UPDATE SET status=EXCLUDED.status,notas=EXCLUDED.notas');
        foreach ($dentes as $num=>$info) {
            $stmt->execute(['pr'=>$pr,'num'=>(int)$num,'status'=>$info['status'] ?? 'saudavel','notas'=>($info['notas'] ?? '')!=='' ? $info['notas'] : null]);
        }
    });
}

// ---------- acessos e senhas ----------

/** Hash de uma senha aleatória que ninguém conhece: a conta só é acessada pelo link emitido em Usuários. */
function senhaSemAcesso(): string {
    return password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
}

function emitirRecuperacao(int $usuario): string {
    exigirPerfil(['dono']);
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare("SELECT 1 FROM usuarios WHERE id=:id AND status='ativo'");
    $stmt->execute(['id'=>$usuario]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Usuário não encontrado ou inativo.');
    $token=bin2hex(random_bytes(32));
    $stmt=$pdo->prepare("INSERT INTO recuperacao_senha (id_usuario,token_hash,expira_em)
        VALUES (:id,:hash,now()+interval '30 minutes')");
    $stmt->execute(['id'=>$usuario,'hash'=>hash('sha256',$token)]);
    return $token;
}

function consultarRecuperacao(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/',$token)) return null;
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare('SELECT r.id,r.id_usuario,p.nome,u.email FROM recuperacao_senha r
        JOIN usuarios u ON u.id=r.id_usuario JOIN pessoa p ON p.id=u.id_pessoa
        WHERE r.token_hash=:hash AND r.expira_em>now() AND r.utilizado_em IS NULL');
    $stmt->execute(['hash'=>hash('sha256',$token)]);return $stmt->fetch() ?: null;
}

function redefinirSenha(string $token, string $senha, ?string $confirmacao=null): void {
    validarNovaSenha($senha,$confirmacao);
    transacaoOperacao(function(PDO $pdo) use($token,$senha) {
        $stmt=$pdo->prepare('SELECT id_usuario FROM recuperacao_senha WHERE token_hash=:hash AND expira_em>now() AND utilizado_em IS NULL FOR UPDATE');
        $stmt->execute(['hash'=>hash('sha256',$token)]);$id=$stmt->fetchColumn();
        if (!$id) throw new InvalidArgumentException('Link inválido, expirado ou já utilizado.');
        $pdo->prepare('UPDATE usuarios SET senha_hash=:senha,tentativas_login=0,bloqueado_ate=NULL WHERE id=:id')->execute(['senha'=>password_hash($senha,PASSWORD_BCRYPT),'id'=>$id]);
        $pdo->prepare('UPDATE recuperacao_senha SET utilizado_em=now() WHERE id_usuario=:id AND utilizado_em IS NULL')->execute(['id'=>$id]);
    });
}

function alterarSenhaAtual(array $dados): void {
    $u=usuarioSessao();$pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare('SELECT senha_hash FROM usuarios WHERE id=:id');$stmt->execute(['id'=>$u['id']]);
    if (!password_verify((string)($dados['senha_atual'] ?? ''),(string)$stmt->fetchColumn())) throw new InvalidArgumentException('Senha atual incorreta.');
    $nova=(string)($dados['senha_nova'] ?? '');
    validarNovaSenha($nova,(string)($dados['confirmar_senha_nova'] ?? ''));
    $hash=password_hash($nova,PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE usuarios SET senha_hash=:senha WHERE id=:id')->execute(['senha'=>$hash,'id'=>$u['id']]);
    // Esta sessão continua; as outras sessões desta conta são encerradas no próximo acesso.
    $_SESSION['versao_senha']=versaoSenha($hash);
}

// ---------- dentistas e clínica ----------

function validarCro(?string $cro): array {
    if (!preg_match('/^CRO-([A-Z]{2})\s+(\d{1,10})$/',trim((string)$cro),$m)) throw new InvalidArgumentException('Use o formato CRO-MS 12345.');
    return [$m[1],$m[2]];
}

/** Procedimentos escolhidos: pelo menos um, todos cadastrados. */
function validarProcedimentos(PDO $pdo, mixed $procedimentos): array {
    $lista=array_values(array_unique(array_filter(is_array($procedimentos) ? $procedimentos : [],'is_string')));
    if (!$lista) throw new InvalidArgumentException('Selecione pelo menos um procedimento habilitado.');
    $stmt=$pdo->prepare('SELECT count(*) FROM procedimentos WHERE nome = ANY(CAST(:nomes AS text[]))');
    $stmt->execute(['nomes'=>'{'.implode(',',array_map(fn($n)=>'"'.str_replace(['\\','"'],['\\\\','\\"'],$n).'"',$lista)).'}']);
    if ((int)$stmt->fetchColumn()!==count($lista)) throw new InvalidArgumentException('Algum procedimento escolhido não está cadastrado. Recarregue a página.');
    return $lista;
}

function cadastrarDentista(array $dados): int {
    exigirPerfil(['dono']);
    $dados=validarDadosPessoa(camposPresentes($dados,['nome','cpf','email','telefone','nascimento'])+$dados);
    if (($dados['email'] ?? '')==='') throw new InvalidArgumentException('Informe o e-mail: ele é o login do dentista.');
    if (($dados['nascimento'] ?? '')!=='' && $dados['nascimento']>date('Y-m-d',strtotime('-18 years'))) throw new InvalidArgumentException('Data de nascimento do dentista inválida: ele precisa ter pelo menos 18 anos.');
    [$croUf,$croNumero]=validarCro($dados['cro'] ?? '');
    $admissao=dataValida($dados['admissao'] ?? '','data de admissão','1950-01-01',hoje(),false) ?? hoje();
    $pdo=\App\Config\Database::getConnection();
    $esp=referenciaPorNome($pdo,'especialidade',(string)($dados['especialidade'] ?? ''));
    if ($esp===null) throw new InvalidArgumentException('Escolha a especialidade.');
    $sala=referenciaPorNome($pdo,'sala',(string)($dados['sala'] ?? 'Sala 1'));
    $procedimentos=validarProcedimentos($pdo,$dados['procedimentos'] ?? []);
    if (emailDeAcessoEmUso($pdo,$dados['email'],null)) throw new InvalidArgumentException('Este e-mail já é usado por outro acesso ao sistema.');
    return transacaoOperacao(function(PDO $pdo) use($dados,$croUf,$croNumero,$admissao,$esp,$sala,$procedimentos) {
        $pessoa=gravarPessoa($pdo,camposPresentes($dados,['nome','cpf','email','telefone','nascimento']),null);
        $stmt=$pdo->prepare("INSERT INTO usuarios (id_pessoa,email,senha_hash,tipo) VALUES (:pessoa,:email,:senha,'dentista')");
        $stmt->execute(['pessoa'=>$pessoa,'email'=>$dados['email'],'senha'=>senhaSemAcesso()]);
        $id=gravarCampos($pdo,'dentistas',['id_pessoa'=>$pessoa,'id_especialidade'=>$esp,'id_sala'=>$sala,'cro_uf'=>$croUf,'cro_numero'=>$croNumero,'admissao'=>$admissao]);
        $stmt=$pdo->prepare('INSERT INTO dentista_procedimentos (id_dentista,id_procedimento) SELECT :id,id FROM procedimentos WHERE nome=:nome');
        foreach ($procedimentos as $proc) $stmt->execute(['id'=>$id,'nome'=>$proc]);
        $stmt=$pdo->prepare("INSERT INTO disponibilidade_dentista (id_dentista,id_dia_semana,hora)
            SELECT :id,fc.id_dia_semana,slots.hora::time FROM funcionamento_clinica fc
            CROSS JOIN LATERAL generate_series(date '2000-01-01'+fc.abertura,
                date '2000-01-01'+fc.fechamento-interval '30 minutes', interval '30 minutes') AS slots(hora)
            WHERE fc.id_clinica=1 ON CONFLICT DO NOTHING");
        $stmt->execute(['id'=>$id]);
        return $id;
    });
}

/** Edição do dentista pelo dono: dados, procedimentos e situação (ativo/inativo), tudo validado. */
function salvarEdicaoDentista(int $id, array $dados): void {
    exigirPerfil(['dono']);
    $pdo=\App\Config\Database::getConnection();
    $atual=buscarPorCampo(lerJson('dentistas.json',['id'=>$id]),'id',$id);
    if (!$atual) throw new InvalidArgumentException('Dentista não encontrado.');
    $novo=validarDadosPessoa(camposPresentes($dados,['nome','email','telefone']));
    validarCro($dados['cro'] ?? '');
    $novo['cro']=trim((string)$dados['cro']);
    $novo['especialidade']=(string)($dados['especialidade'] ?? '');
    $novo['sala']=(string)($dados['sala'] ?? '');
    referenciaPorNome($pdo,'especialidade',$novo['especialidade']);
    referenciaPorNome($pdo,'sala',$novo['sala']);
    $novo['titulacao']=textoValido($dados['titulacao'] ?? '','a titulação',100,false);
    $novo['faculdade']=textoValido($dados['faculdade'] ?? '','a faculdade',150,false);
    if (trim($novo['especialidade'])==='' || trim($novo['sala'])==='') throw new InvalidArgumentException('Escolha a especialidade e a sala.');
    $cor=trim((string)($dados['cor'] ?? $atual['cor'] ?? ''));
    if ($cor!=='' && !preg_match('/^#[0-9a-fA-F]{6}$/',$cor)) throw new InvalidArgumentException('Cor inválida. Use o formato #1a2b3c.');
    $novo['cor']=$cor;
    $novo['procedimentos']=validarProcedimentos($pdo,$dados['procedimentos'] ?? []);
    $status=(string)($dados['status'] ?? $atual['status']);
    if (!in_array($status,['Ativo','Inativo'],true)) throw new InvalidArgumentException('Situação inválida.');
    transacaoOperacao(function(PDO $pdo) use($id,$atual,$novo,$status) {
        salvarJson('dentistas.json',[array_merge(['id'=>$id],$novo)]);
        if ($status!==$atual['status']) definirStatusDentista($id,$status==='Ativo');
    });
}

/**
 * Ativa ou inativa um dentista. Inativar bloqueia também o login dele (o acesso aos prontuários)
 * e é recusado enquanto houver consultas futuras abertas, que ficariam sem profissional.
 */
function definirStatusDentista(int $id, bool $ativo): string {
    exigirPerfil(['dono']);
    return transacaoOperacao(function(PDO $pdo) use($id,$ativo) {
        $stmt=$pdo->prepare('SELECT d.id_pessoa,p.nome FROM dentistas d JOIN pessoa p ON p.id=d.id_pessoa WHERE d.id=:id FOR UPDATE OF d');
        $stmt->execute(['id'=>$id]);
        $d=$stmt->fetch();
        if (!$d) throw new InvalidArgumentException('Dentista não encontrado.');
        if (!$ativo) {
            $stmt=$pdo->prepare("SELECT count(*) FROM agendamentos WHERE id_dentista=:id AND data>=CURRENT_DATE AND status IN (".listaSql(STATUS_EM_ABERTO).")");
            $stmt->execute(['id'=>$id]);
            $futuras=(int)$stmt->fetchColumn();
            if ($futuras>0) throw new InvalidArgumentException($d['nome'].' tem '.$futuras.' consulta(s) marcada(s) a partir de hoje. Remarque ou cancele antes de inativar.');
        }
        $novo=$ativo ? 'ativo' : 'inativo';
        $pdo->prepare('UPDATE dentistas SET status=:status WHERE id=:id')->execute(['status'=>$novo,'id'=>$id]);
        $pdo->prepare("UPDATE usuarios SET status=:status WHERE id_pessoa=:pessoa AND tipo='dentista'")->execute(['status'=>$novo,'pessoa'=>$d['id_pessoa']]);
        return $d['nome'];
    });
}

/** Dados da clínica e regras de agendamento (Configurações). */
function salvarConfiguracoes(array $dados): void {
    exigirPerfil(['dono']);
    $clinica=lerJsonObjeto('clinica.json');
    $novo=camposPresentes($dados,['nome','razao_social','cnpj','telefone','email','endereco','bairro','cidade','estado','cep','limite_agendamentos_dia','antecedencia_min_dias']);
    $novo['nome']=textoValido($novo['nome'] ?? '','o nome da clínica',150);
    $novo['razao_social']=textoValido($novo['razao_social'] ?? '','a razão social',200);
    $novo['email']=strtolower(trim((string)($novo['email'] ?? '')));
    if (!filter_var($novo['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail da clínica inválido.');
    // CNPJ novo ou alterado precisa ter dígitos verificadores corretos; o já salvo (ex.: o fictício do seed) não trava outras mudanças.
    $cnpjNovo=cnpjNormalizado($novo['cnpj'] ?? '');
    if (strlen($cnpjNovo)!==14 || ($cnpjNovo!==cnpjNormalizado($clinica['cnpj'] ?? '') && !cnpjValido($cnpjNovo))) throw new InvalidArgumentException('CNPJ inválido. Confira os 14 caracteres (números, ou letras e números no CNPJ novo).');
    $novo['cnpj']=$cnpjNovo;
    $novo['cidade']=textoValido($novo['cidade'] ?? '','a cidade',100);
    $novo['bairro']=textoValido($novo['bairro'] ?? '','o bairro',100);
    if (!telefoneValido($novo['telefone'] ?? '')) throw new InvalidArgumentException('Telefone da clínica inválido. Use DDD + número.');
    $novo['estado']=strtoupper(trim((string)($novo['estado'] ?? '')));
    $uf=\App\Config\Database::getConnection()->prepare('SELECT 1 FROM estado WHERE uf=:uf');
    $uf->execute(['uf'=>$novo['estado']]);
    if (!preg_match('/^[A-Z]{2}$/',$novo['estado']) || !$uf->fetchColumn()) throw new InvalidArgumentException('UF inválida. Use a sigla do estado, por exemplo MS.');
    if (strlen(soDigitos($novo['cep'] ?? ''))!==8) throw new InvalidArgumentException('CEP deve ter 8 números.');
    foreach (['limite_agendamentos_dia'=>[1,500,'O limite de consultas por dia'],'antecedencia_min_dias'=>[0,365,'A antecedência mínima']] as $campo=>[$min,$max,$rotulo]) {
        $valor=trim((string)($novo[$campo] ?? ''));
        if (!ctype_digit($valor) || (int)$valor<$min || (int)$valor>$max) throw new InvalidArgumentException($rotulo.' deve ser um número entre '.$min.' e '.$max.'.');
        $novo[$campo]=(int)$valor;
    }
    salvarJson('clinica.json',array_merge($clinica,$novo));
}

/** Horário de funcionamento: dias com abertura e fechamento; dia vazio = fechado. */
function salvarFuncionamento(array $abertura, array $fechamento): void {
    exigirPerfil(['dono']);
    transacaoOperacao(function(PDO $pdo) use($abertura,$fechamento) {
        $stmt=$pdo->prepare('INSERT INTO funcionamento_clinica (id_clinica,id_dia_semana,abertura,fechamento) VALUES (1,:dia,:abertura,:fechamento)
            ON CONFLICT (id_clinica,id_dia_semana) DO UPDATE SET abertura=EXCLUDED.abertura,fechamento=EXCLUDED.fechamento');
        $atuais=$pdo->query("SELECT id_dia_semana, to_char(abertura,'HH24:MI') AS ab, to_char(fechamento,'HH24:MI') AS fe FROM funcionamento_clinica WHERE id_clinica=1")->fetchAll(PDO::FETCH_UNIQUE);
        foreach ($pdo->query('SELECT id,nome FROM dia_semana ORDER BY id')->fetchAll() as $d) {
            $ab=trim((string)($abertura[$d['id']] ?? ''));$fe=trim((string)($fechamento[$d['id']] ?? ''));
            // Dia sem mudança: não confere de novo (consultas antigas sem desfecho não travam o formulário).
            if ($ab===($atuais[$d['id']]['ab'] ?? '') && $fe===($atuais[$d['id']]['fe'] ?? '')) continue;
            $fechado=($ab==='' && $fe==='');
            if (!$fechado) {
                foreach ([$ab,$fe] as $h) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$h)) throw new InvalidArgumentException($d['nome'].': informe abertura e fechamento no formato 08:00, ou deixe os dois vazios.');
                if ($ab>=$fe) throw new InvalidArgumentException($d['nome'].': a abertura precisa ser antes do fechamento.');
            }
            // Consultas futuras em aberto que ficariam fora do novo horário (início ou fim, pela duração) ou com o dia fechado.
            $fora=$pdo->prepare("SELECT count(*) FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento
                WHERE a.data+a.hora > LOCALTIMESTAMP AND EXTRACT(DOW FROM a.data)=:dia AND a.status IN (".listaSql(STATUS_EM_ABERTO).")
                AND (:fechado OR a.hora < CAST(:ab AS time) OR a.hora + make_interval(mins=>p.duracao_min) > CAST(:fe AS time))");
            $fora->execute(['dia'=>$d['id'],'fechado'=>$fechado ? 'true' : 'false','ab'=>$ab ?: '00:00','fe'=>$fe ?: '23:59']);
            if (($qtd=(int)$fora->fetchColumn())>0) {
                throw new InvalidArgumentException($d['nome'].': há '.$qtd.' consulta(s) marcada(s) fora do novo horário. Remarque ou cancele antes de mudar o horário deste dia.');
            }
            if ($ab==='' && $fe==='') {
                $pdo->prepare('DELETE FROM funcionamento_clinica WHERE id_clinica=1 AND id_dia_semana=:id')->execute(['id'=>$d['id']]);
                continue;
            }
            foreach ([$ab,$fe] as $h) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$h)) throw new InvalidArgumentException($d['nome'].': informe abertura e fechamento no formato 08:00, ou deixe os dois vazios.');
            if ($ab>=$fe) throw new InvalidArgumentException($d['nome'].': a abertura precisa ser antes do fechamento.');
            $stmt->execute(['dia'=>$d['id'],'abertura'=>$ab,'fechamento'=>$fe]);
        }
    });
}

/**
 * POST das páginas "Meu perfil" dos 4 perfis: salvar dados ou trocar a senha.
 * Sucesso redireciona com mensagem; erro devolve [mensagem, dados com o que foi digitado].
 */
function tratarFormularioPerfil(array $dados): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') return ['',$dados];
    try {
        if (isset($_POST['salvar_perfil'])) { salvarPerfilAtual($_POST); redirecionar(paginaAtual(),'Perfil atualizado.'); }
        if (isset($_POST['alterar_senha'])) { alterarSenhaAtual($_POST); redirecionar(paginaAtual(),'Senha alterada.'); }
        return ['Ação inválida.',$dados];
    } catch (Throwable $e) {
        return [mensagemOperacao($e), isset($_POST['salvar_perfil']) ? array_merge($dados,array_map('strval',$_POST)) : $dados];
    }
}

function salvarPerfilAtual(array $dados): void {
    $u=usuarioSessao();
    $permitidos=match($u['tipo']) {
        'cliente'=>['nome','telefone','nascimento','plano','alergias','observacoes'],
        'dentista'=>['nome','telefone','nascimento','titulacao','faculdade'],
        default=>['nome','telefone','nascimento']
    };
    $novos=validarDadosPessoa(camposPresentes($dados,$permitidos));
    foreach (['alergias'=>500,'observacoes'=>2000,'titulacao'=>100,'faculdade'=>150] as $campo=>$max) {
        if (array_key_exists($campo,$novos)) $novos[$campo]=textoValido($novos[$campo],'o campo '.$campo,$max,false);
    }
    if (array_key_exists('plano',$novos)) {
        // O paciente escolhe entre os planos cadastrados; texto livre não cria plano novo.
        $novos['plano']=trim((string)$novos['plano']) ?: 'Particular';
        referenciaPorNome(\App\Config\Database::getConnection(),'plano_saude',$novos['plano']);
    }
    transacaoOperacao(function(PDO $pdo) use ($u,$novos) {
        $fonte=match($u['tipo']) {'cliente'=>'pacientes.json','dentista'=>'dentistas.json',default=>'usuarios.json'};
        $registro=lerJson($fonte,[$fonte==='usuarios.json'?'id':'id_usuario'=>$u['id']])[0] ?? null;
        if (!$registro) throw new RuntimeException('Perfil não cadastrado.');
        salvarJson($fonte,[['id'=>$registro['id']]+$novos]);
    });
    $_SESSION['usuario']=$novos['nome'];
}
