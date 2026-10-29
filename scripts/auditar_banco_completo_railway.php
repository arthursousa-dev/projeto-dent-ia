<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // só pelo terminal: nunca pela web
/** Diagnóstico SOMENTE LEITURA de todos os objetos do PostgreSQL Railway.
 * Uso: php scripts/auditar_banco_completo_railway.php
 * Gera relatório em JSON SEM registros de pacientes, senhas ou credenciais.
 */
require_once dirname(__DIR__) . '/app/Config/Database.php';
use App\Config\Database;
try {
    $pdo = Database::getConnection();
    $sql = <<<'SQL'
SELECT n.nspname AS esquema,c.relname AS nome,c.relkind AS tipo,
       a.attname AS coluna,format_type(a.atttypid,a.atttypmod) AS tipo_coluna,
       a.attnotnull AS obrigatoria,pg_get_expr(ad.adbin,ad.adrelid) AS padrao
FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
LEFT JOIN pg_attribute a ON a.attrelid=c.oid AND a.attnum>0 AND NOT a.attisdropped
LEFT JOIN pg_attrdef ad ON ad.adrelid=c.oid AND ad.adnum=a.attnum
WHERE n.nspname='public' AND c.relkind IN ('r','p','v','m')
ORDER BY c.relkind,c.relname,a.attnum
SQL;
    $objetos=[];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nome=$r['nome'];
        if (!isset($objetos[$nome])) $objetos[$nome]=['tipo'=>$r['tipo'],'colunas'=>[],'restricoes'=>[]];
        if ($r['coluna']!==null) $objetos[$nome]['colunas'][$r['coluna']]=[
            'tipo'=>$r['tipo_coluna'],'obrigatoria'=>$r['obrigatoria'],
            'padrao'=>$r['padrao']];
    }
    $constraints=$pdo->query(<<<'SQL'
SELECT cls.relname AS tabela,con.conname AS nome,con.contype AS tipo,pg_get_constraintdef(con.oid) AS definicao
FROM pg_constraint con JOIN pg_class cls ON cls.oid=con.conrelid
JOIN pg_namespace ns ON ns.oid=cls.relnamespace WHERE ns.nspname='public'
ORDER BY cls.relname,con.conname
SQL)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($constraints as $c) $objetos[$c['tabela']]['restricoes'][$c['nome']]=[$c['tipo'],$c['definicao']];
    $requeridos=[
        'pessoa','usuarios','pacientes','dentistas','procedimentos','dentista_procedimentos','agendamentos',
        'prontuarios','evolucao_clinica','odontograma_dentes','odontograma_procedimentos','evolucao_dente',
        'pagamento','lancamento_financeiro','categoria_lancamento','recuperacao_senha',
        'sala','especialidade','plano_saude','cidade','endereco','telefone','clinica',
        'disponibilidade_dentista','funcionamento_clinica','dia_semana',
        'vw_usuarios','vw_pacientes','vw_dentistas','vw_procedimentos','vw_agendamentos','vw_clinica',
        'vw_enderecos','vw_resumo_financeiro_mensal','vw_movimentos_financeiros'];
    $faltantes=array_values(array_diff($requeridos,array_keys($objetos)));
    $colunasCriticas=[
        'usuarios'=>['id','id_pessoa','email','senha_hash','tipo','status','tentativas_login','bloqueado_ate'],
        'pacientes'=>['id','id_pessoa'], 'dentistas'=>['id','id_pessoa','id_especialidade','id_sala'],
        'agendamentos'=>['id','id_paciente','id_dentista','id_procedimento','id_sala','data','hora','valor'],
        'prontuarios'=>['id','id_paciente']];
    $divergencias=[];
    foreach ($colunasCriticas as $t=>$colunas) if (isset($objetos[$t])) {
        $faltam=array_values(array_diff($colunas,array_keys($objetos[$t]['colunas'])));
        if ($faltam) $divergencias[$t]=$faltam;
    }
    $relatorio=['gerado_em'=>gmdate('c'),'banco'=>'PostgreSQL (nome ocultado)',
      'total_objetos'=>count($objetos),'objetos'=>$objetos,'objetos_esperados_ausentes'=>$faltantes,
      'colunas_esperadas_ausentes'=>$divergencias,
      'aviso'=>'Somente metadados de esquema; nenhuma linha de pacientes, usuários ou credenciais incluída.'];
    $saida=dirname(__DIR__).'/diagnostico_railway_esquema.json';
    if (file_put_contents($saida,json_encode($relatorio,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false)
        throw new RuntimeException('Não foi possível escrever '.$saida);
    echo "Objetos encontrados: ".count($objetos)."\n";
    echo 'Objetos esperados AUSENTES: '.(implode(', ',$faltantes) ?: 'nenhum')."\n";
    foreach ($divergencias as $t=>$cols) echo "Colunas ausentes em {$t}: ".implode(', ',$cols)."\n";
    echo "Relatório gerado: {$saida}\nNão foram executadas alterações no banco.\n";
} catch(Throwable $e) {fwrite(STDERR,'Falha no diagnóstico: '.$e->getMessage()."\n");exit(1);}
