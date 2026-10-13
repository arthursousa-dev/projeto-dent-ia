<?php
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

function mensagemOperacao(Throwable $e): string {
    if ($e instanceof PDOException) {
        $erroId = bin2hex(random_bytes(4));
        error_log('[DENTIA]['.$erroId.'] SQLSTATE='.$e->getCode().' '.$e->getMessage());
        if (getenv('APP_DEBUG') === 'true') return 'Erro SQLSTATE '.$e->getCode().' ('.$erroId.'): '.$e->getMessage();
        return match ($e->getCode()) {
            '23505'=>'Já existe um cadastro ou reserva para esses dados. Escolha outro horário.',
            '23503'=>'O registro possui vínculos ou o procedimento não é habilitado para esse dentista.',
            '23514','22001'=>'Confira CPF, telefone, datas e demais campos informados.',
            default=>'Não foi possível salvar no banco. Consulte os logs do Railway (erro '.$erroId.', SQLSTATE '.$e->getCode().').',
        };
    }
    return $e->getMessage();
}

function pacienteSessao(): ?array {
    $u=usuarioSessao();
    return buscarPorCampo(lerJson('pacientes.json'),'id_usuario',$u['id']);
}

function dentistaSessao(): ?array {
    $u=usuarioSessao();
    return buscarPorCampo(lerJson('dentistas.json'),'id_usuario',$u['id']);
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
    if (!$stmt->fetchColumn()) throw new RuntimeException('Paciente não vinculado a este dentista.');
}

/** Horários de um profissional no dia; reflete os turnos cadastrados no PostgreSQL. */
function horariosDisponiveis(int $dentista, string $data): array {
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare("SELECT to_char(dd.hora,'HH24:MI') FROM disponibilidade_dentista dd
        WHERE dd.id_dentista=:dentista AND dd.id_dia_semana=EXTRACT(DOW FROM CAST(:data AS date))
        AND EXISTS (SELECT 1 FROM funcionamento_clinica fc WHERE fc.id_clinica=1
            AND fc.id_dia_semana=dd.id_dia_semana AND dd.hora>=fc.abertura AND dd.hora<fc.fechamento)
        AND NOT EXISTS (SELECT 1 FROM agendamentos a WHERE a.id_dentista=dd.id_dentista
            AND a.data=CAST(:dia AS date) AND a.status <> 'Cancelado'
            AND dd.hora >= a.hora AND dd.hora < a.hora +
                (SELECT make_interval(mins=>p.duracao_min) FROM procedimentos p WHERE p.id=a.id_procedimento))
        ORDER BY dd.hora");
    $stmt->execute(['dentista'=>$dentista,'data'=>$data,'dia'=>$data]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Cria de fato no PostgreSQL e devolve o ID confirmado pelo INSERT ... RETURNING. */
function criarAgendamento(array $dados): int {
    $u=exigirPerfil(['recepcionista','cliente']);
    $data=(string)($dados['data'] ?? '');
    $hora=(string)($dados['horario'] ?? '');
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$data);
    if (!$dt || $dt->format('Y-m-d')!==$data || $data<date('Y-m-d')) throw new InvalidArgumentException('Informe uma data atual ou futura.');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$hora)) throw new InvalidArgumentException('Horário inválido.');
    if ($data===date('Y-m-d') && $hora<=date('H:i')) throw new InvalidArgumentException('Escolha um horário futuro.');
    $paciente=(int)($dados['paciente_id'] ?? 0);
    if ($u['tipo']==='cliente') {
        $meuPaciente=pacienteSessao();
        if (!$meuPaciente) throw new InvalidArgumentException('Seu cadastro de paciente não foi encontrado.');
        if ($paciente && $paciente!==(int)$meuPaciente['id']) throw new RuntimeException('Paciente não autorizado.');
        $paciente=(int)$meuPaciente['id'];
    }
    if ($paciente<1) throw new InvalidArgumentException('Selecione um paciente.');
    $nomeProc=trim((string)($dados['procedimento'] ?? ''));
    if ($nomeProc==='') throw new InvalidArgumentException('Selecione um procedimento.');
    $idDentista=(int)($dados['dentista'] ?? 0);
    return transacaoOperacao(function (PDO $pdo) use($u,$paciente,$idDentista,$nomeProc,$data,$hora,$dados) {
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
        if (!$possiveis) throw new InvalidArgumentException('Esse dentista não atende esse procedimento/horário. Escolha um horário disponível.');
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
                'sala'=>$sala,'data'=>$data,'hora'=>$hora,'valor'=>$valor,'obs'=>trim((string)($dados['obs'] ?? ''))]);
            $id=(int)$stmt->fetchColumn();
            if ($id<1) throw new RuntimeException('Banco não retornou o número do agendamento.');
            return $id;
        }
        throw new InvalidArgumentException('Horário ocupado por outro paciente, dentista ou sala. Escolha outro.');
    });
}

function alterarStatusAgenda(int $id, string $status): void {
    $u=exigirPerfil(['recepcionista','dentista','cliente']);
    transacaoOperacao(function(PDO $pdo) use($u,$id,$status) {
        $stmt=$pdo->prepare('SELECT * FROM agendamentos WHERE id=:id FOR UPDATE');$stmt->execute(['id'=>$id]);$a=$stmt->fetch();
        if (!$a) throw new InvalidArgumentException('Consulta não encontrada.');
        if ($u['tipo']==='cliente') {
            $p=pacienteSessao();
            if (!$p || (int)$p['id']!==(int)$a['id_paciente'] || $status!=='Cancelado' || $a['data']<date('Y-m-d')) throw new RuntimeException('Cancelamento não permitido.');
        } elseif ($u['tipo']==='dentista') {
            $d=dentistaSessao();if (!$d || (int)$d['id']!==(int)$a['id_dentista']) throw new RuntimeException('Consulta de outro dentista.');
        }
        $transicoes=['Agendado'=>['Confirmado','Aguardando','Cancelado'],'Confirmado'=>['Aguardando','Cancelado'],
            'Aguardando'=>['Em atendimento','Cancelado'],'Em atendimento'=>['Concluída']];
        if (!in_array($status,$transicoes[$a['status']] ?? [],true)) throw new InvalidArgumentException('Mudança de status não permitida.');
        if ($u['tipo']==='cliente' && !in_array($a['status'],['Agendado','Confirmado'],true)) throw new RuntimeException('A consulta já está em atendimento.');
        if ($status==='Cancelado') {
            $stmt=$pdo->prepare('SELECT 1 FROM pagamento WHERE id_agendamento=:id AND pago_em IS NOT NULL');$stmt->execute(['id'=>$id]);
            if ($stmt->fetchColumn()) throw new InvalidArgumentException('Consulta paga: regularize o pagamento antes de cancelar.');
        }
        $pdo->prepare('UPDATE agendamentos SET status=:status WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);
    });
}

function confirmarPagamento(int $id, int $forma): void {
    exigirPerfil(['recepcionista','dono']);
    transacaoOperacao(function(PDO $pdo) use($id,$forma) {
        $stmt=$pdo->prepare('SELECT id FROM forma_pagamento WHERE id=:id');
        $stmt->execute(['id'=>$forma]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Forma de pagamento inválida.');
        $stmt=$pdo->prepare('SELECT id,valor,status FROM agendamentos WHERE id=:id FOR UPDATE');
        $stmt->execute(['id'=>$id]);
        $ag=$stmt->fetch();
        if (!$ag || $ag['status']==='Cancelado') throw new InvalidArgumentException('Consulta inexistente ou cancelada.');
        $stmt=$pdo->prepare('INSERT INTO pagamento (id_agendamento,id_forma_pagamento,valor,pago_em)
            VALUES (:id,:forma,:valor,now()) ON CONFLICT (id_agendamento)
            DO UPDATE SET id_forma_pagamento=EXCLUDED.id_forma_pagamento,valor=EXCLUDED.valor,pago_em=now()');
        $stmt->execute(['id'=>$id,'forma'=>$forma,'valor'=>$ag['valor']]);
    });
}

function registrarLancamento(array $dados): int {
    $u=exigirPerfil(['dono']); $valor=valorMonetario($dados['valor'] ?? '0');
    if ((float)$valor<=0 || !trim($dados['descricao'] ?? '')) throw new InvalidArgumentException('Informe descrição e valor positivo.');
    $pdo=\App\Config\Database::getConnection();
    $categoria=(int)($dados['id_categoria'] ?? 0);$tipo=$dados['tipo'] ?? '';
    if (!in_array($tipo,['Receita','Despesa'],true)) throw new InvalidArgumentException('Tipo de lançamento inválido.');
    if (!$categoria) {
        $stmt=$pdo->prepare('SELECT id FROM categoria_lancamento WHERE tipo=:tipo ORDER BY id LIMIT 1');$stmt->execute(['tipo'=>$tipo]);$categoria=(int)$stmt->fetchColumn();
    }
    $stmt=$pdo->prepare('SELECT 1 FROM categoria_lancamento WHERE id=:id AND tipo=:tipo');
    $stmt->execute(['id'=>$categoria,'tipo'=>$tipo]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('A categoria deve corresponder ao tipo de lançamento.');
    $data=$dados['data'] ?? date('Y-m-d');
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$data);
    if (!$dt || $dt->format('Y-m-d')!==$data || $data>date('Y-m-d')) throw new InvalidArgumentException('Informe uma data válida até hoje.');
    $stmt=$pdo->prepare('INSERT INTO lancamento_financeiro (data,tipo,descricao,valor,id_categoria,id_usuario)
        VALUES (:data,:tipo,:descricao,:valor,:categoria,:usuario) RETURNING id');
    $stmt->execute(['data'=>$data,'tipo'=>$tipo,'descricao'=>trim($dados['descricao']),'valor'=>$valor,'categoria'=>$categoria,'usuario'=>$u['id']]);
    return (int)$stmt->fetchColumn();
}

function salvarCadastroPaciente(array $dados): int {
    exigirPerfil(['recepcionista','dono']);
    $id=!empty($dados['id']) ? (int)$dados['id'] : null;
    if ($id) {
        $p=buscarPorCampo(lerJson('pacientes.json'),'id',$id);
        if (!$p) throw new InvalidArgumentException('Paciente não encontrado.');
        $dados=array_merge($p,$dados);
    }
    if (!trim($dados['nome'] ?? '') || !soDigitos($dados['cpf'] ?? '')) throw new InvalidArgumentException('Nome e CPF são obrigatórios.');
    if (!salvarJson('pacientes.json',[$dados])) throw new InvalidArgumentException('Confira CPF e os dados do paciente.');
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare('SELECT pac.id FROM pacientes pac JOIN pessoa p ON p.id=pac.id_pessoa WHERE p.cpf=:cpf');$stmt->execute(['cpf'=>soDigitos($dados['cpf'])]);
    $id=(int)$stmt->fetchColumn();
    $pdo->prepare('INSERT INTO prontuarios (id_paciente) VALUES (:id) ON CONFLICT (id_paciente) DO NOTHING')->execute(['id'=>$id]);
    return $id;
}

function registrarEvolucao(array $dados): int {
    exigirPerfil(['dentista']); $d=dentistaSessao();
    if (!$d) throw new RuntimeException('Cadastro de dentista ausente.');
    $p=localizarPaciente((string)($dados['paciente'] ?? ''));pacientePermitido((int)$p['id']);
    $obs=trim($dados['observacoes'] ?? $dados['obs'] ?? '');
    if ($obs==='') throw new InvalidArgumentException('Descreva a evolução clínica.');
    return transacaoOperacao(function(PDO $pdo) use($dados,$d,$p,$obs) {
        $stmt=$pdo->prepare('SELECT id FROM procedimentos WHERE nome=:nome');$stmt->execute(['nome'=>$dados['procedimento'] ?? '']);$proc=$stmt->fetchColumn();
        if (!$proc || !in_array($dados['procedimento'],$d['procedimentos'],true)) throw new InvalidArgumentException('Procedimento não habilitado para o dentista.');
        $stmt=$pdo->prepare('INSERT INTO prontuarios (id_paciente) VALUES (:id) ON CONFLICT (id_paciente) DO UPDATE SET atualizado_em=now() RETURNING id');
        $stmt->execute(['id'=>$p['id']]);$pr=$stmt->fetchColumn();
        $retorno=trim($dados['retorno'] ?? '') ?: null;
        if ($retorno && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$retorno) || $retorno<date('Y-m-d'))) throw new InvalidArgumentException('Retorno inválido.');
        // Vincula a evolução à consulta mais recente compatível, quando ela existe.
        // Atendimentos avulsos continuam permitidos sem agendamento.
        $stmt=$pdo->prepare("SELECT a.id FROM agendamentos a WHERE a.id_paciente=:paciente
            AND a.id_dentista=:dentista AND a.id_procedimento=:procedimento
            AND a.data<=CURRENT_DATE AND a.status<>'Cancelado'
            AND NOT EXISTS (SELECT 1 FROM evolucao_clinica e WHERE e.id_agendamento=a.id)
            ORDER BY a.data DESC,a.hora DESC LIMIT 1 FOR UPDATE OF a");
        $stmt->execute(['paciente'=>$p['id'],'dentista'=>$d['id'],'procedimento'=>$proc]);
        $consulta=$stmt->fetchColumn() ?: null;
        $stmt=$pdo->prepare('INSERT INTO evolucao_clinica (id_prontuario,id_dentista,id_procedimento,id_agendamento,observacoes,retorno)
            VALUES (:pr,:dent,:proc,:ag,:obs,:retorno) RETURNING id');
        $stmt->execute(['pr'=>$pr,'dent'=>$d['id'],'proc'=>$proc,'ag'=>$consulta,'obs'=>$obs,'retorno'=>$retorno]);
        $id=(int)$stmt->fetchColumn();
        if ($consulta) $pdo->prepare("UPDATE agendamentos SET status='Concluída' WHERE id=:id AND status<>'Concluída'")
            ->execute(['id'=>$consulta]);
        foreach (array_unique(preg_split('/[\s,;]+/',trim($dados['dentes'] ?? ''),-1,PREG_SPLIT_NO_EMPTY)) as $numero) {
            if (!ctype_digit($numero)) throw new InvalidArgumentException('Use números FDI nos dentes.');
            $stmt=$pdo->prepare("INSERT INTO odontograma_dentes (id_prontuario,numero_dente,status,notas) VALUES (:pr,:num,'tratado',:obs)
                ON CONFLICT (id_prontuario,numero_dente) DO UPDATE SET status=EXCLUDED.status,notas=EXCLUDED.notas RETURNING id");
            $stmt->execute(['pr'=>$pr,'num'=>$numero,'obs'=>$obs]);$dente=$stmt->fetchColumn();
            $pdo->prepare('INSERT INTO evolucao_dente VALUES (:ev,:dent)')->execute(['ev'=>$id,'dent'=>$dente]);
            $pdo->prepare('INSERT INTO odontograma_procedimentos (id_dente,descricao) VALUES (:id,:descricao)')->execute(['id'=>$dente,'descricao'=>$dados['procedimento']]);
        }
        return $id;
    });
}

function salvarOdontograma(int $paciente, array $dentes): void {
    exigirPerfil(['dentista']);pacientePermitido($paciente);
    transacaoOperacao(function(PDO $pdo) use($paciente,$dentes) {
        $stmt=$pdo->prepare('INSERT INTO prontuarios (id_paciente) VALUES (:id) ON CONFLICT (id_paciente) DO UPDATE SET atualizado_em=now() RETURNING id');$stmt->execute(['id'=>$paciente]);$pr=$stmt->fetchColumn();
        foreach ($dentes as $num=>$info) {
            $stmt=$pdo->prepare('INSERT INTO odontograma_dentes (id_prontuario,numero_dente,status,notas) VALUES (:pr,:num,:status,:notas)
                ON CONFLICT (id_prontuario,numero_dente) DO UPDATE SET status=EXCLUDED.status,notas=EXCLUDED.notas');
            $stmt->execute(['pr'=>$pr,'num'=>$num,'status'=>$info['status'] ?? 'saudavel','notas'=>$info['notas'] ?? null]);
        }
    });
}

function emitirRecuperacao(int $usuario): string {
    exigirPerfil(['dono']);$token=bin2hex(random_bytes(32));
    $pdo=\App\Config\Database::getConnection();
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

function redefinirSenha(string $token, string $senha): void {
    if (strlen($senha)<6) throw new InvalidArgumentException('A senha deve ter pelo menos seis caracteres.');
    transacaoOperacao(function(PDO $pdo) use($token,$senha) {
        $stmt=$pdo->prepare('SELECT id_usuario FROM recuperacao_senha WHERE token_hash=:hash AND expira_em>now() AND utilizado_em IS NULL FOR UPDATE');
        $stmt->execute(['hash'=>hash('sha256',$token)]);$id=$stmt->fetchColumn();
        if (!$id) throw new InvalidArgumentException('Link inválido, expirado ou já utilizado.');
        $pdo->prepare('UPDATE usuarios SET senha_hash=:senha,tentativas_login=0,bloqueado_ate=NULL WHERE id=:id')->execute(['senha'=>password_hash($senha,PASSWORD_BCRYPT),'id'=>$id]);
        $pdo->prepare('UPDATE recuperacao_senha SET utilizado_em=now() WHERE id_usuario=:id AND utilizado_em IS NULL')->execute(['id'=>$id]);
    });
}

function cadastrarDentista(array $dados): int {
    exigirPerfil(['dono']);
    if (!trim($dados['nome'] ?? '') || !filter_var($dados['email'] ?? '',FILTER_VALIDATE_EMAIL) || !soDigitos($dados['cpf'] ?? '')) throw new InvalidArgumentException('Informe nome, CPF e e-mail válidos.');
    if (!preg_match('/^CRO-([A-Z]{2})\s+(\d+)$/',$dados['cro'] ?? '',$cro)) throw new InvalidArgumentException('Use o formato CRO-MS 12345.');
    return transacaoOperacao(function(PDO $pdo) use($dados,$cro) {
        $pessoa=gravarPessoa($pdo,$dados,null);
        $stmt=$pdo->prepare("INSERT INTO usuarios (id_pessoa,email,senha_hash,tipo) VALUES (:pessoa,:email,:senha,'dentista')");
        $stmt->execute(['pessoa'=>$pessoa,'email'=>$dados['email'],'senha'=>password_hash('123456',PASSWORD_BCRYPT)]);
        $esp=referenciaPorNome($pdo,'especialidade',$dados['especialidade']);
        $sala=referenciaPorNome($pdo,'sala',$dados['sala'] ?? 'Sala 1');
        $id=gravarCampos($pdo,'dentistas',['id_pessoa'=>$pessoa,'id_especialidade'=>$esp,'id_sala'=>$sala,'cro_uf'=>$cro[1],'cro_numero'=>$cro[2],'admissao'=>$dados['admissao'] ?: date('Y-m-d')]);
        if (empty($dados['procedimentos'])) throw new InvalidArgumentException('Selecione os procedimentos habilitados.');
        $stmt=$pdo->prepare('INSERT INTO dentista_procedimentos (id_dentista,id_procedimento) SELECT :id,id FROM procedimentos WHERE nome=:nome');
        foreach (array_unique($dados['procedimentos']) as $proc) $stmt->execute(['id'=>$id,'nome'=>$proc]);
        $stmt=$pdo->prepare("INSERT INTO disponibilidade_dentista (id_dentista,id_dia_semana,hora)
            SELECT :id,fc.id_dia_semana,slots.hora::time FROM funcionamento_clinica fc
            CROSS JOIN LATERAL generate_series(date '2000-01-01'+fc.abertura,
                date '2000-01-01'+fc.fechamento-interval '30 minutes', interval '30 minutes') AS slots(hora)
            WHERE fc.id_clinica=1 ON CONFLICT DO NOTHING");
        $stmt->execute(['id'=>$id]);
        return $id;
    });
}

function salvarPerfilAtual(array $dados): void {
    $u=usuarioSessao();
    $nome=trim($dados['nome'] ?? '');
    if ($nome==='') throw new InvalidArgumentException('Informe o nome.');
    transacaoOperacao(function(PDO $pdo) use ($u,$nome,$dados) {
        $fonte=match($u['tipo']) {'cliente'=>'pacientes.json','dentista'=>'dentistas.json',default=>'usuarios.json'};
        $registro=buscarPorCampo(lerJson($fonte),$fonte==='usuarios.json'?'id':'id_usuario',$u['id']);
        if (!$registro) throw new RuntimeException('Perfil não cadastrado.');
        $permitidos=match($u['tipo']) {
            'cliente'=>['nome','telefone','nascimento','plano','alergias','observacoes'],
            'dentista'=>['nome','telefone','nascimento','titulacao','faculdade'],
            default=>['nome','telefone','nascimento']
        };
        foreach ($permitidos as $campo) if (array_key_exists($campo,$dados)) $registro[$campo]=trim($dados[$campo]);
        if (!salvarJson($fonte,[$registro])) throw new RuntimeException('Não foi possível salvar o perfil.');
    });
    $_SESSION['usuario']=$nome;
}

function alterarSenhaAtual(array $dados): void {
    $u=usuarioSessao();$pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare('SELECT senha_hash FROM usuarios WHERE id=:id');$stmt->execute(['id'=>$u['id']]);
    if (!password_verify($dados['senha_atual'] ?? '',$stmt->fetchColumn())) throw new InvalidArgumentException('Senha atual incorreta.');
    $nova=$dados['senha_nova'] ?? '';
    if (strlen($nova)<6) throw new InvalidArgumentException('Use pelo menos seis caracteres.');
    if ($nova!==($dados['confirmar_senha_nova'] ?? '')) throw new InvalidArgumentException('As senhas não coincidem.');
    $stmt=$pdo->prepare('UPDATE usuarios SET senha_hash=:senha WHERE id=:id');
    $stmt->execute(['senha'=>password_hash($nova,PASSWORD_BCRYPT),'id'=>$u['id']]);
}
