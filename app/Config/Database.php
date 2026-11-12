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

    /** Lê .env local sem dependências externas; variáveis já definidas têm precedência. */
    private static function carregarAmbiente(): void
    {
        $arquivo = dirname(__DIR__, 2) . '/.env';
        if (!is_readable($arquivo)) return;
        foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) continue;
            [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $chave) || getenv($chave) !== false) continue;
            $valor = trim($valor, "\"'");
            putenv($chave . '=' . $valor);
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$instancia === null) {
            self::carregarAmbiente();
            if (!extension_loaded('pdo_pgsql')) {
                throw new \RuntimeException('A extensão pdo_pgsql não está habilitada no PHP. Ative php-pgsql.');
            }
            // Railway: variáveis DB_* explícitas, URL ou PG* padrão.
            $url = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: '';
            $parts = $url !== '' ? parse_url($url) : [];
            if ($url !== '' && $parts === false) {
                throw new \RuntimeException('DATABASE_URL inválida.');
            }
            $host = getenv('DB_HOST') ?: ($parts['host'] ?? getenv('PGHOST') ?: 'localhost');
            $port = getenv('DB_PORT') ?: ($parts['port'] ?? getenv('PGPORT') ?: '5432');
            $nome = getenv('DB_NAME') ?: (isset($parts['path']) ? ltrim($parts['path'], '/') : (getenv('PGDATABASE') ?: 'dentia'));
            $user = getenv('DB_USER') ?: (isset($parts['user']) ? rawurldecode($parts['user']) : (getenv('PGUSER') ?: 'postgres'));
            $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (isset($parts['pass']) ? rawurldecode($parts['pass']) : (getenv('PGPASSWORD') ?: ''));
            $query = [];
            if (!empty($parts['query'])) parse_str($parts['query'], $query);
            $sslmode = getenv('DB_SSLMODE') ?: ($query['sslmode'] ?? '');
            foreach (['host'=>$host,'port'=>$port,'dbname'=>$nome] as $key=>$value) {
                if (str_contains((string)$value, ';') || str_contains((string)$value, "\\")) {
                    throw new \RuntimeException('Configuração de banco inválida: ' . $key);
                }
            }
            $dsn = "pgsql:host={$host};port={$port};dbname={$nome}";
            if ($sslmode !== '') {
                if (!in_array($sslmode, ['disable','allow','prefer','require','verify-ca','verify-full'], true)) {
                    throw new \RuntimeException('DB_SSLMODE inválido.');
                }
                $dsn .= ";sslmode={$sslmode}";
            }

            try {
                self::$instancia = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                // CURRENT_DATE/now() no mesmo fuso do PHP (includes/functions.php), mesmo se o servidor estiver em UTC.
                self::$instancia->exec("SET TIME ZONE 'America/Cuiaba'");
            } catch (PDOException $e) {
                throw new \RuntimeException('Não foi possível conectar ao PostgreSQL. Confira o arquivo .env e se o serviço do banco está ativo.', 0, $e);
            }
        }

        return self::$instancia;
    }
}
