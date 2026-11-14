<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Faces do dente com a nomenclatura odontológica: o dentista registra que trabalhou
 * só em determinadas faces de um dente, na evolução e no odontograma.
 */
final class FacesDenteTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
        $_SESSION = [];
    }

    /** Entra como um dentista do banco de teste e devolve [id do paciente dele, procedimento que ele realiza]. */
    private function comoDentista(): array
    {
        $d = $this->pdo->query("SELECT u.email, d.id FROM usuarios u JOIN dentistas d ON d.id_pessoa = u.id_pessoa
            WHERE u.tipo = 'dentista' AND u.status = 'ativo' AND d.status = 'ativo'
              AND EXISTS (SELECT 1 FROM agendamentos a WHERE a.id_dentista = d.id)
              AND EXISTS (SELECT 1 FROM dentista_procedimentos dp WHERE dp.id_dentista = d.id) ORDER BY u.id LIMIT 1")->fetch();
        $this->assertNotFalse($d, 'O banco de teste precisa de um dentista com paciente e procedimento.');
        $_SESSION = ['perfil' => 'dentista', 'email' => $d['email']];
        marcarUsuarioNoBanco('dentista:' . $d['email']);
        $paciente = (int)$this->pdo->query('SELECT id_paciente FROM agendamentos WHERE id_dentista = ' . (int)$d['id'] . ' ORDER BY id LIMIT 1')->fetchColumn();
        return [$paciente, dentistaSessao()['procedimentos'][0]];
    }

    public function test_cada_dente_tem_as_cinco_faces_com_o_nome_tecnico_certo(): void
    {
        $this->assertSame(['mesial', 'distal', 'vestibular', 'palatina', 'incisal'], facesDoDente(11));   // incisivo superior
        $this->assertSame(['mesial', 'distal', 'vestibular', 'palatina', 'incisal'], facesDoDente(23));   // canino superior
        $this->assertSame(['mesial', 'distal', 'vestibular', 'palatina', 'oclusal'], facesDoDente(14));   // pré-molar superior
        $this->assertSame(['mesial', 'distal', 'vestibular', 'palatina', 'oclusal'], facesDoDente(26));   // molar superior
        $this->assertSame(['mesial', 'distal', 'vestibular', 'lingual', 'incisal'], facesDoDente(41));    // incisivo inferior
        $this->assertSame(['mesial', 'distal', 'vestibular', 'lingual', 'oclusal'], facesDoDente(36));    // molar inferior
        $this->assertSame(['mesial', 'distal', 'vestibular', 'palatina', 'incisal'], facesDoDente(53));   // canino decíduo superior
        $this->assertSame(['mesial', 'distal', 'vestibular', 'lingual', 'oclusal'], facesDoDente(75));    // molar decíduo inferior
    }

    public function test_campo_dentes_aceita_faces_por_nome_e_por_sigla(): void
    {
        $this->assertSame([11 => [], 26 => ['mesial', 'oclusal']], interpretarDentesEvolucao('11, 26 (mesial, oclusal)'));
        $this->assertSame([36 => ['mesial', 'distal', 'oclusal']], interpretarDentesEvolucao('36 (MOD)'));
        $this->assertSame([21 => ['vestibular', 'incisal'], 46 => ['lingual']], interpretarDentesEvolucao('21(Incisal; Vestibular) 46 (L)'));
        $this->assertSame([11 => [], 21 => [], 36 => []], interpretarDentesEvolucao('11, 21 36'));       // formato antigo continua valendo
        $this->assertSame([], interpretarDentesEvolucao('   '));
        $this->assertSame([26 => ['mesial', 'oclusal']], interpretarDentesEvolucao('26 (mesial), 26 (oclusal)'));
    }

    public function test_face_que_o_dente_nao_tem_e_recusada_com_explicacao(): void
    {
        $casos = [['26 (lingual)', 'palatina'], ['36 (palatina)', 'lingual'], ['11 (oclusal)', 'incisal'], ['16 (incisal)', 'oclusal'],
                  ['26 (cima)', 'face'], ['99', 'FDI'], ['26 (mesial', 'Não entendi'], ['dente 26', 'Não entendi']];
        foreach ($casos as [$texto, $esperado]) {
            try {
                interpretarDentesEvolucao($texto);
                $this->fail('Aceitou: ' . $texto);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($esperado, $e->getMessage(), $texto);
            }
        }
    }

    public function test_evolucao_guarda_as_faces_e_marca_so_elas_no_odontograma(): void
    {
        [$paciente, $procedimento] = $this->comoDentista();

        $id = registrarEvolucao(['paciente' => (string)$paciente, 'procedimento' => $procedimento,
            'dentes' => '26 (mesial, oclusal), 11', 'observacoes' => 'Restauração em resina composta.']);

        $faces = $this->pdo->query("SELECT d.numero_dente || ':' || f.face FROM evolucao_dente_face f JOIN odontograma_dentes d ON d.id = f.id_dente
            WHERE f.id_evolucao = $id ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['26:mesial', '26:oclusal'], $faces);

        $prontuario = lerJson('prontuarios.json', ['id_paciente' => $paciente])[0];
        $this->assertSame(['mesial' => 'tratado', 'oclusal' => 'tratado'], $prontuario['odontograma']['26']['faces']);
        $this->assertSame([], $prontuario['odontograma']['11']['faces']);
        $this->assertSame('11, 26 (mesial, oclusal)', $prontuario['historico'][0]['dentes']);
    }

    public function test_odontograma_salva_e_le_a_situacao_de_cada_face(): void
    {
        [$paciente] = $this->comoDentista();
        // Parte de um dente sem nenhuma face marcada, qualquer que seja o estado do banco de teste.
        salvarOdontograma($paciente, ['36' => ['status' => 'saudavel', 'faces' => array_fill_keys(facesDoDente(36), 'saudavel')]]);

        salvarOdontograma($paciente, ['36' => ['status' => 'precisaTratamento', 'notas' => '',
            'faces' => ['distal' => 'precisaTratamento', 'oclusal' => 'tratado']]]);
        $this->assertSame(['distal' => 'precisaTratamento', 'oclusal' => 'tratado'],
            lerJson('prontuarios.json', ['id_paciente' => $paciente])[0]['odontograma']['36']['faces']);

        // Face que volta a "saudável" sai da lista; a outra continua.
        salvarOdontograma($paciente, ['36' => ['status' => 'tratado', 'faces' => ['distal' => 'saudavel', 'oclusal' => 'tratado']]]);
        $this->assertSame(['oclusal' => 'tratado'], lerJson('prontuarios.json', ['id_paciente' => $paciente])[0]['odontograma']['36']['faces']);

        foreach ([['faces' => ['palatina' => 'tratado']], ['faces' => ['oclusal' => 'ausente']], ['faces' => 'mesial']] as $ruim) {
            try {
                salvarOdontograma($paciente, ['36' => ['status' => 'tratado'] + $ruim]);
                $this->fail('Aceitou faces inválidas: ' . json_encode($ruim));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('36', $e->getMessage());
            }
        }
    }

    public function test_odontograma_salva_depois_de_uma_evolucao_com_observacao_longa(): void
    {
        // A evolução copia a observação (até 5.000 caracteres) para as notas do dente; salvar o odontograma
        // devolve essas notas ao servidor. Antes o limite ali era 500 e o salvamento era recusado.
        [$paciente, $procedimento] = $this->comoDentista();
        registrarEvolucao(['paciente' => (string)$paciente, 'procedimento' => $procedimento, 'dentes' => '15',
            'observacoes' => str_repeat('Observação clínica detalhada. ', 40)]);

        $odontograma = lerJson('prontuarios.json', ['id_paciente' => $paciente])[0]['odontograma'];
        $this->assertGreaterThan(500, strlen($odontograma['15']['notas']));
        salvarOdontograma($paciente, ['15' => $odontograma['15']]); // a tela envia só o dente alterado
        $this->addToAssertionCount(1);
    }

    public function test_faces_anotadas_na_evolucao_nao_sao_alteradas_nem_apagadas(): void
    {
        [$paciente, $procedimento] = $this->comoDentista();
        $id = registrarEvolucao(['paciente' => (string)$paciente, 'procedimento' => $procedimento, 'dentes' => '46 (MOD)', 'observacoes' => 'Teste.']);

        foreach (["UPDATE evolucao_dente_face SET face = 'vestibular' WHERE id_evolucao = $id", "DELETE FROM evolucao_dente_face WHERE id_evolucao = $id"] as $sql) {
            $this->pdo->exec('SAVEPOINT tentativa');
            try {
                $this->pdo->exec($sql);
                $this->fail('Foi aceito: ' . $sql);
            } catch (PDOException $e) {
                $this->assertStringContainsString('não pode ser alterad', $e->getMessage());
            } finally {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT tentativa');
            }
        }
    }
}
