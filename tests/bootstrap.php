<?php
// tests/bootstrap.php
//
// Testes de integração rodam contra um banco de testes dedicado —
// nunca contra o banco de desenvolvimento/produção. Configure com:
//
//   DB_NAME=dentia_test vendor/bin/phpunit
//
// (ou copie database/schema.sql para um banco "dentia_test" antes
// de rodar). Se DB_NAME não apontar para um banco terminado em
// "_test", os testes recusam rodar, para evitar apagar dados reais
// por engano.

if (!str_ends_with(getenv('DB_NAME') ?: '', '_test')) {
    fwrite(STDERR, "\nDB_NAME precisa apontar para um banco de testes (ex.: dentia_test).\n");
    fwrite(STDERR, "Rode: DB_NAME=dentia_test vendor/bin/phpunit\n\n");
    exit(1);
}

require_once __DIR__ . '/../includes/functions.php';
