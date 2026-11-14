<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;
use RuntimeException;

/** Quadro da recepção: mostra TODOS os horários livres de cada dentista no dia, não só o próximo. */
final class QuadroHorariosTest extends TestCase
{
    private PDO $pdo;
    private array $vaga;
    private string $data;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
        $_SESSION = ['perfil' => 'recepcionista', 'email' => (string)$this->pdo->query(
            "SELECT email FROM usuarios WHERE tipo='recepcionista' AND status='ativo' ORDER BY id LIMIT 1")->fetchColumn()];
        // Um dentista com pelo menos 3 horários num dia da semana em que a clínica abre, e o menor procedimento dele.
        $this->vaga = $this->pdo->query("SELECT d.id AS dentista, d.id_sala AS sala, dd.id_dia_semana AS dia,
                (SELECT p.id FROM dentista_procedimentos dp JOIN procedimentos p ON p.id = dp.id_procedimento
                  WHERE dp.id_dentista = d.id ORDER BY p.duracao_min, p.id LIMIT 1) AS id_procedimento
            FROM dentistas d JOIN disponibilidade_dentista dd ON dd.id_dentista = d.id
            JOIN funcionamento_clinica f ON f.id_clinica = 1 AND f.id_dia_semana = dd.id_dia_semana
                 AND dd.hora >= f.abertura AND dd.hora + interval '2 hours' <= f.fechamento
            WHERE d.status = 'ativo' AND d.id_sala IS NOT NULL
              AND EXISTS (SELECT 1 FROM dentista_procedimentos dp WHERE dp.id_dentista = d.id)
            GROUP BY d.id, d.id_sala, dd.id_dia_semana HAVING count(*) >= 3 ORDER BY d.id, dd.id_dia_semana LIMIT 1")->fetch();
        $this->assertNotFalse($this->vaga, 'O banco de teste precisa de um dentista com 3 ou mais horários num dia.');
        $this->vaga['procedimento'] = (string)$this->pdo->query('SELECT nome FROM procedimentos WHERE id = ' . (int)$this->vaga['id_procedimento'])->fetchColumn();
        $dia = new \DateTimeImmutable('+45 days');
        while ((int)$dia->format('w') !== (int)$this->vaga['dia']) $dia = $dia->modify('+1 day');
        $this->data = $dia->format('Y-m-d');
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
        $_SESSION = [];
    }

    private function linhaDoDentista(array $quadro): array
    {
        foreach ($quadro['dentistas'] as $d) if ((int)$d['id'] === (int)$this->vaga['dentista']) return $d;
        $this->fail('O dentista não apareceu no quadro.');
    }

    public function test_mostra_todos_os_horarios_livres_do_dentista_e_nao_so_o_proximo(): void
    {
        $linha = $this->linhaDoDentista(quadroHorarios($this->data, $this->vaga['procedimento']));

        $this->assertGreaterThanOrEqual(3, count($linha['horarios']));
        $this->assertSame(horariosLivres($this->data, $this->vaga['procedimento'], (int)$this->vaga['dentista']), $linha['horarios']);
        $this->assertTrue($linha['atende']);
        $this->assertNotEmpty($linha['nome']);
        $this->assertNotEmpty($linha['especialidade']);
    }

    public function test_horario_ocupado_sai_do_quadro_e_os_outros_ficam(): void
    {
        $antes = $this->linhaDoDentista(quadroHorarios($this->data, $this->vaga['procedimento']))['horarios'];
        $paciente = (int)$this->pdo->query('SELECT id FROM pacientes ORDER BY id LIMIT 1')->fetchColumn();
        $this->pdo->prepare('INSERT INTO agendamentos (id_paciente, id_dentista, id_procedimento, id_sala, data, hora, valor)
            VALUES (:p, :d, :pr, :s, :data, :hora, 100)')->execute(['p' => $paciente, 'd' => $this->vaga['dentista'],
            'pr' => $this->vaga['id_procedimento'], 's' => $this->vaga['sala'], 'data' => $this->data, 'hora' => $antes[0]]);

        $linha = $this->linhaDoDentista(quadroHorarios($this->data, $this->vaga['procedimento']));

        $this->assertNotContains($antes[0], $linha['horarios']);
        $this->assertContains(end($antes), $linha['horarios']);
        $this->assertSame(1, $linha['consultas']);
    }

    public function test_sem_procedimento_escolhido_usa_o_menor_procedimento_de_cada_dentista(): void
    {
        $linha = $this->linhaDoDentista(quadroHorarios($this->data));
        $this->assertSame(proximosHorariosPorDentista($this->data)[(int)$this->vaga['dentista']] ?? [], $linha['horarios']);
        $this->assertGreaterThanOrEqual(3, count($linha['horarios']));
    }

    public function test_dentista_que_nao_faz_o_procedimento_aparece_sem_horarios(): void
    {
        $par = $this->pdo->query("SELECT d.id AS dentista, p.nome AS procedimento FROM dentistas d CROSS JOIN procedimentos p
            WHERE d.status = 'ativo' AND NOT EXISTS (SELECT 1 FROM dentista_procedimentos dp WHERE dp.id_dentista = d.id AND dp.id_procedimento = p.id)
            LIMIT 1")->fetch();
        if (!$par) $this->markTestSkipped('Todos os dentistas do banco de teste fazem todos os procedimentos.');

        foreach (quadroHorarios($this->data, $par['procedimento'])['dentistas'] as $d) {
            if ((int)$d['id'] !== (int)$par['dentista']) continue;
            $this->assertFalse($d['atende']);
            $this->assertSame([], $d['horarios']);
            return;
        }
        $this->fail('O dentista não apareceu no quadro.');
    }

    public function test_so_a_recepcao_ve_o_quadro(): void
    {
        $_SESSION = ['perfil' => 'cliente', 'email' => (string)$this->pdo->query(
            "SELECT email FROM usuarios WHERE tipo='cliente' AND status='ativo' ORDER BY id LIMIT 1")->fetchColumn()];
        $this->expectException(RuntimeException::class);
        quadroHorarios($this->data);
    }
}
