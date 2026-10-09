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
    $pdo->beginTransaction();
    try { $resultado=$operacao($pdo); $pdo->commit(); return $resultado; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function mensagemOperacao(Throwable $e): string {
    if ($e instanceof PDOException) {
        error_log($e->getMessage());
        return match ($e->getCode()) {
            '23505'=>'Já existe um cadastro ou reserva para esses dados. Escolha outro horário.',
            '23503'=>'O registro possui vínculos ou o procedimento não é habilitado para esse dentista.',
            '23514','22001'=>'Confira CPF, telefone, datas e demais campos informados.',
            default=>'Não foi possível salvar. Confira os dados e tente novamente.',
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
    $stmt=$pdo->prepare('SELECT * FROM vw_pacientes WHERE id::text=:id OR lower(nome)=lower(:nome) OR regexp_replace(cpf,\'[^0-9]\',\'\',\'g\')=:cpf');
    $stmt->execute(['id'=>$termo,'nome'=>$termo,'cpf'=>soDigitos($termo)]);
    $linhas=$stmt->fetchAll();
    if (count($linhas)!==1) throw new InvalidArgumentException('Selecione um paciente pelo ID ou CPF; o nome deve ser único.');
    return $linhas[0];
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

function horariosDisponiveis(int $dentista, string $data): array {
    $pdo=\App\Config\Database::getConnection();
    $stmt=$pdo->prepare("SELECT to_char(dd.hora,'HH24:MI') FROM disponibilidade_dentista dd JOIN dentistas d ON d.id=dd.id_dentista
        WHERE dd.id_dentista=:id AND dd.id_dia_semana=extract(dow FROM CAST(:data AS date))
        AND EXISTS(SELECT 1 FROM funcionamento_clinica f WHERE f.id_clinica=1 AND f.id_dia_semana=dd.id_dia_semana AND dd.hora>=f.abertura AND dd.hora<f.fechamento)
        AND NOT EXISTS (SELECT 1 FROM agendamentos a JOIN procedimentos p ON p.id=a.id_procedimento
            WHERE (a.id_dentista=d.id OR a.id_sala=d.id_sala) AND a.status<>'Cancelado'
            AND CAST(:dia AS date)+dd.hora>=a.data+a.hora AND CAST(:dia2 AS date)+dd.hora<a.data+a.hora+make_interval(mins=>p.duracao_min)) ORDER BY dd.hora");
    $stmt->execute(['id'=>$dentista,'data'=>$data,'dia'=>$data,'dia2'=>$data]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function criarAgendamento(array $dados): int {
    $u=exigirPerfil(['recepcionista','cliente']);
    $paciente=$u['tipo']==='cliente' ? pacienteSessao() : localizarPaciente(trim((string)($dados['paciente_id'] ?? $dados['paciente'] ?? '')));
    if (!$paciente || $paciente['status']!=='Ativo') throw new InvalidArgumentException('Paciente ativo não encontrado.');
    $data=$dados['data'] ?? ''; $hora=$dados['horario'] ?? '';
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$data);
    if (!$dt || $dt->format('Y-m-d')!==$data || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$hora)) throw new InvalidArgumentException('Data ou horário inválido.');
    $c=lerJsonObjeto('clinica.json');
    $minimo=date('Y-m-d',strtotime('+'.($u['tipo']==='cliente' ? (int)$c['antecedencia_min_dias'] : 0).' days'));
    if ($data<$minimo) throw new InvalidArgumentException('A data não respeita a antecedência mínima.');
    return transacaoOperacao(function(PDO $pdo) use($dados,$paciente,$data,$hora,$c) {
        $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:dia))')->execute(['dia'=>'agenda-'.$data]);
        $stmt=$pdo->prepare("SELECT count(*) FROM agendamentos WHERE data=:data AND status<>'Cancelado'");
        $stmt->execute(['data'=>$data]);
        if ((int)$stmt->fetchColumn()>=(int)$c['limite_agendamentos_dia']) throw new InvalidArgumentException('Limite diário de consultas atingido.');
        $stmt=$pdo->prepare('SELECT id,valor_base,duracao_min FROM procedimentos WHERE nome=:nome');
        $stmt->execute(['nome'=>$dados['procedimento'] ?? '']); $proc=$stmt->fetch();
        if (!$proc) throw new InvalidArgumentException('Procedimento inválido.');
        $stmt=$pdo->prepare('SELECT 1 FROM funcionamento_clinica WHERE id_clinica=1 AND id_dia_semana=extract(dow FROM CAST(:dia AS date))
            AND abertura<=CAST(:hora AS time) AND fechamento>=CAST(:fim AS time)+make_interval(mins=>CAST(:duracao AS integer))');
        $stmt->execute(['dia'=>$data,'hora'=>$hora,'fim'=>$hora,'duracao'=>$proc['duracao_min']]);
        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('A consulta deve caber no horário de funcionamento da clínica.');
        $preferencia=explode(' — ',trim($dados['dentista'] ?? ''))[0];
        $stmt=$pdo->prepare("SELECT d.id,d.id_sala FROM dentistas d JOIN pessoa p ON p.id=d.id_pessoa
            JOIN dentista_procedimentos dp ON dp.id_dentista=d.id
            WHERE d.status='ativo' AND dp.id_procedimento=:proc AND
            (:vazio='' OR d.id::text=:id OR p.nome=:nome)
            AND EXISTS (SELECT 1 FROM disponibilidade_dentista dd WHERE dd.id_dentista=d.id
                AND dd.id_dia_semana=extract(dow FROM CAST(:data AS date)) AND dd.hora=CAST(:hora AS time)) ORDER BY d.id");
        $stmt->execute(['proc'=>$proc['id'],'vazio'=>$preferencia,'id'=>$preferencia,'nome'=>$preferencia,'data'=>$data,'hora'=>$hora]);
        $dentistas=$stmt->fetchAll();
        if (!$dentistas) throw new InvalidArgumentException('Dentista sem disponibilidade ou não habilitado para este procedimento.');
        $valor=trim($dados['valor'] ?? '')!=='' ? valorMonetario($dados['valor']) : $proc['valor_base'];
        foreach ($dentistas as $d) {
            $sala=$d['id_sala'];
            if (!empty($dados['sala'])) {
                $stmt=$pdo->prepare('SELECT id FROM sala WHERE nome=:nome OR id::text=:id');
                $stmt->execute(['nome'=>$dados['sala'],'id'=>$dados['sala']]); $sala=$stmt->fetchColumn();
            }
            if (!$sala) throw new InvalidArgumentException('Sala inválida.');
            $pdo->exec('SAVEPOINT reserva');
            try {
                $stmt=$pdo->prepare("INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor,observacoes)
                    VALUES (:pac,:dent,:proc,:sala,:data,:hora,:valor,:obs) RETURNING id");
                $stmt->execute(['pac'=>$paciente['id'],'dent'=>$d['id'],'proc'=>$proc['id'],'sala'=>$sala,'data'=>$data,'hora'=>$hora,'valor'=>$valor,'obs'=>trim($dados['obs'] ?? '')]);
                return (int)$stmt->fetchColumn();
            } catch (PDOException $e) {
                $pdo->exec('ROLLBACK TO SAVEPOINT reserva');
                if ($e->getCode()!=='23505' || $preferencia!=='') throw $e;
            }
        }
        throw new InvalidArgumentException('Nenhum dentista livre nesse intervalo. Escolha outro horário.');
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
    exigirPerfil(['dono','recepcionista']);
    transacaoOperacao(function(PDO $pdo) use($id,$forma) {
        $stmt=$pdo->prepare('SELECT * FROM agendamentos WHERE id=:id FOR UPDATE');$stmt->execute(['id'=>$id]);$a=$stmt->fetch();
        if (!$a || $a['status']==='Cancelado') throw new InvalidArgumentException('Consulta não disponível para pagamento.');
        $stmt=$pdo->prepare('SELECT 1 FROM pagamento WHERE id_agendamento=:id AND pago_em IS NOT NULL');$stmt->execute(['id'=>$id]);
        if ($stmt->fetchColumn()) throw new InvalidArgumentException('Pagamento já confirmado.');
        $stmt=$pdo->prepare('INSERT INTO pagamento (id_agendamento,id_forma_pagamento,valor,pago_em)
            VALUES (:id,:forma,:valor,now()) ON CONFLICT (id_agendamento) DO UPDATE SET
            id_forma_pagamento=EXCLUDED.id_forma_pagamento,valor=EXCLUDED.valor,pago_em=EXCLUDED.pago_em');
        $stmt->execute(['id'=>$id,'forma'=>$forma,'valor'=>$a['valor']]);
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
        $stmt=$pdo->prepare('INSERT INTO evolucao_clinica (id_prontuario,id_dentista,id_procedimento,observacoes,retorno)
            VALUES (:pr,:dent,:proc,:obs,:retorno) RETURNING id');$stmt->execute(['pr'=>$pr,'dent'=>$d['id'],'proc'=>$proc,'obs'=>$obs,'retorno'=>$retorno]);$id=(int)$stmt->fetchColumn();
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
        for ($dia=1;$dia<=5;$dia++) foreach (['09:00','14:00'] as $hora) {
            $pdo->prepare('INSERT INTO disponibilidade_dentista VALUES (:id,:dia,:hora)')->execute(['id'=>$id,'dia'=>$dia,'hora'=>$hora]);
        }
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
