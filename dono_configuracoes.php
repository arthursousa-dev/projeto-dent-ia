<?php
require_once __DIR__.'/includes/bootstrap_sessao.php';
require_once __DIR__.'/includes/functions.php';
verificarSessao('dono');$tituloPagina='Configurações';$erro='';$sucesso='';
$pdo=\App\Config\Database::getConnection();$clinica=lerJsonObjeto('clinica.json');
if (isset($_POST['salvar'])) {
    try {
        if (isset($_POST['horarios'])) {
            transacaoOperacao(function(PDO $pdo) {
                $rotulos=[];
                $stmt=$pdo->prepare('INSERT INTO funcionamento_clinica (id_clinica,id_dia_semana,abertura,fechamento) VALUES (1,:dia,:abertura,:fechamento)
                    ON CONFLICT (id_clinica,id_dia_semana) DO UPDATE SET abertura=EXCLUDED.abertura,fechamento=EXCLUDED.fechamento');
                $dias=$pdo->query('SELECT * FROM dia_semana ORDER BY id')->fetchAll();
                foreach ($dias as $d) {
                    if (empty($_POST['abertura'][$d['id']]) || empty($_POST['fechamento'][$d['id']])) {
                        $pdo->prepare('DELETE FROM funcionamento_clinica WHERE id_clinica=1 AND id_dia_semana=:id')->execute(['id'=>$d['id']]);continue;
                    }
                    $stmt->execute(['dia'=>$d['id'],'abertura'=>$_POST['abertura'][$d['id']],'fechamento'=>$_POST['fechamento'][$d['id']]]);
                    $rotulos[]=$d['nome'].': '.$_POST['abertura'][$d['id']].' às '.$_POST['fechamento'][$d['id']];
                }
                $pdo->prepare('UPDATE clinica SET horario=:horario WHERE id=1')->execute(['horario'=>implode(' | ',$rotulos)]);
            });
        } else {
            $novo=array_merge($clinica,camposPresentes($_POST,['nome','razao_social','cnpj','telefone','email','endereco','bairro','cidade','estado','cep','limite_agendamentos_dia','antecedencia_min_dias']));
            if (!salvarJson('clinica.json',$novo)) throw new RuntimeException('Confira os dados da clínica.');
        }
        $sucesso='Configurações salvas.';
    } catch (Throwable $e) { $erro=mensagemOperacao($e); }
    $clinica=lerJsonObjeto('clinica.json');
}
$dias=$pdo->query("SELECT ds.id,ds.nome,to_char(f.abertura,'HH24:MI') AS abertura,to_char(f.fechamento,'HH24:MI') AS fechamento
    FROM dia_semana ds LEFT JOIN funcionamento_clinica f ON f.id_dia_semana=ds.id AND f.id_clinica=1 ORDER BY ds.id")->fetchAll();
include 'includes/head.php';
?>
<div class="painel"><?php include 'includes/sidebar.php'; ?><main class="conteudo-principal">
<div class="cabecalho-pagina"><h1>Configurações</h1><a class="btn btn-fantasma" href="dono_usuarios.php">Usuários e recuperação de senha</a><a class="btn btn-fantasma" href="dono_perfil.php">Editar minha conta</a></div>
<?php if ($erro): ?><div class="alerta alerta-erro"><?= limpar($erro) ?></div><?php endif; ?>
<?php if ($sucesso): ?><div class="alerta alerta-sucesso"><?= limpar($sucesso) ?></div><?php endif; ?>
<div class="cartao"><div class="cartao-cabecalho"><h3>Clínica e regras de agendamento</h3></div><div class="cartao-corpo"><form method="POST"><?= csrfCampo() ?>
<?php foreach (['nome'=>'Nome','razao_social'=>'Razão social','cnpj'=>'CNPJ','telefone'=>'Telefone','email'=>'E-mail','endereco'=>'Logradouro e número','bairro'=>'Bairro','cidade'=>'Cidade','estado'=>'UF','cep'=>'CEP','limite_agendamentos_dia'=>'Limite de consultas por dia','antecedencia_min_dias'=>'Antecedência mínima para o paciente (dias)'] as $campo=>$rotulo): ?>
<div class="grupo-campo"><label><?= $rotulo ?></label><input name="<?= $campo ?>" value="<?= limpar((string)$clinica[$campo]) ?>" required></div><?php endforeach; ?>
<button name="salvar" class="btn btn-primario">Salvar</button></form></div></div>
<div class="cartao"><div class="cartao-cabecalho"><h3>Horário de funcionamento</h3></div><div class="cartao-corpo"><p>Deixe os dois horários vazios nos dias fechados.</p><form method="POST"><?= csrfCampo() ?><input type="hidden" name="horarios" value="1">
<?php foreach ($dias as $d): ?><div class="linha-campos"><label><?= limpar($d['nome']) ?></label><input type="time" name="abertura[<?= $d['id'] ?>]" value="<?= limpar($d['abertura']) ?>"><input type="time" name="fechamento[<?= $d['id'] ?>]" value="<?= limpar($d['fechamento']) ?>"></div><?php endforeach; ?>
<button name="salvar" class="btn btn-primario">Salvar horários</button></form></div></div>
</main></div><?php include 'includes/a11y_bar.php'; ?>
