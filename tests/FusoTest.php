<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use InvalidArgumentException;
use PDO;

/** P2.9: o fuso da clínica é configurável e vale igual para o PHP e para o banco, inclusive na virada do dia. */
final class FusoTest extends TestCase
{
    private PDO $pdo;
    private string $fusoOriginal;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->fusoOriginal = date_default_timezone_get();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
        Database::aplicarFuso($this->fusoOriginal);
        $_SESSION = [];
    }

    private function hojeNoBanco(): string
    {
        return (string)$this->pdo->query('SELECT CURRENT_DATE::text')->fetchColumn();
    }

    public function test_php_e_banco_concordam_na_data_em_fusos_com_dias_diferentes(): void
    {
        // Kiritimati (UTC+14) e Pago Pago (UTC-11) estão 25 horas separados: a data nunca é a mesma nos dois.
        Database::aplicarFuso('Pacific/Kiritimati');
        $la = date('Y-m-d');
        $this->assertSame($la, $this->hojeNoBanco());
        $this->assertSame('Pacific/Kiritimati', $this->pdo->query('SHOW TIME ZONE')->fetchColumn());

        Database::aplicarFuso('Pacific/Pago_Pago');
        $ca = date('Y-m-d');
        $this->assertSame($ca, $this->hojeNoBanco());
        $this->assertNotSame($la, $ca, 'A virada do dia não acompanhou o fuso.');
        $this->assertSame(date('H'), $this->pdo->query("SELECT to_char(now(), 'HH24')")->fetchColumn());
    }

    public function test_configuracoes_gravam_o_fuso_e_ele_passa_a_valer(): void
    {
        $_SESSION = ['perfil' => 'dono', 'email' => (string)$this->pdo->query("SELECT email FROM usuarios WHERE tipo='dono' AND status='ativo' ORDER BY id LIMIT 1")->fetchColumn()];
        $clinica = lerJsonObjeto('clinica.json');
        $outro = $this->fusoOriginal === 'America/Rio_Branco' ? 'America/Sao_Paulo' : 'America/Rio_Branco';

        salvarConfiguracoes(['fuso_horario' => $outro] + $clinica);

        $this->assertSame($outro, $this->pdo->query('SELECT fuso_horario FROM clinica WHERE id = 1')->fetchColumn());
        $this->assertSame($outro, date_default_timezone_get());
        $this->assertSame($outro, $this->pdo->query('SHOW TIME ZONE')->fetchColumn());
        $this->assertSame(date('Y-m-d'), $this->hojeNoBanco());
    }

    public function test_fuso_fora_do_brasil_ou_inventado_e_recusado(): void
    {
        $_SESSION = ['perfil' => 'dono', 'email' => (string)$this->pdo->query("SELECT email FROM usuarios WHERE tipo='dono' AND status='ativo' ORDER BY id LIMIT 1")->fetchColumn()];
        $clinica = lerJsonObjeto('clinica.json');
        foreach (['Europe/Lisbon', 'America/Inventado', "America/Cuiaba'; DROP TABLE clinica; --", ''] as $ruim) {
            try {
                salvarConfiguracoes(['fuso_horario' => $ruim] + $clinica);
                $this->fail('Aceitou o fuso: ' . $ruim);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Fuso', $e->getMessage());
            }
        }
        $this->assertSame($this->fusoOriginal, date_default_timezone_get());
    }

    public function test_formulario_antigo_sem_o_campo_mantem_o_fuso(): void
    {
        $_SESSION = ['perfil' => 'dono', 'email' => (string)$this->pdo->query("SELECT email FROM usuarios WHERE tipo='dono' AND status='ativo' ORDER BY id LIMIT 1")->fetchColumn()];
        $antes = $this->pdo->query('SELECT fuso_horario FROM clinica WHERE id = 1')->fetchColumn();
        salvarConfiguracoes(lerJsonObjeto('clinica.json'));
        $this->assertSame($antes, $this->pdo->query('SELECT fuso_horario FROM clinica WHERE id = 1')->fetchColumn());
    }
}
