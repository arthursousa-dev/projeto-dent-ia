<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;

final class AdaptadorPersistenciaTest extends TestCase
{
    private PDO $pdo;
    private string $cpfTeste = '529.982.247-25';

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->limparPacienteTeste();

        $stmt = $this->pdo->prepare("INSERT INTO pessoa (nome, cpf, email, nascimento)
            VALUES ('Paciente de Teste', :cpf, 'paciente.teste@dentia.local', '1990-01-01') RETURNING id");
        $stmt->execute(['cpf' => soDigitos($this->cpfTeste)]);
        $id = $stmt->fetchColumn();
        $this->pdo->prepare('INSERT INTO pacientes (id_pessoa) VALUES (:id)')->execute(['id' => $id]);
        upsertTelefone($this->pdo, (int) $id, null, '(67) 90000-0000');
    }

    protected function tearDown(): void
    {
        $this->limparPacienteTeste();
    }

    private function limparPacienteTeste(): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM pessoa WHERE cpf=:cpf');
        $stmt->execute(['cpf' => soDigitos($this->cpfTeste)]);
        $id = $stmt->fetchColumn();
        if (!$id) return;
        $this->pdo->prepare('DELETE FROM historico_clinico WHERE id_paciente IN (SELECT id FROM pacientes WHERE id_pessoa=:id)')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM pacientes WHERE id_pessoa=:id')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM telefone WHERE id_pessoa=:id')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM pessoa WHERE id=:id')->execute(['id' => $id]);
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

        $linha = $this->pdo->prepare('SELECT telefone FROM vw_pacientes WHERE cpf = :cpf');
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
