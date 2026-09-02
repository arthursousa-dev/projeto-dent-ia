<?php
// Leitura e escrita segura de arquivos JSON (com flock) usados como base de dados.

define('DADOS_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dados' . DIRECTORY_SEPARATOR);

/**
 * Lê um arquivo JSON e retorna um array.
 * Retorna [] se o arquivo não existir ou se o JSON for inválido.
 */
function lerJson(string $arquivo): array {
    $caminho = DADOS_DIR . $arquivo;
    if (!file_exists($caminho)) return [];
    $conteudo = file_get_contents($caminho);
    if ($conteudo === false) return [];
    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : [];
}

/**
 * Lê um arquivo JSON que contém um objeto (não array na raiz).
 * Retorna [] se inválido.
 */
function lerJsonObjeto(string $arquivo): array {
    $caminho = DADOS_DIR . $arquivo;
    if (!file_exists($caminho)) return [];
    $conteudo = file_get_contents($caminho);
    if ($conteudo === false) return [];
    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : [];
}

/**
 * Salva dados em um arquivo JSON com bloqueio exclusivo (flock).
 * Garante integridade mesmo com múltiplos acessos simultâneos.
 * Retorna true em caso de sucesso, false em caso de erro.
 */
function salvarJson(string $arquivo, array $dados): bool {
    $caminho = DADOS_DIR . $arquivo;

    // Garante que a pasta existe
    if (!is_dir(DADOS_DIR)) {
        mkdir(DADOS_DIR, 0755, true);
    }

    $handle = fopen($caminho, 'c');
    if (!$handle) return false;

    if (flock($handle, LOCK_EX)) {         // Bloqueia exclusivamente
        ftruncate($handle, 0);             // Limpa conteúdo anterior
        rewind($handle);                   // Volta ao início
        $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);           // Libera o bloqueio
    } else {
        fclose($handle);
        return false;
    }

    fclose($handle);
    return true;
}

/**
 * Retorna o próximo ID disponível em um array de registros.
 */
function proximoId(array $registros): int {
    if (empty($registros)) return 1;
    $ids = array_column($registros, 'id');
    return empty($ids) ? 1 : (max($ids) + 1);
}

/**
 * Busca um registro pelo valor de um campo específico.
 * Ex: buscarPorCampo($usuarios, 'email', 'joao@email.com')
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
 * Ex: filtrarPorCampo($agendamentos, 'status', 'Confirmado')
 */
function filtrarPorCampo(array $registros, string $campo, mixed $valor): array {
    return array_values(array_filter($registros, function($item) use ($campo, $valor) {
        return isset($item[$campo]) && $item[$campo] == $valor;
    }));
}

/**
 * Atualiza um registro em um array pelo ID.
 * Retorna o array atualizado.
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
 * Remove um registro pelo ID.
 * Retorna o array sem o registro.
 */
function removerRegistro(array $registros, int $id): array {
    return array_values(array_filter($registros, fn($item) => $item['id'] != $id));
}

/**
 * Verifica se um arquivo JSON existe na pasta de dados.
 */
function arquivoExiste(string $arquivo): bool {
    return file_exists(DADOS_DIR . $arquivo);
}

/**
 * Retorna o tamanho de um arquivo JSON em bytes.
 */
function tamanhoArquivo(string $arquivo): int {
    $caminho = DADOS_DIR . $arquivo;
    return file_exists($caminho) ? filesize($caminho) : 0;
}
