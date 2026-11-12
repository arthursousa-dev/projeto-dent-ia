<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use InvalidArgumentException;
use PDO;

/**
 * P0.1: quem entra com senha de demonstração ou fraca não abre nenhuma página
 * antes de trocar a senha, e a senha nova não pode ser fraca.
 */
final class SenhaDemoTest extends TestCase
{
    private PDO $pdo;
    private string $email = 'teste.senhademo@dentia.local';

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

    private function criarUsuario(string $senha): void
    {
        $pessoa = $this->pdo->query("INSERT INTO pessoa (nome, cpf) VALUES ('Teste Senha Demo', '39053344705') RETURNING id")->fetchColumn();
        $this->pdo->prepare("INSERT INTO usuarios (email, senha_hash, tipo, id_pessoa, status) VALUES (:e, :s, 'dono', :p, 'ativo')")
            ->execute(['e' => $this->email, 's' => password_hash($senha, PASSWORD_BCRYPT), 'p' => $pessoa]);
    }

    // O @ em iniciarSessaoUsuario cala só o aviso de session_regenerate_id, que não tem sessão HTTP no PHPUnit.

    /** Páginas que o navegador pode pedir (as mesmas que o router.php aceita). */
    private static function paginas(): array
    {
        return array_values(array_diff(array_map('basename', glob(__DIR__ . '/../*.php')), ['router.php']));
    }

    public function test_login_com_123456_exige_troca_de_senha(): void
    {
        $this->criarUsuario('123456');
        $usuario = autenticarUsuario($this->email, '123456', 'dono');

        $this->assertIsArray($usuario);
        $this->assertTrue($usuario['trocar_senha']);

        @iniciarSessaoUsuario($usuario, $this->email);
        $this->assertTrue($_SESSION['trocar_senha']);
    }

    public function test_login_com_senha_forte_nao_exige_troca(): void
    {
        $this->criarUsuario('clinica-Sorriso-42');
        $usuario = autenticarUsuario($this->email, 'clinica-Sorriso-42', 'dono');

        $this->assertFalse($usuario['trocar_senha']);
        @iniciarSessaoUsuario($usuario, $this->email);
        $this->assertArrayNotHasKey('trocar_senha', $_SESSION);
    }

    public function test_com_troca_pendente_nenhuma_pagina_abre_alem_de_trocar_senha_e_sair(): void
    {
        $_SESSION['trocar_senha'] = true;
        $liberadas = [];
        foreach (self::paginas() as $pagina) {
            if (!trocaDeSenhaPendente('/' . $pagina)) $liberadas[] = $pagina;
        }
        sort($liberadas);
        $this->assertSame(['logout.php', 'trocar_senha.php'], $liberadas);
    }

    public function test_sem_troca_pendente_nada_e_bloqueado(): void
    {
        foreach (self::paginas() as $pagina) {
            $this->assertFalse(trocaDeSenhaPendente('/' . $pagina), $pagina);
        }
    }

    public function test_toda_pagina_passa_pela_trava(): void
    {
        // A trava fica em includes/functions.php. Ficam de fora só as duas páginas que não abrem sessão nem mostram dados.
        $semTrava = [];
        foreach (self::paginas() as $pagina) {
            $codigo = file_get_contents(__DIR__ . '/../' . $pagina);
            if (!str_contains($codigo, 'includes/functions.php') && !str_contains($codigo, 'includes/login_form.php')) $semTrava[] = $pagina;
        }
        sort($semTrava);
        $this->assertSame(['health.php', 'login.php'], $semTrava);
        foreach ($semTrava as $pagina) {
            $this->assertStringNotContainsString('session_start', file_get_contents(__DIR__ . '/../' . $pagina), $pagina);
        }
    }

    public function test_trocar_a_senha_libera_o_acesso(): void
    {
        $this->criarUsuario('123456');
        @iniciarSessaoUsuario(autenticarUsuario($this->email, '123456', 'dono'), $this->email);

        alterarSenhaAtual(['senha_atual' => '123456', 'senha_nova' => 'clinica-Sorriso-42', 'confirmar_senha_nova' => 'clinica-Sorriso-42']);

        $this->assertArrayNotHasKey('trocar_senha', $_SESSION);
        $this->assertFalse(autenticarUsuario($this->email, 'clinica-Sorriso-42', 'dono')['trocar_senha']);
    }

    public function test_senha_nova_fraca_e_recusada(): void
    {
        foreach (['123456', 'curta-123', '1234567890', 'senha12345', 'aaaaaaaaaaaa', 'DENTIA12345'] as $fraca) {
            try {
                validarNovaSenha($fraca);
                $this->fail('Aceitou a senha fraca: ' . $fraca);
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
        validarNovaSenha('clinica-Sorriso-42');
        $this->assertGreaterThanOrEqual(10, SENHA_MINIMA);
    }
}
