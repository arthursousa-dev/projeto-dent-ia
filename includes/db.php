<?php
// Adaptador de persistência: lerJson/salvarJson preservam a
// interface original (arquivo JSON), mas operam sobre PostgreSQL
// via PDO com prepared statements. TODO: migrar leituras quentes
// (ex. listagem de pacientes) para queries diretas em vez de
// carregar a tabela inteira.

use App\Config\Database;

require_once __DIR__ . '/../app/Config/Database.php';

/**
 * Lê uma "fonte" de dados e devolve um array de registros, no
 * mesmo formato que o antigo arquivo JSON tinha.
 */
function lerJson(string $arquivo): array {
    $pdo = Database::getConnection();

    switch ($arquivo) {
        case 'usuarios.json':
            $stmt = $pdo->query(
                "SELECT id, email, senha_hash AS senha, tipo, nome, cpf, telefone,
                        to_char(nascimento, 'YYYY-MM-DD') AS nascimento,
                        cargo, status, criado_em
                 FROM usuarios ORDER BY id"
            );
            return $stmt->fetchAll();

        case 'pacientes.json':
            $stmt = $pdo->query(
                "SELECT id, id_usuario, nome, cpf, rg, email, telefone, telefone_emergencia,
                        to_char(nascimento, 'YYYY-MM-DD') AS nascimento,
                        sexo, estado_civil, profissao, plano, numero_carteirinha,
                        endereco, bairro, cidade, estado, cep, alergias,
                        medicamentos_em_uso, doencas_preexistentes, observacoes,
                        INITCAP(status::text) AS status, criado_em
                 FROM pacientes ORDER BY id"
            );
            return $stmt->fetchAll();

        case 'dentistas.json':
            $stmt = $pdo->query(
                "SELECT d.id, d.id_usuario, d.nome, d.email, d.telefone, d.cpf,
                        to_char(d.nascimento, 'YYYY-MM-DD') AS nascimento,
                        d.cro, d.especialidade, d.titulacao, d.faculdade, d.ano_formatura,
                        to_char(d.admissao, 'YYYY-MM-DD') AS admissao,
                        d.cor, d.sala, INITCAP(d.status::text) AS status,
                        d.horarios, d.dias_atendimento,
                        COALESCE(array_agg(DISTINCT p.nome) FILTER (WHERE p.nome IS NOT NULL), '{}') AS procedimentos,
                        (
                            SELECT COUNT(*) FROM agendamentos a
                            WHERE a.id_dentista = d.id
                              AND date_trunc('month', a.data) = date_trunc('month', CURRENT_DATE)
                        ) AS atendimentos_mes
                 FROM dentistas d
                 LEFT JOIN dentista_procedimentos dp ON dp.id_dentista = d.id
                 LEFT JOIN procedimentos p ON p.id = dp.id_procedimento
                 GROUP BY d.id ORDER BY d.id"
            );
            $linhas = $stmt->fetchAll();
            foreach ($linhas as &$d) {
                $d['horarios'] = pgArrayParaLista($d['horarios']);
                $d['dias_atendimento'] = pgArrayParaLista($d['dias_atendimento']);
                $d['procedimentos'] = pgArrayParaLista($d['procedimentos']);
            }
            return $linhas;

        case 'procedimentos.json':
            $stmt = $pdo->query('SELECT id, nome, categoria, duracao_min, valor_base FROM procedimentos ORDER BY nome');
            return $stmt->fetchAll();

        case 'agendamentos.json':
            $stmt = $pdo->query(
                "SELECT a.id, a.id_paciente, a.id_dentista,
                        pac.nome AS paciente, d.nome AS dentista,
                        COALESCE(proc.nome, 'Consulta') AS servico,
                        to_char(a.data, 'YYYY-MM-DD') AS data,
                        to_char(a.hora, 'HH24:MI') AS hora,
                        a.sala, a.status::text AS status, a.valor, a.pago,
                        a.forma_pagamento, a.observacoes
                 FROM agendamentos a
                 JOIN pacientes pac ON pac.id = a.id_paciente
                 JOIN dentistas d   ON d.id  = a.id_dentista
                 LEFT JOIN procedimentos proc ON proc.id = a.id_procedimento
                 ORDER BY a.data, a.hora"
            );
            return $stmt->fetchAll();

        case 'prontuarios.json':
            $prontuarios = $pdo->query('SELECT id, id_paciente FROM prontuarios ORDER BY id')->fetchAll();

            $dentesStmt = $pdo->prepare(
                'SELECT id, numero_dente, status::text AS status, notas FROM odontograma_dentes WHERE id_prontuario = :id'
            );
            $procsStmt = $pdo->prepare(
                'SELECT descricao FROM odontograma_procedimentos WHERE id_dente = :id_dente ORDER BY registrado_em'
            );

            foreach ($prontuarios as &$p) {
                $dentesStmt->execute([':id' => $p['id']]);
                $odontograma = [];
                foreach ($dentesStmt->fetchAll() as $dente) {
                    $procsStmt->execute([':id_dente' => $dente['id']]);
                    $odontograma[(string) $dente['numero_dente']] = [
                        'status'       => $dente['status'],
                        'notas'        => $dente['notas'],
                        'procedimentos' => array_column($procsStmt->fetchAll(), 'descricao'),
                    ];
                }
                $p['odontograma'] = $odontograma;
            }
            return $prontuarios;

        default:
            return [];
    }
}

/**
 * Lê uma "fonte" de dados que representa um único objeto (não uma
 * lista), como a configuração da clínica ou o resumo financeiro.
 */
function lerJsonObjeto(string $arquivo): array {
    $pdo = Database::getConnection();

    switch ($arquivo) {
        case 'clinica.json':
            $stmt = $pdo->query('SELECT * FROM clinica WHERE id = 1');
            return $stmt->fetch() ?: [];

        case 'faturamento.json':
            $stmt = $pdo->query(
                "SELECT ano, mes_num, receita, despesas, qtd_procedimentos AS procedimentos
                 FROM faturamento_mensal ORDER BY ano, mes_num"
            );
            return ['historico_mensal' => $stmt->fetchAll()];

        default:
            return [];
    }
}

/**
 * Grava de volta uma "fonte" de dados. Recebe o array completo (no
 * mesmo formato de lerJson) e faz upsert de cada registro pelo ID —
 * suficiente para o volume de dados desta aplicação.
 */
function salvarJson(string $arquivo, array $dados): bool {
    $pdo = Database::getConnection();

    try {
        $pdo->beginTransaction();

        switch ($arquivo) {
            case 'usuarios.json':
                $stmt = $pdo->prepare(
                    "UPDATE usuarios SET nome=:nome, telefone=:telefone, nascimento=NULLIF(:nascimento,'')::date,
                     senha_hash=:senha, status=:status WHERE id=:id"
                );
                foreach ($dados as $u) {
                    $stmt->execute([
                        ':nome' => $u['nome'], ':telefone' => $u['telefone'] ?? null,
                        ':nascimento' => $u['nascimento'] ?? '', ':senha' => $u['senha'],
                        ':status' => $u['status'] ?? 'ativo', ':id' => $u['id'],
                    ]);
                }
                break;

            case 'pacientes.json':
                $stmt = $pdo->prepare(
                    'UPDATE pacientes SET nome=:nome, telefone=:telefone, email=:email,
                     endereco=:endereco, bairro=:bairro, cidade=:cidade, estado=:estado, cep=:cep,
                     alergias=:alergias, medicamentos_em_uso=:medicamentos, doencas_preexistentes=:doencas,
                     observacoes=:observacoes WHERE id=:id'
                );
                foreach ($dados as $p) {
                    $stmt->execute([
                        ':nome' => $p['nome'], ':telefone' => $p['telefone'] ?? null, ':email' => $p['email'] ?? null,
                        ':endereco' => $p['endereco'] ?? null, ':bairro' => $p['bairro'] ?? null,
                        ':cidade' => $p['cidade'] ?? null, ':estado' => $p['estado'] ?? null, ':cep' => $p['cep'] ?? null,
                        ':alergias' => $p['alergias'] ?? null, ':medicamentos' => $p['medicamentos_em_uso'] ?? null,
                        ':doencas' => $p['doencas_preexistentes'] ?? null, ':observacoes' => $p['observacoes'] ?? null,
                        ':id' => $p['id'],
                    ]);
                }
                break;

            case 'dentistas.json':
                $stmt = $pdo->prepare(
                    'UPDATE dentistas SET nome=:nome, telefone=:telefone, email=:email, sala=:sala
                     WHERE id=:id'
                );
                foreach ($dados as $d) {
                    $stmt->execute([
                        ':nome' => $d['nome'], ':telefone' => $d['telefone'] ?? null,
                        ':email' => $d['email'] ?? null, ':sala' => $d['sala'] ?? null, ':id' => $d['id'],
                    ]);
                }
                break;

            case 'clinica.json':
                $c = $dados;
                $stmt = $pdo->prepare(
                    'UPDATE clinica SET nome=:nome, telefone=:telefone, email=:email, endereco=:endereco,
                     bairro=:bairro, cidade=:cidade, estado=:estado, cep=:cep, horario=:horario,
                     atualizado_em = now() WHERE id = 1'
                );
                $stmt->execute([
                    ':nome' => $c['nome'], ':telefone' => $c['telefone'], ':email' => $c['email'],
                    ':endereco' => $c['endereco'], ':bairro' => $c['bairro'], ':cidade' => $c['cidade'],
                    ':estado' => $c['estado'], ':cep' => $c['cep'], ':horario' => $c['horario'],
                ]);
                break;

            case 'faturamento.json':
                $stmt = $pdo->prepare(
                    'INSERT INTO faturamento_mensal (ano, mes_num, receita, despesas, qtd_procedimentos)
                     VALUES (:ano, :mes, :receita, :despesas, :qtd)
                     ON CONFLICT (ano, mes_num) DO UPDATE SET
                        receita = EXCLUDED.receita, despesas = EXCLUDED.despesas, qtd_procedimentos = EXCLUDED.qtd_procedimentos'
                );
                foreach (($dados['historico_mensal'] ?? []) as $m) {
                    $stmt->execute([
                        ':ano' => $m['ano'], ':mes' => $m['mes_num'], ':receita' => $m['receita'],
                        ':despesas' => $m['despesas'], ':qtd' => $m['procedimentos'],
                    ]);
                }
                break;
        }

        $pdo->commit();
        return true;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('salvarJson falhou (' . $arquivo . '): ' . $e->getMessage());
        return false;
    }
}

/** Converte o texto retornado pra um array PHP (o driver pgsql retorna arrays como string "{a,b,c}"). */
function pgArrayParaLista(?string $valorPg): array {
    if ($valorPg === null || $valorPg === '{}') return [];
    $valorPg = trim($valorPg, '{}');
    if ($valorPg === '') return [];
    preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|([^,]+)/', $valorPg, $matches);
    $itens = [];
    foreach ($matches[0] as $i => $bruto) {
        $item = $matches[1][$i] !== '' ? $matches[1][$i] : $matches[2][$i];
        $itens[] = str_replace('\\"', '"', $item);
    }
    return $itens;
}

/**
 * Retorna o próximo ID disponível em um array de registros.
 * Mantido por compatibilidade — com o banco, os IDs novos vêm de
 * SERIAL, mas algumas telas ainda calculam isso em memória antes
 * de um INSERT direto.
 */
function proximoId(array $registros): int {
    if (empty($registros)) return 1;
    $ids = array_column($registros, 'id');
    return empty($ids) ? 1 : (max($ids) + 1);
}

/**
 * Busca um registro pelo valor de um campo específico.
 */
function buscarPorCampo(array $registros, string $campo, mixed $valor): array|null {
    foreach ($registros as $item) {
        if (isset($item[$campo]) && $item[$campo] == $valor) {
            return $item;
        }
    }
    return null;
}

/**
 * Filtra registros onde um campo tem determinado valor.
 */
function filtrarPorCampo(array $registros, string $campo, mixed $valor): array {
    return array_values(array_filter($registros, function($item) use ($campo, $valor) {
        return isset($item[$campo]) && $item[$campo] == $valor;
    }));
}

/**
 * Atualiza um registro em um array pelo ID (em memória).
 */
function atualizarRegistro(array $registros, int $id, array $novosDados): array {
    foreach ($registros as &$item) {
        if ($item['id'] == $id) {
            $item = array_merge($item, $novosDados);
            break;
        }
    }
    return $registros;
}

/**
 * Remove um registro pelo ID (em memória).
 */
function removerRegistro(array $registros, int $id): array {
    return array_values(array_filter($registros, fn($item) => $item['id'] != $id));
}
