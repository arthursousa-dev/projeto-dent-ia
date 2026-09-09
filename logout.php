<?php
require_once __DIR__ . '/includes/bootstrap_sessao.php';
$perfil = $_SESSION['perfil'] ?? 'cliente';
$logins = [
    'cliente'       => 'login_paciente.php',
    'recepcionista' => 'login_recepcionista.php',
    'dentista'      => 'login_dentista.php',
    'dono'          => 'login_dono.php',
];
$destino = $logins[$perfil] ?? 'index.php';
session_destroy();
header('Location: ' . $destino);
exit;
