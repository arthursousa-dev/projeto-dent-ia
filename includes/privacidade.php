<?php
// Direitos do titular dos dados (LGPD, art. 18): cópia, correção e exclusão.
// O prontuário não é apagado (guarda mínima de 20 anos, Lei 13.787/2018): a exclusão remove o que não é clínico.

/** O que a exclusão faz. O mesmo texto aparece para o paciente e para o gestor. */
const TEXTO_EXCLUSAO_LGPD = 'A exclusão apaga telefone, e-mail, endereço, RG, profissão, estado civil, carteirinha do plano e observações, e encerra o acesso ao portal. Nome, CPF, data de nascimento, prontuário, consultas realizadas e pagamentos continuam guardados, porque a lei obriga a clínica a manter o prontuário por no mínimo 20 anos (Lei 13.787/2018).';

/** Pedidos de um paciente, do mais novo para o mais antigo. */
function solicitacoesDoPaciente(int $paciente): array {
    $stmt=\App\Config\Database::getConnection()->prepare("SELECT id,tipo,mensagem,status,resposta,
        to_char(criada_em,'DD/MM/YYYY HH24:MI') AS criada_em,to_char(respondida_em,'DD/MM/YYYY HH24:MI') AS respondida_em
        FROM solicitacao_titular WHERE id_paciente=:id ORDER BY id DESC");
    $stmt->execute(['id'=>$paciente]);
    return $stmt->fetchAll();
}

/** Tudo o que o sistema guarda sobre o paciente logado, para ele baixar. O download fica registrado como pedido de cópia atendido. */
function exportarDadosTitular(): array {
    exigirPerfil(['cliente']);
    $p=pacienteSessao() ?? throw new RuntimeException('Cadastro de paciente não encontrado.');
    $id=(int)$p['id'];
    $pdo=\App\Config\Database::getConnection();
    $prontuario=lerJson('prontuarios.json',['id_paciente'=>$id])[0] ?? [];
    $stmt=$pdo->prepare("SELECT versao_termo,origem,to_char(aceito_em,'DD/MM/YYYY HH24:MI') AS aceito_em FROM consentimento WHERE id_paciente=:id ORDER BY id");
    $stmt->execute(['id'=>$id]);
    $consentimentos=$stmt->fetchAll();
    $pdo->prepare("INSERT INTO solicitacao_titular (id_paciente,tipo,status,resposta,respondida_em,respondida_por)
        VALUES (:id,'copia','atendida','Arquivo gerado pelo próprio paciente no portal.',now(),:quem)")
        ->execute(['id'=>$id,'quem'=>'cliente:'.($_SESSION['email'] ?? '')]);
    // Identificadores internos e e-mails da equipe ficam de fora: o arquivo é só sobre o paciente.
    $semInternos=fn(array $linha,array $chaves)=>array_diff_key($linha,array_flip($chaves));
    return [
        'gerado_em'=>date('d/m/Y H:i'),
        'clinica'=>lerJsonObjeto('clinica.json')['nome'] ?? 'DENT IA',
        'cadastro'=>$semInternos($p,['id','id_usuario']),
        'consentimentos'=>$consentimentos,
        'consultas'=>array_map(fn($a)=>$semInternos($a,['id','id_paciente','id_dentista','paciente']),lerJson('agendamentos.json',['id_paciente'=>$id])),
        'prontuario'=>['odontograma'=>$prontuario['odontograma'] ?? [],'evolucoes'=>$prontuario['historico'] ?? []],
        'ja_constaram'=>array_map(fn($h)=>$semInternos($h,['retificado_por']),historicoRetificado($id)),
        'pedidos'=>array_map(fn($s)=>$semInternos($s,['id']),solicitacoesDoPaciente($id)),
    ];
}

/** Paciente pede correção (do que ele não consegue mudar em "Meu perfil") ou exclusão. Um pedido aberto por tipo. */
function solicitarDireitoTitular(string $tipo, string $mensagem): void {
    exigirPerfil(['cliente']);
    $p=pacienteSessao() ?? throw new RuntimeException('Cadastro de paciente não encontrado.');
    if (!in_array($tipo,['correcao','exclusao'],true)) throw new InvalidArgumentException('Escolha o tipo do pedido.');
    $mensagem=textoValido($mensagem,'o que precisa ser corrigido',1000,$tipo==='correcao');
    try {
        transacaoOperacao(fn(PDO $pdo)=>$pdo->prepare('INSERT INTO solicitacao_titular (id_paciente,tipo,mensagem) VALUES (:id,:tipo,:mensagem)')
            ->execute(['id'=>$p['id'],'tipo'=>$tipo,'mensagem'=>$mensagem===''?null:$mensagem]));
    } catch (PDOException $e) {
        if ($e->getCode()==='23505') throw new InvalidArgumentException('Você já tem um pedido desse tipo em análise. Aguarde a resposta da clínica.');
        throw $e;
    }
}

/** Todos os pedidos, para o gestor: abertos primeiro. */
function solicitacoesDosTitulares(): array {
    exigirPerfil(['dono']);
    return \App\Config\Database::getConnection()->query("SELECT s.id,s.tipo,s.mensagem,s.status,s.resposta,pe.nome,
        to_char(s.criada_em,'DD/MM/YYYY HH24:MI') AS criada_em,to_char(s.respondida_em,'DD/MM/YYYY HH24:MI') AS respondida_em
        FROM solicitacao_titular s JOIN pacientes pac ON pac.id=s.id_paciente JOIN pessoa pe ON pe.id=pac.id_pessoa
        WHERE s.tipo<>'copia' ORDER BY (s.status='aberta') DESC,s.id DESC LIMIT 200")->fetchAll();
}

/** Gestor atende ou recusa um pedido aberto, sempre com resposta ao paciente. Atender uma exclusão anonimiza na mesma transação. */
function responderSolicitacaoTitular(int $id, bool $atender, string $resposta): void {
    exigirPerfil(['dono']);
    $resposta=textoValido($resposta,'a resposta ao paciente',1000);
    transacaoOperacao(function(PDO $pdo) use($id,$atender,$resposta) {
        $stmt=$pdo->prepare("SELECT id_paciente,tipo FROM solicitacao_titular WHERE id=:id AND status='aberta' FOR UPDATE");
        $stmt->execute(['id'=>$id]);
        $pedido=$stmt->fetch() ?: throw new InvalidArgumentException('Pedido não encontrado ou já respondido.');
        if ($atender && $pedido['tipo']==='exclusao') anonimizarPaciente($pdo,(int)$pedido['id_paciente']);
        $pdo->prepare('UPDATE solicitacao_titular SET status=:status,resposta=:resposta,respondida_em=now(),respondida_por=:quem WHERE id=:id')
            ->execute(['status'=>$atender?'atendida':'recusada','resposta'=>$resposta,'quem'=>'dono:'.($_SESSION['email'] ?? ''),'id'=>$id]);
    });
}

/**
 * Exclusão a pedido do titular: apaga o que não é clínico (ver TEXTO_EXCLUSAO_LGPD) e mantém o prontuário.
 * Não tem volta. Chame dentro de uma transação.
 */
function anonimizarPaciente(PDO $pdo, int $paciente): void {
    $stmt=$pdo->prepare('SELECT id_pessoa,id_endereco FROM pacientes WHERE id=:id FOR UPDATE');
    $stmt->execute(['id'=>$paciente]);
    $pac=$stmt->fetch() ?: throw new InvalidArgumentException('Paciente não encontrado.');
    $pessoa=(int)$pac['id_pessoa'];
    // Telefone e e-mail são da pessoa: se ela também é da equipe, apagar aqui tiraria o contato do funcionário.
    $stmt=$pdo->prepare("SELECT EXISTS(SELECT 1 FROM usuarios WHERE id_pessoa=:p AND tipo<>'cliente') OR EXISTS(SELECT 1 FROM dentistas WHERE id_pessoa=:p2)");
    $stmt->execute(['p'=>$pessoa,'p2'=>$pessoa]);
    if (booleanoPg($stmt->fetchColumn())) throw new InvalidArgumentException('Este paciente também é da equipe da clínica. A exclusão precisa ser feita à mão, com o responsável.');
    $stmt=$pdo->prepare('SELECT count(*) FROM agendamentos WHERE id_paciente=:id AND data>=current_date AND status IN ('.listaSql(STATUS_EM_ABERTO).')');
    $stmt->execute(['id'=>$paciente]);
    if ((int)$stmt->fetchColumn()>0) throw new InvalidArgumentException('O paciente ainda tem consulta marcada. Cancele ou conclua as consultas antes de atender a exclusão.');

    $pdo->prepare('DELETE FROM telefone WHERE id_pessoa=:p')->execute(['p'=>$pessoa]);
    $pdo->prepare('UPDATE pessoa SET email=NULL,rg=NULL WHERE id=:p')->execute(['p'=>$pessoa]);
    $pdo->prepare("UPDATE pacientes SET id_endereco=NULL,estado_civil=NULL,profissao=NULL,numero_carteirinha=NULL,observacoes=NULL,id_plano=DEFAULT,status='inativo' WHERE id=:id")
        ->execute(['id'=>$paciente]);
    if ($pac['id_endereco']) {
        // Endereço usado por outro cadastro (23503) fica; o deste paciente já foi desligado acima.
        try { transacaoOperacao(fn(PDO $pdo)=>$pdo->prepare('DELETE FROM endereco WHERE id=:id')->execute(['id'=>$pac['id_endereco']])); }
        catch (PDOException $e) { if ($e->getCode()!=='23503') throw $e; }
    }
    // O login era o e-mail: troca por um endereço que não existe e por uma senha que ninguém conhece.
    $pdo->prepare("UPDATE usuarios SET status='inativo',email='removido-'||id||'@anonimizado.invalid',senha_hash=:senha WHERE id_pessoa=:p AND tipo='cliente'")
        ->execute(['senha'=>senhaSemAcesso(),'p'=>$pessoa]);
}
