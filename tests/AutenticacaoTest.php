<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Config\Database;
use PDO;

final class AutenticacaoTest extends TestCase
{
    private PDO $pdo;
    private string $email = 'teste.autenticacao@dentia.local';
    private string $senha = 'senhaCorreta123';

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->prepare('DELETE FROM usuarios WHERE email = :email')
            ->execute([':email' => $this->email]);

        $this->pdo->prepare(
            'INSERT INTO usuarios (email, senha_hash, tipo, nome, status)
             VALUES (:email, :senha, :tipo, :nome, :status)'
        )->execute([
            ':email'  => $this->email,
            ':senha'  => password_hash($this->senha, PASSWORD_DEFAULT),
            ':tipo'   => 'dono',
            ':nome'   => 'Usuário de Teste',
            ':status' => 'ativo',
        ]);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM usuarios WHERE email = :email')
            ->execute([':email' => $this->email]);
    }

    public function test_login_com_credenciais_corretas_retorna_dados_do_usuario(): void
    {
        $resultado = autenticarUsuario($this->email, $this->senha, 'dono');

        $this->assertIsArray($resultado);
        $this->assertSame('Usuário de Teste', $resultado['nome']);
        $this->assertSame('dono', $resultado['perfil']);
        $this->assertSame('dono_dashboard.php', $resultado['redirecionar']);
    }

    public function test_login_com_senha_errada_retorna_false(): void
    {
        $resultado = autenticarUsuario($this->email, 'senhaErrada', 'dono');

        $this->assertFalse($resultado);
    }

    public function test_login_com_perfil_errado_retorna_false(): void
    {
        // usuário existe como 'dono', mas login tentado como 'dentista'
        $resultado = autenticarUsuario($this->email, $this->senha, 'dentista');

        $this->assertFalse($resultado);
    }

    public function test_login_com_email_inexistente_retorna_false(): void
    {
        $resultado = autenticarUsuario('nao.existe@dentia.local', 'qualquer', 'dono');

        $this->assertFalse($resultado);
    }

    public function test_conta_bloqueia_apos_cinco_tentativas_incorretas(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $resultado = autenticarUsuario($this->email, 'senhaErrada', 'dono');
            $this->assertFalse($resultado, "tentativa " . ($i + 1) . " deveria falhar, mas não bloquear ainda");
        }

        // 5ª tentativa errada: bloqueia a conta
        autenticarUsuario($this->email, 'senhaErrada', 'dono');

        // mesmo com a senha CORRETA agora, o login deve falhar (bloqueado)
        $resultado = autenticarUsuario($this->email, $this->senha, 'dono');
        $this->assertFalse($resultado, 'conta deveria estar bloqueada após 5 tentativas incorretas');

        $linha = $this->pdo->prepare('SELECT bloqueado_ate FROM usuarios WHERE email = :email');
        $linha->execute([':email' => $this->email]);
        $bloqueadoAte = $linha->fetchColumn();

        $this->assertNotNull($bloqueadoAte);
        $this->assertGreaterThan(time(), strtotime($bloqueadoAte));
    }

    public function test_login_correto_zera_contador_de_tentativas(): void
    {
        autenticarUsuario($this->email, 'senhaErrada', 'dono');
        autenticarUsuario($this->email, 'senhaErrada', 'dono');

        $resultado = autenticarUsuario($this->email, $this->senha, 'dono');
        $this->assertIsArray($resultado);

        $linha = $this->pdo->prepare('SELECT tentativas_login FROM usuarios WHERE email = :email');
        $linha->execute([':email' => $this->email]);

        $this->assertSame(0, (int) $linha->fetchColumn());
    }

    public function test_conta_inativa_nao_autentica(): void
    {
        $this->pdo->prepare('UPDATE usuarios SET status = :status WHERE email = :email')
            ->execute([':status' => 'inativo', ':email' => $this->email]);

        $resultado = autenticarUsuario($this->email, $this->senha, 'dono');

        $this->assertFalse($resultado);
    }
}
