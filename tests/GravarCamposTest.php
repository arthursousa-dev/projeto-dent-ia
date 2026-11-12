<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use InvalidArgumentException;
use PDO;

/** P2.7: gravarCampos só aceita colunas que existem na tabela; nome de coluna nunca vira SQL. */
final class GravarCamposTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    private function recusa(string $tabela, array $campos, ?int $id = null): void
    {
        try {
            gravarCampos($this->pdo, $tabela, $campos, $id);
            $this->fail('Aceitou: ' . implode(', ', array_keys($campos)));
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Campo inválido', $e->getMessage());
        }
    }

    public function test_coluna_valida_grava(): void
    {
        $id = gravarCampos($this->pdo, 'pessoa', ['nome' => 'Coluna Válida', 'cpf' => '39053344705']);
        gravarCampos($this->pdo, 'pessoa', ['nome' => 'Coluna Válida 2'], $id);
        $this->assertSame('Coluna Válida 2', $this->pdo->query("SELECT nome FROM pessoa WHERE id=$id")->fetchColumn());
    }

    public function test_nome_de_coluna_com_sql_e_recusado_antes_de_chegar_ao_banco(): void
    {
        $id = gravarCampos($this->pdo, 'pessoa', ['nome' => 'Alvo', 'cpf' => '39053344705']);
        $this->recusa('pessoa', ["nome='invadido' WHERE true --" => 'x'], $id);
        $this->recusa('pessoa', ['nome) VALUES (\'x\'); DROP TABLE pessoa; --' => 'x']);
        $this->recusa('pessoa', ['coluna_que_nao_existe' => 'x'], $id);
        $this->assertSame('Alvo', $this->pdo->query("SELECT nome FROM pessoa WHERE id=$id")->fetchColumn());
    }

    public function test_senha_e_bloqueio_nao_passam_por_aqui(): void
    {
        $id = (int)$this->pdo->query('SELECT id FROM usuarios ORDER BY id LIMIT 1')->fetchColumn();
        $this->recusa('usuarios', ['senha_hash' => 'qualquer'], $id);
        $this->recusa('usuarios', ['tentativas_login' => 0], $id);
        $this->recusa('usuarios', ['bloqueado_ate' => null], $id);
    }
}
