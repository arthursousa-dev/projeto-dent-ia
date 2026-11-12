<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;
use PDOException;

/**
 * P1.1, P1.3 e P1.4: o prontuário guarda o que constava antes, a evolução clínica não é
 * alterada nem apagada, e o consentimento LGPD e as leituras ficam registrados sem que a
 * aplicação possa apagá-los. Tudo em transação com rollback.
 */
final class ProntuarioRastreavelTest extends TestCase
{
    private PDO $pdo;
    private int $paciente;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
        $pessoa = $this->pdo->query("INSERT INTO pessoa (nome, cpf) VALUES ('Paciente Rastreável', '39053344705') RETURNING id")->fetchColumn();
        $this->paciente = (int)$this->pdo->query("INSERT INTO pacientes (id_pessoa) VALUES ($pessoa) RETURNING id")->fetchColumn();
        $_SESSION = ['perfil' => 'dentista', 'email' => 'dentista.teste@dentia.local'];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        marcarUsuarioNoBanco('dentista:dentista.teste@dentia.local');
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
        $_SESSION = [];
    }

    private function recusado(string $sql, string $esperado): void
    {
        $this->pdo->exec('SAVEPOINT tentativa');
        try {
            $this->pdo->exec($sql);
            $this->fail('Foi aceito: ' . $sql);
        } catch (PDOException $e) {
            $this->assertStringContainsString($esperado, $e->getCode() . ' ' . $e->getMessage(), $sql);
        } finally {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT tentativa');
        }
    }

    public function test_editar_alergia_preserva_a_versao_anterior(): void
    {
        salvarJson('pacientes.json', [['id' => $this->paciente, 'alergias' => 'Dipirona, Látex']]);
        salvarJson('pacientes.json', [['id' => $this->paciente, 'alergias' => 'Látex, Penicilina']]);

        $atuais = $this->pdo->query("SELECT descricao FROM historico_clinico WHERE id_paciente={$this->paciente} AND tipo='alergia' ORDER BY descricao")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Látex', 'Penicilina'], $atuais);

        $antes = historicoRetificado($this->paciente);
        $this->assertCount(1, $antes, 'Só a alergia retirada vai para o histórico; a que ficou (Látex) não.');
        $this->assertSame('alergia', $antes[0]['tipo']);
        $this->assertSame('Dipirona', $antes[0]['descricao']);
        $this->assertSame('dentista:dentista.teste@dentia.local', $antes[0]['retificado_por']);
        $this->assertNotEmpty($antes[0]['retificado_em']);
    }

    public function test_evolucao_clinica_nao_e_alterada_nem_apagada(): void
    {
        $id = (int)$this->pdo->query('SELECT id FROM evolucao_clinica ORDER BY id LIMIT 1')->fetchColumn();
        $this->assertGreaterThan(0, $id, 'O banco de teste precisa ter ao menos uma evolução.');

        $this->recusado("UPDATE evolucao_clinica SET observacoes = 'reescrito' WHERE id = $id", 'não pode ser alterad');
        $this->recusado("DELETE FROM evolucao_clinica WHERE id = $id", 'não pode ser alterad');
        // Os dentes da evolução têm a mesma trava (conferida no catálogo: o banco de teste pode não ter linhas ali).
        $travas = $this->pdo->query("SELECT count(*) FROM pg_trigger WHERE tgname = 'evolucao_dente_so_acrescimo'
            AND tgrelid = 'evolucao_dente'::regclass AND tgenabled = 'O'")->fetchColumn();
        $this->assertSame(1, (int)$travas);
    }

    public function test_cadastro_pelo_site_guarda_o_consentimento(): void
    {
        $novo = cadastrarPacientePortal(['nome' => 'Paciente do Site', 'cpf' => '111.444.777-35', 'email' => 'paciente.site@dentia.local',
            'telefone' => '(67) 99234-5678', 'nascimento' => '1990-05-10',
            'senha' => 'clinica-Sorriso-42', 'confirmar_senha' => 'clinica-Sorriso-42', 'aceite' => '1']);

        $c = $this->pdo->query("SELECT versao_termo, hash_termo, origem, ip, aceito_em FROM consentimento WHERE id_paciente = {$novo['id_paciente']}")->fetchAll();
        $this->assertCount(1, $c);
        $this->assertSame(TERMO_LGPD_VERSAO, $c[0]['versao_termo']);
        $this->assertSame(hash('sha256', TERMO_LGPD_TEXTO), $c[0]['hash_termo']);
        $this->assertSame('site', $c[0]['origem']);
        $this->assertSame('203.0.113.9', $c[0]['ip']);
        $this->assertNotEmpty($c[0]['aceito_em']);
    }

    public function test_leitura_do_prontuario_fica_registrada(): void
    {
        registrarLeituraProntuario($this->paciente, 'dentista_prontuario.php');

        $l = $this->pdo->query("SELECT usuario, pagina FROM acesso_prontuario WHERE id_paciente = {$this->paciente}")->fetchAll();
        $this->assertSame([['usuario' => 'dentista:dentista.teste@dentia.local', 'pagina' => 'dentista_prontuario.php']], $l);
    }

    public function test_a_aplicacao_nao_apaga_consentimento_leitura_nem_historico(): void
    {
        registrarConsentimento($this->pdo, $this->paciente, 'recepcao');
        registrarLeituraProntuario($this->paciente, 'dentista_prontuario.php');
        $this->pdo->exec('SET LOCAL ROLE dentia_app');

        foreach (['DELETE FROM consentimento', "UPDATE consentimento SET origem = 'site'",
                  'DELETE FROM acesso_prontuario', "UPDATE acesso_prontuario SET usuario = 'ninguem'",
                  'DELETE FROM historico_clinico_retificado', "UPDATE historico_clinico_retificado SET descricao = 'x'",
                  "INSERT INTO historico_clinico_retificado (id_original, id_paciente, tipo, descricao, operacao) VALUES (1, 1, 'alergia', 'x', 'DELETE')"] as $sql) {
            $this->recusado($sql, '42501');
        }
    }
}
