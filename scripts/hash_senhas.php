<?php
// scripts/hash_senhas.php
// Roda UMA VEZ pra converter as senhas em texto puro de dados/usuarios.json
// em hashes bcrypt (password_hash). Depois de rodar, apague este arquivo
// ou pelo menos tire ele do ar em produção.
//
// Uso: php scripts/hash_senhas.php

require_once __DIR__ . '/../includes/db.php';

$usuarios = lerJson('usuarios.json');
$alterados = 0;

foreach ($usuarios as &$u) {
    // Se já começa com $2y$ é um hash bcrypt válido — não mexe de novo.
    if (!str_starts_with($u['senha'], '$2y$')) {
        $u['senha'] = password_hash($u['senha'], PASSWORD_DEFAULT);
        $alterados++;
    }
}
unset($u);

if ($alterados > 0) {
    salvarJson('usuarios.json', $usuarios);
    echo "OK: {$alterados} senha(s) convertida(s) para hash em dados/usuarios.json\n";
} else {
    echo "Nada a fazer — todas as senhas já estão em hash.\n";
}
