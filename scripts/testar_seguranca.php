<?php
/** Testes dos itens corrigidos na banca de revisão e do Cadastre-se do paciente. Tudo com ROLLBACK. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start(); // a sessão precisa trocar de ID sem "headers already sent"
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['REMOTE_ADDR']='203.0.113.'.random_int(1,250); // origem fictícia (faixa de documentação), só para o limite por IP
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
require_once dirname(__DIR__).'/includes/functions.php';
$pdo=\App\Config\Database::getConnection();
function ok(bool $val, string $label): void { if (!$val) throw new RuntimeException('FALHOU: '.$label); echo '[OK] '.$label."\n"; }
function falha(callable $f, string $label, ?string $contem=null): void {
    try { $f(); } catch (InvalidArgumentException|RuntimeException $e) {
        if ($contem!==null && !str_contains($e->getMessage(),$contem)) throw new RuntimeException('FALHOU: '.$label.' (mensagem: '.$e->getMessage().')');
        ok(true,$label.' ('.$e->getMessage().')'); return;
    }
    throw new RuntimeException('FALHOU: '.$label.' (operação foi aceita)');
}
function cpfTeste(): string {
    do {
        $n=array_map('intval',str_split(str_pad((string)random_int(100000000,999999999),9,'0',STR_PAD_LEFT)));
        foreach ([10,11] as $peso) { $s=0; foreach ($n as $i=>$d) $s+=$d*($peso-$i); $n[]=(($s*10)%11)%10; }
        $cpf=implode('',$n);
    } while (!cpfValido($cpf));
    return $cpf;
}
function como(string $perfil, string $email): void { $_SESSION['perfil']=$perfil; $_SESSION['email']=$email; }

$retrato=fn()=>$pdo->query("SELECT (SELECT count(*) FROM agendamentos)||'|'||(SELECT count(*) FROM pessoa)||'|'||(SELECT count(*) FROM usuarios)||'|'||
    (SELECT md5(string_agg(id||nome||cpf,',' ORDER BY id)) FROM pessoa)||'|'||(SELECT md5(string_agg(id||status::text||data||hora,',' ORDER BY id)) FROM agendamentos)")->fetchColumn();
$antes=$retrato();
try {
    $pdo->beginTransaction();

    // ---- validadores ----
    ok(cpfValido('529.982.247-25') && !cpfValido('11111111111') && !cpfValido('52998224726'),'CPF: dígitos verificadores conferidos');
    ok(denteValido(11) && denteValido(48) && denteValido(85) && !denteValido(19) && !denteValido(99) && !denteValido(0),'dente: numeração FDI');
    ok(valorMonetario('1.500')==='1500.00' && valorMonetario('1.500,50')==='1500.50' && valorMonetario('150,5')==='150.50','valor: aceita "1.500" e "1.500,50"');
    falha(fn()=>valorMonetario('99999999999'),'valor acima do máximo é recusado','máximo');
    falha(fn()=>validarNovaSenha('😀😀'),'senha conta caracteres, não bytes (2 emojis não passam)');

    // ---- mensagens sem detalhe técnico ----
    $m=mensagemOperacao(new TypeError('trim(): Argument #1 must be of type string, called in C:\\x\\arquivo.php on line 9'));
    ok(!str_contains($m,'arquivo.php') && str_contains($m,'código'),'erro interno (TypeError) não mostra caminho nem código-fonte');
    $e=new class('numeric field overflow') extends PDOException { protected $code='22003'; };
    ok(!str_contains(mensagemOperacao($e),'SQLSTATE') && !str_contains(mensagemOperacao($e),'22003'),'erro do banco (22003) não mostra SQLSTATE');

    // ---- recepção não reescreve a pessoa de outro usuário (crítico da banca) ----
    como('recepcionista','recepcao@dentai.com');
    $dono=$pdo->query("SELECT p.id,p.nome,p.cpf FROM usuarios u JOIN pessoa p ON p.id=u.id_pessoa WHERE u.tipo='dono' LIMIT 1")->fetch();
    $idDono=(int)$pdo->query("SELECT id FROM usuarios WHERE tipo='dono' LIMIT 1")->fetchColumn();
    $novoPac=salvarCadastroPaciente(['nome'=>'Paciente Teste Banca','cpf'=>cpfTeste(),'id_usuario'=>(string)$idDono,'senha'=>'x','tipo'=>'dono']);
    $depois=$pdo->prepare('SELECT nome,cpf FROM pessoa WHERE id=:id'); $depois->execute(['id'=>$dono['id']]); $depois=$depois->fetch();
    ok($depois['nome']===$dono['nome'] && $depois['cpf']===$dono['cpf'],'id_usuario no POST não altera a pessoa do dono');
    $outra=$pdo->prepare('SELECT p.id FROM pacientes pac JOIN pessoa p ON p.id=pac.id_pessoa WHERE pac.id=:id'); $outra->execute(['id'=>$novoPac]);
    ok((int)$outra->fetchColumn()!==(int)$dono['id'],'o paciente novo ganhou uma pessoa própria');
    falha(fn()=>salvarCadastroPaciente(['nome'=>'Telefone Ruim','cpf'=>cpfTeste(),'telefone'=>'abcdef']),'telefone inválido é recusado (antes era descartado em silêncio)','Telefone');
    falha(fn()=>salvarCadastroPaciente(['nome'=>'Nascimento Ruim','cpf'=>cpfTeste(),'nascimento'=>'0001-01-01']),'nascimento no ano 1 é recusado','nascimento');
    falha(fn()=>salvarCadastroPaciente(['nome'=>'Plano Inventado','cpf'=>cpfTeste(),'plano'=>'Plano Inexistente '.bin2hex(random_bytes(3))]),'plano digitado que não existe não é criado','Plano');
    falha(fn()=>salvarCadastroPaciente(['nome'=>'Email Duplicado','cpf'=>cpfTeste(),'email'=>'dentista@dentai.com']),'e-mail de acesso de outra pessoa é recusado','e-mail');
    falha(fn()=>salvarCadastroPaciente(['nome'=>'CPF Ruim','cpf'=>'123']),'CPF inválido é recusado','CPF');

    // ---- datas absurdas ----
    falha(fn()=>criarAgendamento(['paciente_id'=>$novoPac,'procedimento'=>'Limpeza Dental','data'=>'9999-12-31','horario'=>'08:00']),'consulta em 9999 é recusada','Data da consulta');
    falha(fn()=>criarAgendamento(['paciente_id'=>$novoPac,'procedimento'=>'Limpeza Dental','data'=>'2026-02-31','horario'=>'08:00']),'data 31/02 é recusada como inexistente','calendário');
    como('dono','dono@dentai.com');
    falha(fn()=>registrarLancamento(['tipo'=>'Despesa','descricao'=>'Teste','valor'=>'10,00','data'=>'0001-01-01']),'lançamento no ano 1 é recusado','lançamento');
    falha(fn()=>resumoFinanceiro($pdo,'0001-01-01'),'faturamento do ano 1 é recusado sem erro do banco','Período');

    // ---- pagamento não é reescrito ----
    como('recepcionista','recepcao@dentai.com');
    $aberta=$pdo->query("SELECT a.id FROM agendamentos a LEFT JOIN pagamento pg ON pg.id_agendamento=a.id
        WHERE a.status IN ('Agendado','Confirmado','Concluída') AND pg.pago_em IS NULL ORDER BY a.id LIMIT 1")->fetchColumn();
    $forma=(int)$pdo->query('SELECT id FROM forma_pagamento ORDER BY id LIMIT 1')->fetchColumn();
    confirmarPagamento((int)$aberta,$forma);
    falha(fn()=>confirmarPagamento((int)$aberta,$forma),'segundo recebimento da mesma consulta é recusado','já foi paga');

    // ---- paciente não cancela depois do horário; dentista não cancela ----
    $cliente=$pdo->query("SELECT pac.id FROM usuarios u JOIN pacientes pac ON pac.id_pessoa=u.id_pessoa WHERE u.email='cliente@gmail.com'")->fetchColumn();
    $dentista=$pdo->query("SELECT d.id,d.id_sala FROM usuarios u JOIN dentistas d ON d.id_pessoa=u.id_pessoa WHERE u.email='dentista@dentai.com'")->fetch();
    $proc=$pdo->prepare('SELECT dp.id_procedimento FROM dentista_procedimentos dp JOIN procedimentos p ON p.id=dp.id_procedimento WHERE dp.id_dentista=:d ORDER BY p.duracao_min LIMIT 1'); $proc->execute(['d'=>$dentista['id']]); $proc=(int)$proc->fetchColumn();
    // Consulta de hoje que já começou: procura um horário passado sem conflito (a trigger recusa sobreposição).
    $hoje=$pdo->prepare("INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor) VALUES (:p,:d,:pr,:s,CURRENT_DATE,:h,100) RETURNING id");
    $idHoje=0;
    for ($min=0; !$idHoje && $min < (int)date('G')*60+(int)date('i'); $min+=5) {
        $pdo->exec('SAVEPOINT tenta_horario');
        try {
            $hoje->execute(['p'=>$cliente,'d'=>$dentista['id'],'pr'=>$proc,'s'=>$dentista['id_sala'],'h'=>sprintf('%02d:%02d',intdiv($min,60),$min%60)]);
            $idHoje=(int)$hoje->fetchColumn();
            $pdo->exec('RELEASE SAVEPOINT tenta_horario');
        } catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT tenta_horario'); }
    }
    ok($idHoje>0,'consulta de hoje, já iniciada, para os testes');
    como('cliente','cliente@gmail.com');
    falha(fn()=>alterarStatusAgenda($idHoje,'Cancelado'),'paciente não cancela a consulta depois do horário','recepção');
    como('dentista','dentista@dentai.com');
    falha(fn()=>alterarStatusAgenda($idHoje,'Cancelado'),'dentista não cancela consulta','recepção');

    // ---- dentista inativado perde o acesso; com consulta futura, não inativa ----
    como('dono','dono@dentai.com');
    falha(fn()=>definirStatusDentista((int)$dentista['id'],false),'dentista com consulta de hoje ou futura não é inativado','Remarque');
    $pdo->prepare("UPDATE agendamentos SET status='Cancelado' WHERE id_dentista=:d AND data>=CURRENT_DATE")->execute(['d'=>$dentista['id']]); // só dentro da transação de teste
    definirStatusDentista((int)$dentista['id'],false);
    ok(autenticarUsuario('dentista@dentai.com','123456','dentista')===false,'dentista inativado não consegue entrar');
    falha(fn()=>definirStatusDentista(999999,false),'inativar dentista inexistente mostra erro','não encontrado');
    definirStatusDentista((int)$dentista['id'],true); // reativa dentro da transação (o ROLLBACK desfaz tudo)

    // ---- referência digitada não cria sala ----
    falha(fn()=>referenciaPorNome($pdo,'sala','Sala Inexistente '.bin2hex(random_bytes(3))),'sala digitada que não existe não é criada','não está cadastrad');

    // ---- bloqueio de login: depois que vence, a contagem recomeça ----
    $pdo->exec("UPDATE usuarios SET tentativas_login=5, bloqueado_ate=now()-interval '1 minute' WHERE email='recepcao@dentai.com'");
    autenticarUsuario('recepcao@dentai.com','senha-errada','recepcionista');
    $t=$pdo->query("SELECT tentativas_login, bloqueado_ate IS NULL FROM usuarios WHERE email='recepcao@dentai.com'")->fetch(PDO::FETCH_NUM);
    ok((int)$t[0]===1 && booleanoPg($t[1]),'após o bloqueio vencer, 1 erro não bloqueia de novo');
    $pdo->exec("UPDATE usuarios SET tentativas_login=0, bloqueado_ate=NULL WHERE email='recepcao@dentai.com'");

    // ---- prontuário: dente e odontograma inválidos ----
    como('dentista','dentista@dentai.com');
    $pacDentista=$pdo->prepare('SELECT a.id_paciente FROM agendamentos a WHERE a.id_dentista=:d ORDER BY a.id LIMIT 1');
    $pacDentista->execute(['d'=>$dentista['id']]); $pacDentista=(int)$pacDentista->fetchColumn();
    $procDentista=dentistaSessao()['procedimentos'][0];
    falha(fn()=>registrarEvolucao(['paciente'=>(string)$pacDentista,'procedimento'=>$procDentista,'obs'=>'x','dentes'=>'99']),'dente 99 é recusado com mensagem certa','FDI');
    falha(fn()=>registrarEvolucao(['paciente'=>(string)$pacDentista,'procedimento'=>$procDentista,'obs'=>'x','retorno'=>'2030-13-45']),'retorno 2030-13-45 é recusado','retorno');
    falha(fn()=>salvarOdontograma($pacDentista,5),'odontograma que não é lista é recusado','formato');
    falha(fn()=>salvarOdontograma($pacDentista,['11'=>['status'=>'quebrado']]),'situação de dente inválida é recusada','Situação');
    falha(fn()=>pacientePermitido(999999),'paciente de outro dentista ou inexistente: mesma mensagem','Paciente não encontrado');

    // ---- Cadastre-se do paciente ----
    unset($_SESSION['perfil'],$_SESSION['email']);
    $cpfNovo=cpfTeste(); $emailNovo='cadastro.'.$cpfNovo.'@exemplo.com';
    $base=['nome'=>'Paciente Portal Teste','cpf'=>$cpfNovo,'email'=>$emailNovo,'telefone'=>'(67) 99999-1234','nascimento'=>'1990-05-10',
        'senha'=>'segredo1','confirmar_senha'=>'segredo1','aceite'=>'1'];
    falha(fn()=>cadastrarPacientePortal(['aceite'=>'']+$base),'sem aceite dos dados (LGPD) não cadastra','aceite');
    falha(fn()=>cadastrarPacientePortal(['confirmar_senha'=>'outra']+$base),'senhas diferentes não cadastram','coincidem');
    $u=cadastrarPacientePortal($base);
    $linha=$pdo->prepare("SELECT u.tipo::text AS tipo,u.senha_hash,pac.id AS paciente,(SELECT count(*) FROM prontuarios pr WHERE pr.id_paciente=pac.id) AS prontuarios
        FROM usuarios u JOIN pacientes pac ON pac.id_pessoa=u.id_pessoa WHERE u.id=:id");
    $linha->execute(['id'=>$u['id']]); $linha=$linha->fetch();
    ok($linha && $linha['tipo']==='cliente' && (int)$linha['prontuarios']===1,'cadastro cria pessoa, paciente, prontuário e acesso de paciente');
    ok(str_starts_with($linha['senha_hash'],'$2y$') && password_verify('segredo1',$linha['senha_hash']),'senha guardada com bcrypt');
    ok(is_array(autenticarUsuario($emailNovo,'segredo1','cliente')),'o paciente recém-cadastrado entra com e-mail e senha');
    $generica='Não foi possível concluir o cadastro';
    falha(fn()=>cadastrarPacientePortal(['email'=>'outro.'.$emailNovo]+$base),'CPF já cadastrado: resposta genérica',$generica);
    falha(fn()=>cadastrarPacientePortal(['cpf'=>cpfTeste()]+$base),'e-mail já cadastrado: mesma resposta genérica',$generica);
    // Toda tentativa conta para o limite (5 por hora por origem); cada bloco usa uma origem fictícia nova.
    $_SERVER['REMOTE_ADDR']='198.51.100.'.random_int(1,250);
    falha(fn()=>cadastrarPacientePortal(['cpf'=>'111.111.111-11']+$base),'CPF inválido não cadastra','CPF');
    falha(fn()=>cadastrarPacientePortal(['telefone'=>'']+$base),'telefone é obrigatório no cadastro','telefone');
    $_SERVER['REMOTE_ADDR']='192.0.2.'.random_int(1,250);
    for ($i=0; $i<10; $i++) { try { cadastrarPacientePortal(['email'=>'dup'.$i.'.'.$emailNovo]+$base); } catch (InvalidArgumentException $e) {} } // CPF já existe: chega ao banco e conta
    falha(fn()=>cadastrarPacientePortal(['cpf'=>cpfTeste(),'email'=>'x.'.$emailNovo]+$base),'limite: 11ª tentativa de cadastro da mesma origem na hora é recusada','Muitos cadastros');

    // ---- 2ª rodada da banca ----
    unset($_SESSION['perfil'],$_SESSION['email']);
    $_SERVER['REMOTE_ADDR']='203.0.113.'.random_int(1,250);
    falha(fn()=>cadastrarPacientePortal(['cpf'=>cpfTeste(),'email'=>'semtel.'.$emailNovo,'telefone'=>null]+array_diff_key($base,['telefone'=>1])),'Cadastre-se sem o campo telefone no POST é recusado','telefone');
    falha(fn()=>cadastrarPacientePortal(array_diff_key($base,['cpf'=>1])+['email'=>'semcpf.'.$emailNovo]),'Cadastre-se sem o campo CPF no POST é recusado sem warning','CPF');
    $_SERVER['REMOTE_ADDR']='198.51.100.'.random_int(1,250);
    for ($i=0; $i<6; $i++) { try { cadastrarPacientePortal(['confirmar_senha'=>'outra']+$base); } catch (InvalidArgumentException $e) {} }
    $ok=cadastrarPacientePortal(['cpf'=>cpfTeste(),'email'=>'depoisdeerros.'.$emailNovo]+$base);
    ok($ok['id']>0,'erros de digitação no Cadastre-se não gastam a cota da origem');

    $ag=['status'=>'Agendado','data'=>date('Y-m-d'),'hora'=>date('H:i',strtotime('-1 minute'))];
    ok(!consultaFutura($ag),'consulta de hoje cujo horário já passou não é "próxima"');
    ok(consultaFutura(['hora'=>date('H:i',strtotime('+2 minutes'))]+$ag) || date('H:i')>'23:57','consulta de hoje mais tarde é "próxima"');
    ok(consultaFutura(['status'=>'Aguardando']+$ag),'paciente que já fez check-in continua com a consulta em andamento');

    como('dono','dono@dentai.com');
    $pdo->prepare("UPDATE agendamentos SET status='Em atendimento', data=CURRENT_DATE WHERE id=:id")->execute(['id'=>$idHoje]);
    falha(fn()=>definirStatusDentista((int)$dentista['id'],false),'dentista com consulta em atendimento não é inativado','Remarque');
    como('recepcionista','recepcao@dentai.com');
    $pagaHoje=$pdo->prepare("INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor) VALUES (:p,:d,:pr,:s,CURRENT_DATE,:h,80) RETURNING id");
    $idPaga=0;
    for ($min=0; !$idPaga && $min < (int)date('G')*60+(int)date('i'); $min+=5) {
        $pdo->exec('SAVEPOINT tenta_paga');
        try { $pagaHoje->execute(['p'=>$novoPac,'d'=>$dentista['id'],'pr'=>$proc,'s'=>$dentista['id_sala'],'h'=>sprintf('%02d:%02d',intdiv($min,60),$min%60)]); $idPaga=(int)$pagaHoje->fetchColumn(); $pdo->exec('RELEASE SAVEPOINT tenta_paga'); }
        catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT tenta_paga'); }
    }
    confirmarPagamento($idPaga,$forma);
    falha(fn()=>alterarStatusAgenda($idPaga,'Faltou'),'consulta paga não recebe falta sem antes estornar','Estornar');
    falha(fn()=>estornarPagamento($idPaga,'   '),'estorno exige motivo','motivo');
    estornarPagamento($idPaga,'Paciente não compareceu');
    $est=$pdo->prepare('SELECT count(*) FROM pagamento_estorno WHERE id_agendamento=:id AND motivo=:m'); $est->execute(['id'=>$idPaga,'m'=>'Paciente não compareceu']);
    ok((int)$est->fetchColumn()===1,'estorno guarda o pagamento original, quem estornou e o motivo');
    alterarStatusAgenda($idPaga,'Faltou');
    $st=$pdo->prepare('SELECT status::text FROM agendamentos WHERE id=:id'); $st->execute(['id'=>$idPaga]);
    ok($st->fetchColumn()==='Faltou','depois do estorno, a falta pode ser registrada');
    falha(fn()=>estornarPagamento($idPaga,'de novo'),'estornar consulta sem pagamento é recusado','nada foi estornado');
    como('cliente','cliente@gmail.com');
    falha(fn()=>estornarPagamento($idPaga,'x'),'paciente não estorna pagamento','Acesso');
    $est=estornosDoPeriodo($pdo,date('Y-m-01'),date('Y-m-d',strtotime('first day of next month')));
    ok((bool)array_filter($est,fn($e)=>(int)$e['id_agendamento']===$idPaga),'estorno aparece para o gestor no Faturamento do mês');
    como('recepcionista','recepcao@dentai.com');
    $antigo=$pdo->query("SELECT a.id FROM agendamentos a JOIN pagamento p ON p.id_agendamento=a.id
        WHERE p.pago_em < date_trunc('month', now()) ORDER BY p.pago_em DESC LIMIT 1")->fetchColumn();
    if ($antigo) {
        // Estorno de pagamento de mês anterior: a consulta destrava, a receita do mês original não muda
        // e a devolução entra como saída no mês atual (migração 010).
        $mesPago=$pdo->prepare("SELECT to_char(p.pago_em,'YYYY-MM-01') FROM pagamento p WHERE p.id_agendamento=:id"); $mesPago->execute(['id'=>$antigo]); $mesPago=$mesPago->fetchColumn();
        $receitaAntes=resumoFinanceiro($pdo,$mesPago)['mes_atual']['receita_total'];
        $despesaAtualAntes=resumoFinanceiro($pdo,date('Y-m-d'))['mes_atual']['despesas_total'];
        $valor=(float)$pdo->query("SELECT valor FROM pagamento WHERE id_agendamento=".(int)$antigo)->fetchColumn();
        estornarPagamento((int)$antigo,'Devolução de mês anterior (teste)');
        ok(abs(resumoFinanceiro($pdo,$mesPago)['mes_atual']['receita_total']-$receitaAntes)<0.001,'estorno de mês já fechado não muda a receita daquele mês');
        ok(abs(resumoFinanceiro($pdo,date('Y-m-d'))['mes_atual']['despesas_total']-$despesaAtualAntes-$valor)<0.001,'a devolução aparece como saída no mês do estorno');
    }
    ok(cnpjValido('12.ABC.345/01DE-35') && cnpjValido('11.222.333/0001-81') && !cnpjValido('12.ABC.345/01DE-34'),'CNPJ numérico e alfanumérico (IN RFB 2.229/2024)');
    como('recepcionista','recepcao@dentai.com');
    falha(fn()=>salvarCadastroPaciente(['id'=>'1e1','nome'=>'Nao Pode Editar']),'id "1e1" não edita outro paciente','não encontrado');
    ok(!consultaFutura(['status'=>'Aguardando','data'=>emDias(-3),'hora'=>'08:00']),'"Aguardando" de dia passado não aparece como próxima consulta');
    como('cliente','cliente@gmail.com');
    $deOutro=$pdo->query("SELECT a.id FROM agendamentos a WHERE a.id_paciente<>(SELECT pac.id FROM pacientes pac JOIN usuarios u ON u.id_pessoa=pac.id_pessoa WHERE u.email='cliente@gmail.com') LIMIT 1")->fetchColumn();
    falha(fn()=>alterarStatusAgenda((int)$deOutro,'Cancelado'),'consulta de outro paciente: mesma resposta de "não encontrada"','não encontrada');

    como('dono','dono@dentai.com');
    falha(fn()=>salvarFuncionamento([1=>'18:00'],[1=>'08:00']),'horário de funcionamento com abertura depois do fechamento é recusado','abertura');
    falha(fn()=>salvarConfiguracoes(['nome'=>'']+lerJsonObjeto('clinica.json')),'nome da clínica vazio é recusado','nome');
    $pdo->exec('UPDATE clinica SET limite_agendamentos_dia=1 WHERE id=1'); // hoje já tem consultas de teste
    falha(fn()=>horariosLivres(hoje(),'Limpeza Dental'),'horários livres respeitam o limite de consultas do dia','limite');
    $pdo->exec('UPDATE clinica SET limite_agendamentos_dia=500 WHERE id=1');
    $_SESSION['perfil']='recepcionista'; $_SESSION['email']='recepcao@dentai.com';
    carregarListas();
    ok(count(array_filter($GLOBALS['listaPacientes'],fn($p)=>$p['ultimaConsulta']!=='—'))>0,'"última consulta" aparece para a recepção');

    como('recepcionista','recepcao@dentai.com');
    // Salvar a ficha sem mudar o e-mail não pode trocar o login (no seed, contato e login são diferentes).
    $maria=$pdo->query("SELECT pac.id, u.email FROM usuarios u JOIN pacientes pac ON pac.id_pessoa=u.id_pessoa WHERE u.email='cliente@gmail.com'")->fetch();
    if ($maria) {
        salvarCadastroPaciente(['id'=>(string)$maria['id']]);
        $login=$pdo->prepare("SELECT u.email FROM usuarios u JOIN pacientes pac ON pac.id_pessoa=u.id_pessoa WHERE pac.id=:id AND u.tipo='cliente'");
        $login->execute(['id'=>$maria['id']]);
        ok($login->fetchColumn()==='cliente@gmail.com','salvar a ficha sem mudar o e-mail mantém o login do portal');
    }
    $idForm0=salvarCadastroPaciente(['id'=>'0','nome'=>'Paciente Do Formulario','cpf'=>cpfTeste()]);
    ok($idForm0>0,'cadastro de paciente novo com id=0 (como a tela envia) cria o paciente');
    ok(cnpjValido('11.222.333/0001-81') && !cnpjValido('11.111.111/1111-11'),'CNPJ: dígitos verificadores conferidos');
    ok(idParametro('99999999999999')===0 && idParametro('12')===12 && idParametro('abc')===0,'id gigante ou texto na URL vira "não encontrado", sem erro do banco');
    ok(semAcento('JOÃO Conceição')==='joao conceicao','busca sem diferenciar acento');
    como('dono','dono@dentai.com');
    $diaComConsulta=$pdo->query("SELECT EXTRACT(DOW FROM data)::int FROM agendamentos WHERE data>=CURRENT_DATE AND status IN ('Agendado','Confirmado') LIMIT 1")->fetchColumn();
    if ($diaComConsulta!==false) falha(fn()=>salvarFuncionamento([],[]),'fechar dias que têm consultas marcadas é recusado','consulta');

    // ---- tela "Banco de dados": registro de alterações e acesso ----
    marcarUsuarioNoBanco('dono:teste-auditoria@exemplo.com');
    $antesAud=(int)$pdo->query('SELECT COALESCE(max(id),0) FROM auditoria_banco')->fetchColumn();
    $pdo->exec("UPDATE clinica SET limite_agendamentos_dia=limite_agendamentos_dia+1 WHERE id=1");
    $pdo->exec("UPDATE clinica SET nome=nome WHERE id=1"); // não muda nada: não entra no registro
    $reg=registroAlteracoes($pdo,$antesAud,10);
    ok(count($reg)===1 && $reg[0]['tabela']==='clinica' && $reg[0]['operacao']==='UPDATE' && str_contains($reg[0]['colunas'],'limite_agendamentos_dia')
        && $reg[0]['usuario']==='dono:teste-auditoria@exemplo.com','registro de alterações: tabela, operação, colunas e quem fez (sem os valores)');
    $usuarios=conteudoTabela($pdo,'usuarios');
    ok(!array_filter($usuarios['linhas'],fn($l)=>isset($l['senha_hash']) && $l['senha_hash']!=='•••• (oculto)'),'conteúdo da tabela: hash de senha nunca aparece');
    falha(fn()=>conteudoTabela($pdo,'usuarios; DROP TABLE pessoa'),'nome de tabela fora da lista é recusado','Tabela não encontrada');
    $ipReal=$_SERVER['REMOTE_ADDR'];
    $_SERVER['REMOTE_ADDR']='192.168.0.50';
    ok(getenv('APP_MONITOR_REDE')==='true' || !monitorPermitido(),'tela do banco fechada para outro aparelho da rede');
    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    ok(monitorPermitido(),'tela do banco abre neste computador');
    $_SERVER['REMOTE_ADDR']=$ipReal;
    ok(count(tabelasMonitor($pdo))===count(tabelasDoBanco($pdo)),'todas as tabelas aparecem na tela');

    // ---- troca de senha derruba as outras sessões ----
    como('recepcionista','recepcao@dentai.com');
    $_SESSION['versao_senha']='versao-antiga';
    alterarSenhaAtual(['senha_atual'=>'123456','senha_nova'=>'nova-senha-1','confirmar_senha_nova'=>'nova-senha-1']);
    $hash=$pdo->query("SELECT senha_hash FROM usuarios WHERE email='recepcao@dentai.com'")->fetchColumn();
    ok($_SESSION['versao_senha']===versaoSenha($hash) && versaoSenha($hash)!=='versao-antiga','troca de senha: esta sessão segue, sessões antigas deixam de valer');
} catch (Throwable $e) {
    fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n");
    $falhou=true;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
try {
    ok($retrato()===$antes,'ROLLBACK: pessoas, usuários e consultas ficaram exatamente como antes');
} catch (Throwable $e) { fwrite(STDERR,'[ERRO] '.$e->getMessage()."\n"); $falhou=true; }

if (!empty($falhou)) exit(1);
echo "[OK] TODOS OS TESTES DE SEGURANÇA E CADASTRO PASSARAM.\n";
