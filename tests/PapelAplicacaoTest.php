<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;
use PDOException;

/**
 * P0.2: o papel dentia_app (o que a aplicação usa) lê e grava dados, mas não muda
 * a estrutura do banco nem toca no registro de auditoria. Roda com SET LOCAL ROLE
 * dentro de transação: vale tanto conectado como postgres quanto como dentia_app.
 */
final class PapelAplicacaoTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
        $this->pdo->exec('SET LOCAL ROLE dentia_app');
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    /** O comando precisa ser recusado por falta de privilégio (SQLSTATE 42501). */
    private function negado(string $sql): void
    {
        $this->pdo->exec('SAVEPOINT tentativa');
        try {
            $this->pdo->exec($sql);
            $this->fail('dentia_app conseguiu executar: ' . $sql);
        } catch (PDOException $e) {
            $this->assertSame('42501', (string)$e->getCode(), $sql . ' => ' . $e->getMessage());
        } finally {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT tentativa');
        }
    }

    public function test_papel_nao_tem_poderes_de_administrador(): void
    {
        $papel = $this->pdo->query("SELECT rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls
            FROM pg_roles WHERE rolname = 'dentia_app'")->fetch();
        foreach ($papel as $poder => $valor) $this->assertFalse((bool)$valor, $poder);
        $this->assertSame('dentia_app', $this->pdo->query('SELECT current_user')->fetchColumn());
    }

    public function test_nao_altera_a_estrutura_do_banco(): void
    {
        $this->negado('DROP TABLE pacientes CASCADE');
        $this->negado('ALTER TABLE pacientes ADD COLUMN invasao INT');
        $this->negado('TRUNCATE pacientes CASCADE');
        $this->negado('CREATE TABLE invasao (id INT)');
        $this->negado('ALTER TABLE pacientes DISABLE TRIGGER auditoria_banco_registro');
        $this->negado('DROP FUNCTION registrar_auditoria() CASCADE');
    }

    public function test_nao_apaga_nem_reescreve_a_auditoria(): void
    {
        $this->negado('DELETE FROM auditoria_banco');
        $this->negado("UPDATE auditoria_banco SET usuario = 'ninguem'");
        $this->negado("INSERT INTO auditoria_banco (tabela, operacao) VALUES ('pacientes', 'DELETE')");
        $this->negado('TRUNCATE auditoria_banco');
        $this->negado('DELETE FROM schema_migrations');
    }

    public function test_escrita_normal_funciona_e_continua_auditada(): void
    {
        $id = $this->pdo->query("INSERT INTO sala (nome) VALUES ('Sala do teste de papel') RETURNING id")->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT count(*) FROM auditoria_banco WHERE tabela = 'sala' AND operacao = 'INSERT' AND id_registro = :id");
        $stmt->execute(['id' => (string)$id]);
        $this->assertSame(1, (int)$stmt->fetchColumn());

        $this->assertSame(1, $this->pdo->exec("UPDATE sala SET nome = 'Sala do teste de papel 2' WHERE id = " . (int)$id));
        $this->assertSame(1, $this->pdo->exec('DELETE FROM sala WHERE id = ' . (int)$id));
    }
}
