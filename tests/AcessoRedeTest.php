<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;

/**
 * P2.2: a tela "Banco de dados" só abre neste computador, inclusive atrás de um proxy na mesma máquina.
 * P2.13: o limite de tentativas de login vale por computador+conta; uma conta errando não trava as outras.
 */
final class AcessoRedeTest extends TestCase
{
    private PDO $pdo;
    private array $servidor;
    private string|false $proxy;
    private string|false $rede;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
        $this->servidor = $_SERVER;
        $this->proxy = getenv('APP_PROXY_CONFIAVEL');
        $this->rede = getenv('APP_MONITOR_REDE');
        putenv('APP_MONITOR_REDE=false');
        putenv('APP_PROXY_CONFIAVEL=false');
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
        $_SERVER = $this->servidor;
        foreach (['APP_PROXY_CONFIAVEL' => $this->proxy, 'APP_MONITOR_REDE' => $this->rede] as $chave => $valor) {
            putenv($valor === false ? $chave : "$chave=$valor");
        }
    }

    public function test_sem_proxy_so_o_proprio_computador_abre_a_tela_do_banco(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertTrue(monitorPermitido());

        $_SERVER['REMOTE_ADDR'] = '192.168.0.50';
        $this->assertFalse(monitorPermitido());

        // Sem proxy declarado, o cabeçalho não vale: quem manda "X-Forwarded-For: 127.0.0.1" de fora continua barrado.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
        $this->assertFalse(monitorPermitido());
    }

    public function test_atras_de_proxy_local_visitante_de_fora_nao_abre_a_tela_do_banco(): void
    {
        putenv('APP_PROXY_CONFIAVEL=true');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';          // toda requisição chega do proxy, que está nesta máquina

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
        $this->assertFalse(monitorPermitido(), 'Visitante da internet passou pela trava por causa do proxy local.');

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.9, 127.0.0.1';
        $this->assertTrue(monitorPermitido(), 'Navegador nesta máquina, passando pelo proxy, deve abrir.');

        // Proxy que não informa a origem: não dá para saber quem é, então fica fechado.
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertFalse(monitorPermitido());
    }

    public function test_conta_que_erra_muito_nao_trava_as_outras_do_mesmo_computador(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 250);
        for ($i = 0; $i < LOGIN_MAX_POR_CONTA; $i++) registrarFalhaLogin('Recepcao@Clinica.com ');

        $this->assertTrue(loginLimitado('recepcao@clinica.com'), 'Maiúsculas e espaços não criam outra contagem.');
        $this->assertFalse(loginLimitado('dentista@clinica.com'), 'Outra pessoa da clínica ficou travada junto.');

        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $this->assertFalse(loginLimitado('recepcao@clinica.com'), 'O limite é por computador: de outro lugar, só vale o bloqueio da conta.');
    }

    public function test_muitas_contas_do_mesmo_computador_batem_no_teto_geral(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 250);
        for ($i = 0; $i < LOGIN_MAX_POR_ORIGEM; $i++) registrarFalhaLogin("conta$i@clinica.com");

        $this->assertTrue(loginLimitado('nunca.tentou@clinica.com'));
    }
}
