<?php
// Compatibilidade com as páginas legadas sobre PostgreSQL normalizado.

use App\Config\Database;

require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/financeiro.php';

/**
 * Lê uma "fonte" de dados e devolve um array de registros, no
 * mesmo formato que o antigo arquivo JSON tinha.
 */
function lerJson(string $arquivo, array $filtro = []): array {
    $pdo = Database::getConnection();
    // $filtro: ['coluna' => valor | [valores]] vira WHERE no SQL, para não carregar a tabela inteira no PHP.
    $where = [];
    $params = [];
    foreach ($filtro as $coluna => $valor) {
        if (!preg_match('/^[a-z_]+$/', $coluna)) throw new InvalidArgumentException('Filtro inválido');
        $valores = array_values((array) $valor);
        if (!$valores) { $where[] = 'false'; continue; }
        $marcas = [];
        foreach ($valores as $i => $v) { $marcas[] = ":{$coluna}{$i}"; $params["{$coluna}{$i}"] = $v; }
        $where[] = "{$coluna} IN (" . implode(',', $marcas) . ')';
    }
    $where = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $views = [
        'usuarios.json' => ['vw_usuarios', 'id'],
        'pacientes.json' => ['vw_pacientes', 'id'],
        'dentistas.json' => ['vw_dentistas', 'id'],
        'procedimentos.json' => ['vw_procedimentos', 'nome'],
        'agendamentos.json' => ['vw_agendamentos', 'data, hora, id'],
    ];
    if (isset($views[$arquivo])) {
        [$view, $ordem] = $views[$arquivo];
        $stmt = $pdo->prepare("SELECT * FROM {$view}{$where} ORDER BY {$ordem}");
        $stmt->execute($params);
        $linhas = $stmt->fetchAll();
        if ($arquivo === 'agendamentos.json') {
            foreach ($linhas as &$a) $a['pago']=booleanoPg($a['pago']);
            unset($a);
        }
        if ($arquivo === 'dentistas.json') {
            foreach ($linhas as &$d) {
                foreach (['horarios', 'dias_atendimento', 'procedimentos'] as $campo) {
                    $d[$campo] = pgArrayParaLista($d[$campo]);
                }
            }
        }
        return $linhas;
    }
    switch ($arquivo) {
        case 'prontuarios.json':
            $stmt = $pdo->prepare("SELECT * FROM (SELECT pr.id, pr.id_paciente, p.nome AS paciente FROM prontuarios pr JOIN pacientes pac ON pac.id=pr.id_paciente JOIN pessoa p ON p.id=pac.id_pessoa) x{$where} ORDER BY id");
            $stmt->execute($params);
            $prontuarios = $stmt->fetchAll();

            // Uma consulta por prontuário (antes: uma por dente), com os procedimentos agregados em JSON.
            $dentesStmt = $pdo->prepare(
                "SELECT d.numero_dente, d.status::text AS status, d.notas,
                    COALESCE((SELECT json_agg(op.descricao ORDER BY op.registrado_em) FROM odontograma_procedimentos op
                        WHERE op.id_dente = d.id), '[]') AS procedimentos
                 FROM odontograma_dentes d WHERE d.id_prontuario = :id ORDER BY d.numero_dente"
            );
            $stmt=$pdo->prepare("SELECT to_char(e.registrado_em,'DD/MM/YYYY') AS data,p.nome AS dentista,
                    proc.nome AS proc,e.observacoes AS obs,COALESCE(a.valor,proc.valor_base) AS valor,e.retorno,
                    COALESCE((SELECT string_agg(d.numero_dente::text, ', ' ORDER BY d.numero_dente) FROM evolucao_dente ed
                        JOIN odontograma_dentes d ON d.id=ed.id_dente WHERE ed.id_evolucao=e.id),'—') AS dentes
                    FROM evolucao_clinica e JOIN dentistas dt ON dt.id=e.id_dentista JOIN pessoa p ON p.id=dt.id_pessoa
                    JOIN procedimentos proc ON proc.id=e.id_procedimento LEFT JOIN agendamentos a ON a.id=e.id_agendamento
                    WHERE e.id_prontuario=:id ORDER BY e.registrado_em DESC,e.id DESC");

            foreach ($prontuarios as &$p) {
                $dentesStmt->execute([':id' => $p['id']]);
                $odontograma = [];
                foreach ($dentesStmt->fetchAll() as $dente) {
                    $odontograma[(string) $dente['numero_dente']] = [
                        'status'       => $dente['status'],
                        'notas'        => $dente['notas'],
                        'procedimentos' => json_decode($dente['procedimentos'], true),
                    ];
                }
                $p['odontograma'] = $odontograma;
                $stmt->execute(['id'=>$p['id']]);$p['historico']=$stmt->fetchAll();
            }
            unset($p);
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
            $stmt = $pdo->query('SELECT * FROM vw_clinica WHERE id = 1');
            return $stmt->fetch() ?: [];

        case 'faturamento.json':
            return resumoFinanceiro($pdo);

        default:
            return [];
    }
}

/** Compatibiliza BOOLEAN recebido como bool ou 't'/'f' pelo PDO PostgreSQL. */
function booleanoPg(mixed $valor): bool {
    return in_array($valor, [true, 1, '1', 't', 'true'], true);
}

function soDigitos(?string $valor): string {
    return preg_replace('/\D/', '', $valor ?? '');
}

/**
 * Id de uma referência (sala, plano, especialidade…) pelo nome.
 * Por padrão só procura: texto digitado não cria item novo (um erro de digitação criaria uma "Sala 1 " separada
 * e furaria a trava de sala). $criar=true é usado só por scripts de carga e testes.
 */
function referenciaPorNome(PDO $pdo, string $tabela, ?string $nome, bool $criar = false): ?int {
    $rotulos = ['sala' => 'Sala', 'plano_saude' => 'Plano', 'especialidade' => 'Especialidade',
        'categoria_procedimento' => 'Categoria', 'forma_pagamento' => 'Forma de pagamento'];
    if (!isset($rotulos[$tabela])) throw new InvalidArgumentException('Referência inválida');
    $nome = trim($nome ?? '');
    if ($nome === '') return null;
    if ($criar) {
        $stmt = $pdo->prepare("INSERT INTO {$tabela} (nome) VALUES (:nome)
            ON CONFLICT (nome) DO UPDATE SET nome=EXCLUDED.nome RETURNING id");
    } else {
        $stmt = $pdo->prepare("SELECT id FROM {$tabela} WHERE lower(nome)=lower(:nome) ORDER BY id LIMIT 1");
    }
    $stmt->execute(['nome' => $nome]);
    $id = $stmt->fetchColumn();
    if ($id === false) throw new InvalidArgumentException($rotulos[$tabela] . ' "' . cortarTexto($nome, 0, 60) . '" não está cadastrado(a). Escolha uma opção da lista.');
    return (int) $id;
}

function cidadePorNome(PDO $pdo, ?string $nome, ?string $uf): ?int {
    if (!$nome || !$uf) return null;
    $stmt = $pdo->prepare('INSERT INTO cidade (nome, uf) VALUES (:nome, :uf)
        ON CONFLICT (nome, uf) DO UPDATE SET nome=EXCLUDED.nome RETURNING id');
    $stmt->execute(['nome' => trim($nome), 'uf' => strtoupper(trim($uf))]);
    return (int) $stmt->fetchColumn();
}

function gravarCampos(PDO $pdo, string $tabela, array $campos, ?int $id = null): int {
    $permitidas = ['pessoa', 'usuarios', 'pacientes', 'dentistas', 'clinica', 'endereco', 'agendamentos', 'procedimentos'];
    if (!in_array($tabela, $permitidas, true)) throw new InvalidArgumentException('Tabela inválida');
    if (!$campos) return $id ?? throw new InvalidArgumentException('Registro vazio');
    if ($id !== null) {
        $updates = array_map(fn($c) => "{$c}=:{$c}", array_keys($campos));
        $stmt = $pdo->prepare("UPDATE {$tabela} SET " . implode(', ', $updates) . ' WHERE id=:id RETURNING id');
        $stmt->execute($campos + ['id' => $id]);
        if ($stmt->fetchColumn() !== false) return $id;
        throw new InvalidArgumentException('Registro de destino não encontrado na tabela '.$tabela.'.');
    }
    $colunas = array_keys($campos);
    $params = array_map(fn($c) => ':' . $c, $colunas);
    $stmt = $pdo->prepare("INSERT INTO {$tabela} (" . implode(', ', $colunas) . ') VALUES (' . implode(', ', $params) . ') RETURNING id');
    $stmt->execute($campos);
    $novoId = (int) $stmt->fetchColumn();
    return $novoId;
}

function camposPresentes(array $dados, array $nomes): array {
    return array_intersect_key($dados, array_flip($nomes));
}

function upsertTelefone(PDO $pdo, ?int $pessoa, ?int $clinica, ?string $numero, string $finalidade = 'principal'): void {
    $numero = soDigitos($numero);
    if ($numero === '') return; // campo vazio: mantém o telefone que já existe
    if (!preg_match(REGEX_TELEFONE, $numero)) {
        throw new InvalidArgumentException('Telefone inválido. Use DDD + número, por exemplo (67) 99999-0000.');
    }
    $campo = $pessoa !== null ? 'id_pessoa' : 'id_clinica';
    $id = $pessoa ?? $clinica;
    // A pessoa/clínica já está bloqueada pela escrita do registro base.
    $stmt = $pdo->prepare("DELETE FROM telefone WHERE {$campo}=:id AND (finalidade=:finalidade OR numero=:numero)");
    $stmt->execute(['id' => $id, 'finalidade' => $finalidade, 'numero' => $numero]);
    $stmt = $pdo->prepare("INSERT INTO telefone ({$campo}, numero, finalidade) VALUES (:id, :numero, :finalidade)");
    $stmt->execute(['id' => $id, 'numero' => $numero, 'finalidade' => $finalidade]);
}

function gravarPessoa(PDO $pdo, array $dados, ?int $id, bool $contato = true): int {
    $campos = camposPresentes($dados, ['nome', 'cpf', 'rg', 'nascimento', 'sexo']);
    if ($contato && array_key_exists('email', $dados)) $campos['email'] = $dados['email'] ?: null;
    if (isset($campos['cpf'])) $campos['cpf'] = soDigitos($campos['cpf']);
    foreach (['nascimento', 'sexo'] as $campo) {
        if (array_key_exists($campo, $campos) && !$campos[$campo]) $campos[$campo] = null;
    }
    $id = gravarCampos($pdo, 'pessoa', $campos, $id);
    if (array_key_exists('telefone', $dados)) upsertTelefone($pdo, $id, null, $dados['telefone']);
    if (array_key_exists('telefone_emergencia', $dados)) {
        if ($dados['telefone_emergencia']) {
            upsertTelefone($pdo, $id, null, $dados['telefone_emergencia'], 'emergencia');
        } else {
            $pdo->prepare("DELETE FROM telefone WHERE id_pessoa=:id AND finalidade='emergencia'")->execute(['id' => $id]);
        }
    }
    return $id;
}

function gravarEndereco(PDO $pdo, array $dados, ?int $id): ?int {
    if (!array_intersect(['endereco', 'bairro', 'cidade', 'estado', 'cep', 'complemento'], array_keys($dados))) return $id;
    $stmt = $pdo->prepare('SELECT * FROM vw_enderecos WHERE id=:id');
    $stmt->execute(['id' => $id]);
    $dados = array_merge($stmt->fetch() ?: [], $dados);
    $campos = camposPresentes($dados, ['bairro', 'complemento']);
    if (array_key_exists('endereco', $dados)) {
        $partes = explode(', ', $dados['endereco'] ?? '', 2);
        $campos['logradouro'] = $partes[0] ?: null;
        $campos['numero'] = $partes[1] ?? null;
    }
    if (array_key_exists('cep', $dados)) $campos['cep'] = soDigitos($dados['cep']) ?: null;
    $campos['id_cidade'] = cidadePorNome($pdo, $dados['cidade'] ?? null, $dados['estado'] ?? null);
    return gravarCampos($pdo, 'endereco', $campos, $id);
}

function salvarJson(string $arquivo, array $dados): bool {
    $pdo = Database::getConnection();
    $propria=!$pdo->inTransaction();
    try {
        if ($propria) $pdo->beginTransaction();
        else $pdo->exec('SAVEPOINT adapter');
        switch ($arquivo) {
            case 'usuarios.json':
            case 'pacientes.json':
            case 'dentistas.json':
                $tabela = basename($arquivo, '.json');
                foreach ($dados as $registro) {
                    // id ausente, vazio ou 0 (formulário de cadastro novo) = registro novo, nunca UPDATE do id 0.
                    $id = (int)($registro['id'] ?? 0) > 0 ? (int) $registro['id'] : null;
                    $stmt = $pdo->prepare("SELECT * FROM {$tabela} WHERE id=:id FOR UPDATE");
                    $stmt->execute(['id' => $id]);
                    $base = $stmt->fetch() ?: [];
                    // Registro novo sempre cria pessoa nova: nunca grava por cima da pessoa de outro usuário.
                    $idPessoa = $base['id_pessoa'] ?? null;
                    $idPessoa = gravarPessoa($pdo, $registro, $idPessoa, $tabela !== 'usuarios');
                    $campos = ['id_pessoa' => $idPessoa];
                    if ($tabela === 'usuarios') {
                        // Senha não passa por aqui: só alterarSenhaAtual/redefinirSenha gravam o hash.
                        $campos += camposPresentes($registro, ['email', 'tipo', 'status']);
                    } elseif ($tabela === 'pacientes') {
                        $campos += camposPresentes($registro, ['estado_civil', 'profissao', 'numero_carteirinha', 'observacoes']);
                        $campos['id_endereco'] = gravarEndereco($pdo, $registro, $base['id_endereco'] ?? null);
                        if (array_key_exists('plano', $registro)) {
                            $campos['id_plano'] = referenciaPorNome($pdo, 'plano_saude', $registro['plano'] ?: 'Particular');
                        }
                    } else {
                        $campos += camposPresentes($registro, ['titulacao', 'faculdade', 'ano_formatura', 'admissao', 'cor']);
                        if (array_key_exists('sala', $registro)) $campos['id_sala'] = referenciaPorNome($pdo, 'sala', $registro['sala']);
                        if (array_key_exists('especialidade', $registro)) $campos['id_especialidade'] = referenciaPorNome($pdo, 'especialidade', $registro['especialidade']);
                        if (isset($registro['cro'])) {
                            if (!preg_match('/^CRO-([A-Z]{2})\s+(\d+)$/', $registro['cro'], $cro)) throw new InvalidArgumentException('CRO inválido');
                            $campos['cro_uf'] = $cro[1]; $campos['cro_numero'] = $cro[2];
                        }
                    }
                    if (isset($registro['status'])) $campos['status'] = strtolower($registro['status']);
                    if (!$base && isset($registro['criado_em'])) $campos['criado_em'] = $registro['criado_em'];
                    $id = gravarCampos($pdo, $tabela, $campos, $id);
                    if ($tabela === 'pacientes') {
                        foreach (['alergias' => 'alergia', 'medicamentos_em_uso' => 'medicamento', 'doencas_preexistentes' => 'condicao'] as $campo => $tipo) {
                            if (!array_key_exists($campo, $registro)) continue;
                            $descricoes = array_values(array_unique(array_filter(array_map('trim', explode(',', $registro[$campo] ?? '')))));
                            $stmt = $pdo->prepare('SELECT descricao FROM historico_clinico WHERE id_paciente=:id AND tipo=:tipo ORDER BY id');
                            $stmt->execute(['id' => $id, 'tipo' => $tipo]);
                            if ($stmt->fetchAll(PDO::FETCH_COLUMN) === $descricoes) continue;
                            $pdo->prepare('DELETE FROM historico_clinico WHERE id_paciente=:id AND tipo=:tipo')->execute(['id' => $id, 'tipo' => $tipo]);
                            $stmt = $pdo->prepare('INSERT INTO historico_clinico (id_paciente,tipo,descricao) VALUES (:id,:tipo,:descricao)');
                            foreach ($descricoes as $descricao) $stmt->execute(['id' => $id, 'tipo' => $tipo, 'descricao' => $descricao]);
                        }
                    } elseif ($tabela === 'dentistas') {
                        if (isset($registro['horarios'], $registro['dias_atendimento'])) {
                            $pdo->prepare('DELETE FROM disponibilidade_dentista WHERE id_dentista=:id')->execute(['id' => $id]);
                            $stmt = $pdo->prepare('INSERT INTO disponibilidade_dentista (id_dentista,id_dia_semana,hora)
                                SELECT :id,id,CAST(:hora AS time) FROM dia_semana WHERE nome=:nome ON CONFLICT DO NOTHING');
                            foreach ($registro['dias_atendimento'] as $dia) {
                                foreach ($registro['horarios'] as $hora) $stmt->execute(['id' => $id, 'nome' => $dia, 'hora' => $hora]);
                            }
                        }
                        if (isset($registro['procedimentos'])) {
                            $stmt = $pdo->prepare('INSERT INTO dentista_procedimentos (id_dentista,id_procedimento)
                                SELECT :id,id FROM procedimentos WHERE nome=:nome ON CONFLICT DO NOTHING');
                            foreach ($registro['procedimentos'] as $nome) $stmt->execute(['id' => $id, 'nome' => $nome]);
                            $stmt = $pdo->prepare('SELECT dp.id_procedimento, p.nome FROM dentista_procedimentos dp
                                JOIN procedimentos p ON p.id=dp.id_procedimento WHERE dp.id_dentista=:id');
                            $stmt->execute(['id' => $id]);
                            foreach ($stmt->fetchAll() as $proc) {
                                if (!in_array($proc['nome'], $registro['procedimentos'], true)) {
                                    $pdo->prepare('DELETE FROM dentista_procedimentos WHERE id_dentista=:id AND id_procedimento=:proc')
                                        ->execute(['id' => $id, 'proc' => $proc['id_procedimento']]);
                                }
                            }
                        }
                    }
                }
                break;
            case 'clinica.json':
                $base = $pdo->query('SELECT * FROM clinica WHERE id=1 FOR UPDATE')->fetch() ?: [];
                $campos = camposPresentes($dados, ['nome', 'razao_social', 'email', 'logo_texto', 'cor_primaria', 'cor_destaque', 'limite_agendamentos_dia', 'antecedencia_min_dias']);
                if (isset($dados['cnpj'])) $campos['cnpj'] = cnpjNormalizado($dados['cnpj']);
                $campos['id_endereco'] = gravarEndereco($pdo, $dados, $base['id_endereco'] ?? null);
                gravarCampos($pdo, 'clinica', $campos, 1);
                if (isset($dados['telefone'])) upsertTelefone($pdo, null, 1, $dados['telefone']);
                break;
            default:
                throw new InvalidArgumentException('Fonte de escrita desconhecida');
        }
        if ($propria) $pdo->commit();
        else $pdo->exec('RELEASE SAVEPOINT adapter');
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            if ($propria) $pdo->rollBack();
            else $pdo->exec('ROLLBACK TO SAVEPOINT adapter');
        }
        // A exceção sobe: quem chama mostra a mensagem certa (mensagemOperacao) em vez de um "confira os dados" genérico.
        throw $e;
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
