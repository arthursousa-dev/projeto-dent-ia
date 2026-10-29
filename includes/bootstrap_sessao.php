<?php
// Primeiro include de toda página: erros fora da tela, sessão segura e entrada básica validada.

// Erros vão para o log, nunca para a tela (o caminho do servidor e o SQL não podem vazar).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
header_remove('X-Powered-By');

/** Página de erro amigável, com o layout do sistema, para respostas que não chegam à tela normal. */
function paginaErro(int $codigo, string $titulo, string $mensagem): never {
    if (!headers_sent()) http_response_code($codigo);
    if (str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'api_')) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    $t = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . $t . ' — DENT IA</title><link rel="stylesheet" href="css/style.css"></head>'
        . '<body><main class="pagina-erro" id="conteudo-principal"><h1>' . $t . '</h1><p>' . $m . '</p>'
        . '<p><a class="btn btn-primario" href="javascript:history.back()">Voltar</a> <a class="btn btn-fantasma" href="index.php">Início</a></p></main></body></html>';
    exit;
}

// Qualquer falha não tratada vira uma página genérica; o detalhe fica só no log, com um código para o suporte.
set_exception_handler(function (Throwable $e): void {
    $codigo = bin2hex(random_bytes(4));
    error_log('[DENTIA][' . $codigo . '] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    paginaErro(500, 'Algo deu errado', 'Não foi possível abrir esta página. Tente de novo; se continuar, informe ao suporte o código ' . $codigo . '.');
});

// Formulários e links do sistema só mandam texto. Lista (campo[]=...) só onde o formulário usa,
// e texto precisa ser UTF-8 válido e de tamanho razoável; o resto é recusado antes de chegar às regras.
(function (): void {
    $listasPermitidas = ['procedimentos', 'abertura', 'fechamento'];
    foreach (['GET' => $_GET, 'POST' => $_POST] as $valores) {
        foreach ($valores as $chave => $valor) {
            $itens = is_array($valor) ? $valor : [$valor];
            if (is_array($valor) && !in_array($chave, $listasPermitidas, true)) {
                paginaErro(400, 'Dados inválidos', 'O formulário enviou um campo em formato inesperado. Recarregue a página e tente de novo.');
            }
            foreach ($itens as $item) {
                if (!is_string($item) || preg_match('//u', $item) !== 1 || strlen($item) > 20000) {
                    paginaErro(400, 'Dados inválidos', 'Algum campo tem caracteres inválidos ou texto longo demais. Confira e tente de novo.');
                }
            }
        }
    }
})();

// Cookie de sessão: httponly + samesite=Lax + secure (quando HTTPS).
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (getenv('APP_PROXY_CONFIAVEL') === 'true' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Encerra a sessão após 60 minutos sem uso: computador da recepção é compartilhado e há dados clínicos.
const DENTIA_INATIVIDADE_SEGUNDOS = 3600;
if (isset($_SESSION['perfil'], $_SESSION['ultima_atividade']) && time() - $_SESSION['ultima_atividade'] > DENTIA_INATIVIDADE_SEGUNDOS) {
    $_SESSION = ['sessao_expirada' => true];
    session_regenerate_id(true);
}
$_SESSION['ultima_atividade'] = time();
