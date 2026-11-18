<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;

/** Régua de horas da agenda da recepção: cada consulta na posição da hora e com a largura da duração. */
final class ReguaAgendaTest extends TestCase
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

    public function test_posicao_e_largura_seguem_hora_e_duracao(): void
    {
        $linha = $this->pdo->query("SELECT a.data::text AS data, a.id FROM agendamentos a ORDER BY a.id LIMIT 1")->fetch();
        $consultas = agendaRecepcao('ag.data = CAST(:data AS date)', ['data' => $linha['data']]);
        $regua = reguaDoDia($linha['data'], $consultas);

        $this->assertNotNull($regua);
        $total = ($regua['fim'] - $regua['inicio']) * 60;
        $duracoes = $this->pdo->query("SELECT a.id, p.duracao_min FROM agendamentos a JOIN procedimentos p ON p.id = a.id_procedimento
            WHERE a.data = '{$linha['data']}'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $vistas = 0;
        foreach ($regua['linhas'] as $l) {
            foreach ($l['consultas'] as $c) {
                [$h, $m] = array_map('intval', explode(':', $c['hora']));
                $this->assertEqualsWithDelta((($h - $regua['inicio']) * 60 + $m) / $total * 100, $c['esquerda'], 0.01);
                $this->assertEqualsWithDelta($duracoes[$c['id']] / $total * 100, $c['largura'], 0.01);
                $this->assertLessThanOrEqual(100.001, $c['esquerda'] + $c['largura'], 'Consulta passou do fim da régua.');
                $vistas++;
            }
        }
        $this->assertSame(count($consultas), $vistas, 'Toda consulta do dia aparece na régua.');
    }

    public function test_dia_sem_consulta_nao_tem_regua_e_agora_so_vale_hoje(): void
    {
        $this->assertNull(reguaDoDia('2026-01-01', []));
        $amanha = date('Y-m-d', strtotime('+400 days'));
        $consulta = ['id' => 0, 'hora' => '09:00', 'paciente' => 'X', 'procedimento' => 'Y', 'dentista' => 'Z', 'sala' => 'S', 'status' => 'Agendado'];
        $this->assertNull(reguaDoDia($amanha, [$consulta])['agora']);
    }
}
