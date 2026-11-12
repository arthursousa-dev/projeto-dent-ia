<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * P1.2 (direitos do titular) e P1.1 (consentimento no cadastro da recepção).
 * O paciente baixa os próprios dados e pede exclusão; o gestor atende, e a exclusão
 * apaga o que não é clínico sem tocar no prontuário. Tudo com rollback.
 */
final class DireitosTitularTest extends TestCase
{
    private PDO $pdo;
    private int $pessoa;
    private int $paciente;
    private string $email = 'titular.teste@dentia.local';

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
        $this->pessoa = (int)$this->pdo->query("INSERT INTO pessoa (nome, cpf, rg, email, nascimento)
            VALUES ('Titular de Teste', '39053344705', '1234567', '{$this->email}', '1985-03-20') RETURNING id")->fetchColumn();
        $this->paciente = (int)$this->pdo->query("INSERT INTO pacientes (id_pessoa, profissao, observacoes)
            VALUES ({$this->pessoa}, 'Professora', 'Prefere atendimento à tarde') RETURNING id")->fetchColumn();
        $this->pdo->exec("INSERT INTO prontuarios (id_paciente) VALUES ({$this->paciente})");
        $this->pdo->exec("INSERT INTO historico_clinico (id_paciente, tipo, descricao) VALUES ({$this->paciente}, 'alergia', 'Látex')");
        $this->pdo->prepare("INSERT INTO usuarios (id_pessoa, email, senha_hash, tipo) VALUES (:p, :e, :s, 'cliente')")
            ->execute(['p' => $this->pessoa, 'e' => $this->email, 's' => password_hash('clinica-Sorriso-42', PASSWORD_BCRYPT)]);
        upsertTelefone($this->pdo, $this->pessoa, null, '(67) 99234-5678');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $this->como('cliente', $this->email);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
        $_SESSION = [];
    }

    private function como(string $perfil, string $email): void
    {
        $_SESSION = ['perfil' => $perfil, 'email' => $email];
    }

    private function gestor(): void
    {
        $this->como('dono', (string)$this->pdo->query("SELECT email FROM usuarios WHERE tipo='dono' AND status='ativo' ORDER BY id LIMIT 1")->fetchColumn());
    }

    private function valor(string $sql): mixed
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    public function test_paciente_baixa_os_proprios_dados(): void
    {
        $dados = exportarDadosTitular();

        $this->assertSame('Titular de Teste', $dados['cadastro']['nome']);
        $this->assertSame('Látex', $dados['cadastro']['alergias']);
        $this->assertIsArray($dados['consultas']);
        $this->assertArrayHasKey('prontuario', $dados);
        $this->assertStringNotContainsString('senha', strtolower(json_encode($dados)));
        // O download fica registrado como pedido de cópia já atendido.
        $this->assertSame('atendida', $this->valor("SELECT status FROM solicitacao_titular WHERE id_paciente={$this->paciente} AND tipo='copia'"));
    }

    public function test_pedido_de_exclusao_fica_aberto_e_nao_duplica(): void
    {
        solicitarDireitoTitular('exclusao', 'Mudei de cidade.');
        $this->assertSame('aberta', $this->valor("SELECT status FROM solicitacao_titular WHERE id_paciente={$this->paciente} AND tipo='exclusao'"));

        $this->expectException(InvalidArgumentException::class);
        solicitarDireitoTitular('exclusao', 'De novo.');
    }

    public function test_atender_exclusao_apaga_o_que_nao_e_clinico_e_mantem_o_prontuario(): void
    {
        solicitarDireitoTitular('exclusao', 'Mudei de cidade.');
        $id = (int)$this->valor("SELECT id FROM solicitacao_titular WHERE id_paciente={$this->paciente}");

        $this->gestor();
        responderSolicitacaoTitular($id, true, 'Dados de contato removidos; prontuário mantido por obrigação legal.');

        // Saiu: contato, documentos secundários, dados de perfil e o acesso ao portal.
        $this->assertSame(0, (int)$this->valor("SELECT count(*) FROM telefone WHERE id_pessoa={$this->pessoa}"));
        $p = $this->pdo->query("SELECT nome, cpf, nascimento, email, rg FROM pessoa WHERE id={$this->pessoa}")->fetch();
        $this->assertNull($p['email']);
        $this->assertNull($p['rg']);
        $pac = $this->pdo->query("SELECT profissao, observacoes, status FROM pacientes WHERE id={$this->paciente}")->fetch();
        $this->assertNull($pac['profissao']);
        $this->assertNull($pac['observacoes']);
        $this->assertSame('inativo', $pac['status']);
        $u = $this->pdo->query("SELECT email, status FROM usuarios WHERE id_pessoa={$this->pessoa}")->fetch();
        $this->assertSame('inativo', $u['status']);
        $this->assertStringNotContainsString('titular.teste', $u['email']);
        $this->assertFalse(autenticarUsuario($this->email, 'clinica-Sorriso-42', 'cliente'));

        // Ficou: identificação do prontuário e todo o conteúdo clínico.
        $this->assertSame('Titular de Teste', $p['nome']);
        $this->assertSame('39053344705', $p['cpf']);
        $this->assertSame('1985-03-20', $p['nascimento']);
        $this->assertSame(1, (int)$this->valor("SELECT count(*) FROM prontuarios WHERE id_paciente={$this->paciente}"));
        $this->assertSame('Látex', $this->valor("SELECT descricao FROM historico_clinico WHERE id_paciente={$this->paciente}"));

        $s = $this->pdo->query("SELECT status, respondida_por, respondida_em FROM solicitacao_titular WHERE id=$id")->fetch();
        $this->assertSame('atendida', $s['status']);
        $this->assertStringStartsWith('dono:', $s['respondida_por']);
        $this->assertNotEmpty($s['respondida_em']);
    }

    public function test_recusar_exige_resposta_e_nao_mexe_nos_dados(): void
    {
        solicitarDireitoTitular('exclusao', 'Mudei de cidade.');
        $id = (int)$this->valor("SELECT id FROM solicitacao_titular WHERE id_paciente={$this->paciente}");
        $this->gestor();

        try {
            responderSolicitacaoTitular($id, false, '  ');
            $this->fail('Aceitou resposta vazia.');
        } catch (InvalidArgumentException $e) {
            $this->addToAssertionCount(1);
        }
        responderSolicitacaoTitular($id, false, 'Há tratamento em andamento.');

        $this->assertSame('recusada', $this->valor("SELECT status FROM solicitacao_titular WHERE id=$id"));
        $this->assertSame(1, (int)$this->valor("SELECT count(*) FROM telefone WHERE id_pessoa={$this->pessoa}"));
        $this->assertSame($this->email, $this->valor("SELECT email FROM pessoa WHERE id={$this->pessoa}"));
    }

    public function test_so_o_gestor_responde(): void
    {
        solicitarDireitoTitular('exclusao', 'Mudei de cidade.');
        $id = (int)$this->valor("SELECT id FROM solicitacao_titular WHERE id_paciente={$this->paciente}");

        $this->expectException(RuntimeException::class);
        responderSolicitacaoTitular($id, true, 'Eu mesmo aprovo.');
    }

    public function test_consulta_futura_impede_a_exclusao(): void
    {
        // Marca uma consulta futura para o paciente do teste, num horário em que algum dentista atende (não depende dos dados do banco).
        $vaga = $this->pdo->query("SELECT d.id AS dentista, d.id_sala AS sala, dd.id_dia_semana AS dia, to_char(dd.hora, 'HH24:MI') AS hora,
                (SELECT dp.id_procedimento FROM dentista_procedimentos dp WHERE dp.id_dentista = d.id LIMIT 1) AS procedimento
            FROM disponibilidade_dentista dd JOIN dentistas d ON d.id = dd.id_dentista
            JOIN funcionamento_clinica f ON f.id_dia_semana = dd.id_dia_semana AND dd.hora >= f.abertura AND dd.hora < f.fechamento
            WHERE d.id_sala IS NOT NULL AND d.status = 'ativo' AND EXISTS (SELECT 1 FROM dentista_procedimentos dp WHERE dp.id_dentista = d.id)
            ORDER BY d.id, dd.id_dia_semana, dd.hora DESC LIMIT 1")->fetch();
        $this->assertNotFalse($vaga, 'O banco de teste precisa de ao menos um dentista com horário e procedimento.');
        $dia = new \DateTimeImmutable('+60 days');
        while ((int)$dia->format('w') !== (int)$vaga['dia']) $dia = $dia->modify('+1 day');
        $this->pdo->prepare('INSERT INTO agendamentos (id_paciente, id_dentista, id_procedimento, id_sala, data, hora, valor)
            VALUES (:p, :d, :pr, :s, :data, :hora, 100)')
            ->execute(['p' => $this->paciente, 'd' => $vaga['dentista'], 'pr' => $vaga['procedimento'], 's' => $vaga['sala'],
                       'data' => $dia->format('Y-m-d'), 'hora' => $vaga['hora']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('consulta marcada');
        anonimizarPaciente($this->pdo, $this->paciente);
    }

    public function test_recepcao_registra_o_consentimento_uma_vez(): void
    {
        $this->como('recepcionista', (string)$this->valor("SELECT email FROM usuarios WHERE tipo='recepcionista' AND status='ativo' ORDER BY id LIMIT 1"));
        $base = ['nome' => 'Paciente da Recepção', 'cpf' => '111.444.777-35'];

        $id = salvarCadastroPaciente($base);
        $this->assertSame(0, (int)$this->valor("SELECT count(*) FROM consentimento WHERE id_paciente=$id"), 'Sem marcar a caixa, nada é gravado.');

        salvarCadastroPaciente($base + ['id' => (string)$id, 'consentimento_lgpd' => '1']);
        salvarCadastroPaciente($base + ['id' => (string)$id, 'consentimento_lgpd' => '1']);
        $c = $this->pdo->query("SELECT origem, versao_termo, registrado_por FROM consentimento WHERE id_paciente=$id")->fetchAll();
        $this->assertCount(1, $c, 'Mesma versão do termo não duplica.');
        $this->assertSame('recepcao', $c[0]['origem']);
        $this->assertSame(TERMO_LGPD_VERSAO, $c[0]['versao_termo']);
    }
}
