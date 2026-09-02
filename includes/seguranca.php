<?php
// includes/seguranca.php
// Funções de segurança: hash de senha e proteção CSRF.

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
 * Imprime o campo hidden com o token, pronto pra colar dentro do <form>.
 */
function csrfCampo(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
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
