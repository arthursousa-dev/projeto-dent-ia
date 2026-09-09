<?php
// includes/bootstrap_sessao.php
//
// Endurece os cookies de sessão antes de iniciar a sessão:
// - httponly: JavaScript não consegue ler o cookie (mitiga roubo via XSS)
// - samesite=Lax: navegador não envia o cookie em requisições
//   disparadas por outros sites (mitiga CSRF de origem cruzada)
// - secure: só envia o cookie em conexão HTTPS (ativado automaticamente
//   quando a aplicação está atrás de HTTPS; em desenvolvimento local
//   sem HTTPS, o PHP simplesmente ignora essa flag)

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
