<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;

final class AdaptadorPersistenciaTest extends TestCase
{
    private PDO $pdo;
    private string $cpfTeste = '000.000.000-00';

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->limparPacienteTeste();

        $this->pdo->prepare(
            "INSERT INTO pacientes (nome, cpf, email, telefone, nascimento, status)
             VALUES (:nome, :cpf, :email, :telefone, :nascimento, 'ativo')"
        )->execute([
            ':nome' => 'Paciente de Teste',
            ':cpf' => $this->cpfTeste,
            ':email' => 'paciente.teste@dentia.local',
            ':telefone' => '(67) 90000-0000',
            ':nascimento' => '1990-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        $this->limparPacienteTeste();
    }

    private function limparPacienteTeste(): void
    {
        $this->pdo->prepare('DELETE FROM pacientes WHERE cpf = :cpf')->execute([':cpf' => $this->cpfTeste]);
    }

    private function buscarPacienteTeste(array $pacientes): ?array
    {
        foreach ($pacientes as $p) {
            if ($p['cpf'] === $this->cpfTeste) {
                return $p;
            }
        }
        return null;
    }

    public function test_lerJson_devolve_paciente_gravado_direto_no_banco(): void
    {
        $pacientes = lerJson('pacientes.json');
        $encontrado = $this->buscarPacienteTeste($pacientes);

        $this->assertNotNull($encontrado, 'paciente inserido via SQL deveria aparecer em lerJson()');
        $this->assertSame('Paciente de Teste', $encontrado['nome']);
        $this->assertSame('Ativo', $encontrado['status'], 'status deveria vir capitalizado, como no JSON original');
    }

    public function test_salvarJson_persiste_alteracao_de_telefone(): void
    {
        $pacientes = lerJson('pacientes.json');
        foreach ($pacientes as &$p) {
            if ($p['cpf'] === $this->cpfTeste) {
                $p['telefone'] = '(67) 99999-8888';
            }
        }
        unset($p);

        $resultado = salvarJson('pacientes.json', $pacientes);
        $this->assertTrue($resultado);

        $linha = $this->pdo->prepare('SELECT telefone FROM pacientes WHERE cpf = :cpf');
        $linha->execute([':cpf' => $this->cpfTeste]);

        $this->assertSame('(67) 99999-8888', $linha->fetchColumn());
    }

    public function test_lerJsonObjeto_devolve_configuracao_da_clinica(): void
    {
        $clinica = lerJsonObjeto('clinica.json');

        $this->assertArrayHasKey('nome', $clinica);
        $this->assertArrayHasKey('cnpj', $clinica);
    }

    public function test_lerJsonObjeto_devolve_historico_de_faturamento_como_lista(): void
    {
        $faturamento = lerJsonObjeto('faturamento.json');

        $this->assertArrayHasKey('historico_mensal', $faturamento);
        $this->assertIsArray($faturamento['historico_mensal']);
    }

    public function test_arquivo_desconhecido_devolve_array_vazio(): void
    {
        $this->assertSame([], lerJson('arquivo_que_nao_existe.json'));
    }
}
