<?php
// Consultas da tela "Banco de dados" do gestor (dono_banco.php e api_banco.php). Tudo só leitura.

/**
 * A tela mostra o banco inteiro (dados de pacientes): além do perfil de gestor, só abre neste computador.
 * Para abrir de outro aparelho da rede, APP_MONITOR_REDE=true no .env.
 */
function monitorPermitido(): bool {
    \App\Config\Database::getConnection(); // carrega o .env
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) || getenv('APP_MONITOR_REDE') === 'true';
}

/** Barra quem não é o gestor ou está fora deste computador. A página "não existe" para os outros. */
function exigirMonitor(): void {
    verificarSessao('dono');
    if (!monitorPermitido()) paginaErro(404, 'Página não encontrada', 'Esta página não existe.');
}

/** Nomes das tabelas do sistema (lista fixa vinda do catálogo: é a única fonte aceita para nome de tabela). */
function tabelasDoBanco(PDO $pdo): array {
    return $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
}

/** Visão geral: tamanho, conexões e volume de alterações. */
function resumoBanco(PDO $pdo): array {
    return $pdo->query("SELECT pg_size_pretty(pg_database_size(current_database())) AS tamanho,
        current_database() AS nome, split_part(version(), ' ', 2) AS versao,
        (SELECT count(*) FROM pg_stat_activity WHERE datname=current_database()) AS conexoes,
        (SELECT count(*) FROM auditoria_banco WHERE momento >= date_trunc('day', now())) AS alteracoes_hoje,
        (SELECT count(*) FROM auditoria_banco WHERE momento >= now() - interval '1 hour') AS alteracoes_hora,
        (SELECT COALESCE(max(id),0) FROM auditoria_banco) AS ultimo_id")->fetch();
}

/** Cada tabela com registros, tamanho, totais de inserções/alterações/exclusões e a última mudança registrada. */
function tabelasMonitor(PDO $pdo): array {
    $tabelas = tabelasDoBanco($pdo);
    $contagens = implode(' UNION ALL ', array_map(
        fn($t) => "SELECT " . $pdo->quote($t) . " AS tabela, count(*) AS registros FROM " . '"' . str_replace('"', '""', $t) . '"', $tabelas));
    $stmt = $pdo->query("WITH c AS ($contagens),
        ultima AS (SELECT tabela, max(momento) AS ultima, count(*) FILTER (WHERE momento >= now() - interval '1 hour') AS na_hora
                   FROM auditoria_banco GROUP BY tabela)
        SELECT c.tabela, c.registros, pg_size_pretty(pg_total_relation_size(quote_ident(c.tabela)::regclass)) AS tamanho,
               COALESCE(s.n_tup_ins,0) AS insercoes, COALESCE(s.n_tup_upd,0) AS alteracoes, COALESCE(s.n_tup_del,0) AS exclusoes,
               to_char(u.ultima,'DD/MM/YYYY HH24:MI:SS') AS ultima_alteracao,
               EXTRACT(EPOCH FROM u.ultima)::bigint AS ultima_epoca, COALESCE(u.na_hora,0) AS na_hora
        FROM c LEFT JOIN pg_stat_user_tables s ON s.relname=c.tabela AND s.schemaname='public'
        LEFT JOIN ultima u ON u.tabela=c.tabela
        ORDER BY u.ultima DESC NULLS LAST, c.tabela");
    return $stmt->fetchAll();
}

/** Ligações entre tabelas (chaves estrangeiras): as arestas do grafo. */
function relacoesTabelas(PDO $pdo): array {
    return $pdo->query("SELECT DISTINCT c.conrelid::regclass::text AS origem, c.confrelid::regclass::text AS destino
        FROM pg_constraint c JOIN pg_namespace n ON n.oid=c.connamespace
        WHERE c.contype='f' AND n.nspname='public' AND c.conrelid<>c.confrelid ORDER BY 1,2")->fetchAll();
}

/** Colunas de cada tabela, na ordem do banco (para o inspetor). */
function colunasPorTabela(PDO $pdo): array {
    $colunas = [];
    foreach ($pdo->query("SELECT table_name, column_name FROM information_schema.columns
        WHERE table_schema='public' ORDER BY table_name, ordinal_position") as $c) $colunas[$c['table_name']][] = $c['column_name'];
    return $colunas;
}

/** Área de cada tabela no grafo (cor do nó). */
function grupoTabela(string $tabela): string {
    static $grupos = [
        'acesso' => ['pessoa','usuarios','telefone','endereco','cidade','estado','recuperacao_senha','tentativa_acesso'],
        'agenda' => ['agendamentos','historico_status_agendamento','disponibilidade_dentista','funcionamento_clinica','sala','dia_semana','dentistas','dentista_procedimentos','especialidade','procedimentos','categoria_procedimento'],
        'clinico' => ['pacientes','prontuarios','evolucao_clinica','evolucao_dente','odontograma_dentes','odontograma_procedimentos','historico_clinico','plano_saude'],
        'financeiro' => ['pagamento','pagamento_estorno','lancamento_financeiro','categoria_lancamento','forma_pagamento','faturamento_mensal'],
    ];
    foreach ($grupos as $grupo => $tabelas) if (in_array($tabela, $tabelas, true)) return $grupo;
    return 'sistema';
}

/** Registro de alterações: as mais novas primeiro; com $desde, só as que chegaram depois desse id. */
function registroAlteracoes(PDO $pdo, int $desde = 0, int $limite = 100, string $tabela = ''): array {
    $sql = "SELECT id, to_char(momento,'DD/MM HH24:MI:SS') AS momento, tabela, operacao, id_registro,
            COALESCE(array_to_string(colunas, ', '), '') AS colunas, COALESCE(usuario, 'sistema') AS usuario
            FROM auditoria_banco WHERE id > :desde";
    $params = ['desde' => $desde];
    if ($tabela !== '') { $sql .= ' AND tabela = :tabela'; $params['tabela'] = $tabela; }
    $stmt = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT ' . max(1, min($limite, 500)));
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Coluna sensível: o valor nunca aparece na tela, mesmo para o gestor. */
function colunaOculta(string $coluna): bool {
    return (bool)preg_match('/senha|token|hash/i', $coluna);
}

/** Linhas de uma tabela (as mais recentes primeiro), com segredos mascarados e textos longos cortados. */
function conteudoTabela(PDO $pdo, string $tabela, int $pagina = 1, int $porPagina = 50): array {
    if (!in_array($tabela, tabelasDoBanco($pdo), true)) throw new InvalidArgumentException('Tabela não encontrada.');
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=:t ORDER BY ordinal_position");
    $stmt->execute(['t' => $tabela]);
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $identificador = '"' . str_replace('"', '""', $tabela) . '"';
    $total = (int)$pdo->query("SELECT count(*) FROM $identificador")->fetchColumn();
    $paginas = max(1, (int)ceil($total / $porPagina));
    $pagina = max(1, min($pagina, $paginas));
    $ordem = in_array('id', $colunas, true) ? 'id DESC' : '1';
    $linhas = $pdo->query("SELECT * FROM $identificador ORDER BY $ordem LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($linhas as &$linha) {
        foreach ($linha as $coluna => &$valor) {
            if (colunaOculta($coluna) && $valor !== null) $valor = '•••• (oculto)';
            elseif (is_string($valor) && tamanhoTexto($valor) > 120) $valor = cortarTexto($valor, 0, 120) . '…';
        }
        unset($valor);
    }
    unset($linha);
    return ['colunas' => $colunas, 'linhas' => $linhas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
}

/** O registro guarda 180 dias; o mais antigo sai de vez em quando (não a cada acesso). */
function limparRegistroAntigo(PDO $pdo): void {
    if (random_int(1, 20) === 1) $pdo->exec("DELETE FROM auditoria_banco WHERE momento < now() - interval '180 days'");
}
