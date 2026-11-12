<?php
// includes/seguranca.php
// Proteção CSRF, envio único de formulário e mensagens entre redirecionamentos.

/**
 * Gera (ou reaproveita) o token CSRF da sessão atual.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campos ocultos de todo formulário POST: o token CSRF e, por padrão, um código de envio único.
 * O código de envio impede que F5, "voltar" ou duplo clique repitam a mesma operação.
 * Formulários que só leem (ex.: baixar relatório) usam csrfCampo(false), pois podem ser enviados de novo.
 */
function csrfCampo(bool $envioUnico = true): string {
    $campos = '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
    if (!$envioUnico) return $campos . '<input type="hidden" name="envio" value="livre">';
    $codigo = bin2hex(random_bytes(12));
    $_SESSION['envios'][$codigo] = time();
    // Guarda só os mais recentes: uma página grande (agenda) gera muitos formulários.
    if (count($_SESSION['envios']) > 3000) $_SESSION['envios'] = array_slice($_SESSION['envios'], -2000, null, true);
    return $campos . '<input type="hidden" name="envio" value="' . $codigo . '">';
}

/**
 * Valida o token recebido no POST contra o da sessão.
 * Usa hash_equals() para evitar timing attack.
 */
function csrfValido(?string $tokenRecebido): bool {
    return !empty($_SESSION['csrf_token'])
        && !empty($tokenRecebido)
        && hash_equals($_SESSION['csrf_token'], $tokenRecebido);
}

/** Consome o código de envio único. Falso quando o formulário já foi usado ou expirou. "livre" = formulário que pode repetir. */
function consumirEnvio(string $codigo): bool {
    if ($codigo === 'livre') return in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['logout.php', 'dono_relatorios.php'], true);
    if (!isset($_SESSION['envios'][$codigo])) return false;
    unset($_SESSION['envios'][$codigo]);
    return true;
}

/** Guarda uma mensagem para a próxima página (padrão POST → redirecionamento → GET). */
function definirFlash(string $tipo, string $mensagem): void {
    $_SESSION['flash'][] = ['tipo' => in_array($tipo, ['erro', 'aviso'], true) ? $tipo : 'sucesso', 'mensagem' => $mensagem];
}

/** Mostra e apaga as mensagens guardadas. */
function exibirFlash(): string {
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $papel = $f['tipo'] === 'sucesso' ? 'role="status"' : 'role="alert"';
        $icone = $f['tipo'] === 'sucesso' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        $html .= '<div class="alerta alerta-' . $f['tipo'] . '" ' . $papel . '><i class="bi ' . $icone . '" aria-hidden="true"></i> '
            . htmlspecialchars($f['mensagem'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/** Redireciona com 303 (o navegador faz GET) para um caminho deste sistema. */
function redirecionar(string $destino, ?string $sucesso = null, ?string $erro = null): never {
    if ($sucesso !== null) definirFlash('sucesso', $sucesso);
    if ($erro !== null) definirFlash('erro', $erro);
    // Só caminhos relativos deste sistema: impede redirecionamento para outro site.
    if (!preg_match('#^/?[a-z0-9_]+\.php(\?[^\r\n]*)?$#i', $destino)) $destino = 'index.php';
    header('Location: ' . $destino, true, 303);
    exit;
}

/** A página atual (arquivo + query), para voltar a ela depois de um POST. */
function paginaAtual(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/index.php';
    $caminho = ltrim((string)parse_url($uri, PHP_URL_PATH), '/') ?: 'index.php';
    $query = (string)parse_url($uri, PHP_URL_QUERY);
    return $caminho . ($query !== '' ? '?' . $query : '');
}
