<?php

namespace App\Config;

use PDO;
use PDOException;

/**
 * Conexão PDO única (Singleton) com PostgreSQL.
 * Credenciais vêm de variáveis de ambiente, com fallback pra
 * desenvolvimento local.
 */
class Database
{
    private static ?PDO $instancia = null;

    public static function getConnection(): PDO
    {
        if (self::$instancia === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $nome = getenv('DB_NAME') ?: 'dentia';
            $user = getenv('DB_USER') ?: 'postgres';
            $pass = getenv('DB_PASS') ?: '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$nome}";

            try {
                self::$instancia = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die('Não foi possível conectar ao banco de dados.');
            }
        }

        return self::$instancia;
    }
}
