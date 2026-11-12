<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;
use PDOException;

/**
 * P0.6: database/RECONSTRUIR_RAILWAY.sql não pode apagar um banco por engano.
 * Tudo roda dentro de transação com rollback: no Postgres o DROP SCHEMA é
 * transacional, então o banco de teste volta inteiro mesmo se a trava falhar.
 */
final class ScriptDestrutivoTest extends TestCase
{
    private PDO $pdo;
    private string $sql;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->sql = file_get_contents(__DIR__ . '/../database/RECONSTRUIR_RAILWAY.sql');
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
    }

    private function tabelasNoPublic(): int
    {
        return (int)$this->pdo->query("SELECT count(*) FROM pg_tables WHERE schemaname='public'")->fetchColumn();
    }

    public function test_sem_liberacao_o_script_aborta_e_nao_apaga_nada(): void
    {
        $antes = $this->tabelasNoPublic();
        $this->assertGreaterThan(0, $antes);

        $abortou = false;
        $this->pdo->exec('SAVEPOINT antes_do_script');
        try {
            $this->pdo->exec($this->sql);
        } catch (PDOException $e) {
            $abortou = true;
            $this->assertStringContainsString('BLOQUEADO', $e->getMessage());
            $this->pdo->exec('ROLLBACK TO SAVEPOINT antes_do_script');
        }

        $this->assertTrue($abortou, 'O script rodou sem trava num banco que não é de demonstração.');
        $this->assertSame($antes, $this->tabelasNoPublic());
    }

    public function test_com_liberacao_explicita_o_script_reconstroi_o_schema(): void
    {
        $this->pdo->exec("SET LOCAL dentia.permitir_reset = 'SIM'");
        $this->pdo->exec($this->sql);

        $this->assertSame(0, $this->tabelasNoPublic());
    }
}
