<?php
// Roteador do servidor embutido (php -S), que ignora o .htaccess.
// Lista do que pode ser servido (não do que é proibido), conferida no caminho CRU da requisição,
// e os estáticos são entregues por este arquivo: o caminho validado é exatamente o caminho servido.
// (Antes, "//.env/js/app.js" passava pela validação e o php -S entregava o .env; "%5c" furava no Windows.)
ini_set('display_errors', '0');
ini_set('expose_php', '0');
header_remove('X-Powered-By');

$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$caminho = explode('?', $uri, 2)[0];
if ($caminho === '/') $caminho = '/index.php';

// Só letras, números, "_", "-", "." e uma barra entre partes. Sem "%", "\", "//" nem "..".
$permitido = !str_contains($caminho, '..') && (
    (preg_match('#^/[a-z0-9_]+\.php$#', $caminho) && $caminho !== '/router.php')
    || preg_match('#^/(css|js)/[a-z0-9_\-]+\.(css|js)$#i', $caminho)
    || preg_match('#^/[a-z0-9_\-]+\.(ico|png|svg|webp)$#i', $caminho)
);
$arquivo = __DIR__ . $caminho;
if (!$permitido || !is_file($arquivo)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");

if (str_ends_with($caminho, '.php')) {
    $_SERVER['SCRIPT_NAME'] = $caminho;
    $_SERVER['SCRIPT_FILENAME'] = $arquivo;
    $_SERVER['PHP_SELF'] = $caminho;
    chdir(__DIR__);
    require $arquivo;
    return true;
}

// Estáticos (css, js, imagens) entregues aqui mesmo, nunca pelo php -S.
$tipos = ['css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'ico' => 'image/x-icon',
          'png' => 'image/png', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
header('Content-Type: ' . $tipos[strtolower(pathinfo($arquivo, PATHINFO_EXTENSION))]);
header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: public, max-age=300');
readfile($arquivo);
return true;
