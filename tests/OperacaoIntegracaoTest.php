<?php
namespace Tests;

use App\Config\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class OperacaoIntegracaoTest extends TestCase
{
    private PDO $pdo;
    private array $pessoas=[];
    private array $usuarios=[];
    private int $paciente;
    private int $dentista;
    private int $procedimento;
    private int $sala;

    protected function setUp(): void
    {
        $this->pdo=Database::getConnection();
        foreach ([['52998224725','dono'],['11144477735','dentista'],['12345678909','cliente']] as [$cpf,$tipo]) {
            $stmt=$this->pdo->prepare('INSERT INTO pessoa (nome,cpf,email) VALUES (:nome,:cpf,:email) RETURNING id');
            $stmt->execute(['nome'=>'Operação '.$tipo,'cpf'=>$cpf,'email'=>'operacao.'.$tipo.'@teste.local']);
            $this->pessoas[$tipo]=(int)$stmt->fetchColumn();
            $stmt=$this->pdo->prepare('INSERT INTO usuarios (id_pessoa,email,senha_hash,tipo) VALUES (:id,:email,:senha,:tipo) RETURNING id');
            $stmt->execute(['id'=>$this->pessoas[$tipo],'email'=>'operacao.'.$tipo.'@teste.local','senha'=>password_hash('123456',PASSWORD_BCRYPT),'tipo'=>$tipo]);
            $this->usuarios[$tipo]=(int)$stmt->fetchColumn();
        }
        $this->paciente=(int)$this->pdo->query("INSERT INTO pacientes (id_pessoa) VALUES ({$this->pessoas['cliente']}) RETURNING id")->fetchColumn();
        $esp=referenciaPorNome($this->pdo,'especialidade','Operação');
        $this->sala=referenciaPorNome($this->pdo,'sala','Sala operação');
        $this->dentista=(int)$this->pdo->query("INSERT INTO dentistas (id_pessoa,cro_uf,cro_numero,id_especialidade,id_sala)
            VALUES ({$this->pessoas['dentista']},'MS','999996',$esp,{$this->sala}) RETURNING id")->fetchColumn();
        $cat=referenciaPorNome($this->pdo,'categoria_procedimento','Operação');
        $this->procedimento=(int)$this->pdo->query("INSERT INTO procedimentos (nome,id_categoria,duracao_min,valor_base)
            VALUES ('Procedimento operação',$cat,60,90) RETURNING id")->fetchColumn();
        $this->pdo->exec("INSERT INTO dentista_procedimentos VALUES ({$this->dentista},{$this->procedimento})");
        foreach (['09:00','09:30','10:00','14:00'] as $hora) $this->pdo->exec("INSERT INTO disponibilidade_dentista VALUES ({$this->dentista},1,'$hora')");
        $this->sessao('cliente');
    }

    private function sessao(string $tipo): void
    {
        $_SESSION=['perfil'=>$tipo,'email'=>'operacao.'.$tipo.'@teste.local','usuario'=>'Operação '.$tipo];
    }

    protected function tearDown(): void
    {
        $_SESSION=[];
        $this->pdo->exec("DELETE FROM evolucao_dente WHERE id_evolucao IN (SELECT e.id FROM evolucao_clinica e JOIN prontuarios pr ON pr.id=e.id_prontuario WHERE pr.id_paciente={$this->paciente})");
        $this->pdo->exec("DELETE FROM evolucao_clinica WHERE id_dentista={$this->dentista}");
        $this->pdo->exec("DELETE FROM odontograma_procedimentos WHERE id_dente IN (SELECT d.id FROM odontograma_dentes d JOIN prontuarios pr ON pr.id=d.id_prontuario WHERE pr.id_paciente={$this->paciente})");
        $this->pdo->exec("DELETE FROM odontograma_dentes WHERE id_prontuario IN (SELECT id FROM prontuarios WHERE id_paciente={$this->paciente})");
        $this->pdo->exec("DELETE FROM prontuarios WHERE id_paciente={$this->paciente}");
        $this->pdo->exec("DELETE FROM pagamento WHERE id_agendamento IN (SELECT id FROM agendamentos WHERE id_paciente={$this->paciente})");
        $this->pdo->exec("DELETE FROM agendamentos WHERE id_paciente={$this->paciente}");
        $this->pdo->exec("DELETE FROM dentista_procedimentos WHERE id_dentista={$this->dentista}");
        $this->pdo->exec("DELETE FROM disponibilidade_dentista WHERE id_dentista={$this->dentista}");
        $this->pdo->exec("DELETE FROM dentistas WHERE id={$this->dentista}");
        $this->pdo->exec("DELETE FROM procedimentos WHERE id={$this->procedimento}");
        $this->pdo->exec("DELETE FROM pacientes WHERE id={$this->paciente}");
        foreach ($this->usuarios as $id) {
            $this->pdo->exec("DELETE FROM lancamento_financeiro WHERE id_usuario=$id");
            $this->pdo->exec("DELETE FROM recuperacao_senha WHERE id_usuario=$id");
            $this->pdo->exec("DELETE FROM usuarios WHERE id=$id");
        }
        foreach ($this->pessoas as $id) $this->pdo->exec("DELETE FROM pessoa WHERE id=$id");
    }

    private function agendar(string $hora='09:00'): int
    {
        return criarAgendamento(['procedimento'=>'Procedimento operação','dentista'=>(string)$this->dentista,'data'=>'2030-01-07','horario'=>$hora]);
    }

    public function test_agendamento_gravado_com_paciente_da_sessao(): void
    {
        $id=$this->agendar();$a=buscarPorCampo(lerJson('agendamentos.json'),'id',$id);
        $this->assertSame($this->paciente,$a['id_paciente']);$this->assertSame($this->dentista,$a['id_dentista']);
        $this->assertSame('90.00',$a['valor']);$this->assertFalse($a['pago']);
    }

    public function test_rejeita_sobreposicao_pela_duracao(): void
    {
        $this->agendar();$this->expectException(\PDOException::class);$this->agendar('09:30');
    }

    public function test_clinica_fechada_e_dentista_sem_disponibilidade(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        criarAgendamento(['procedimento'=>'Procedimento operação','dentista'=>(string)$this->dentista,'data'=>'2030-01-06','horario'=>'09:00']);
    }

    public function test_cancelamento_e_acesso_a_consulta_de_outro_paciente(): void
    {
        $id=$this->agendar();alterarStatusAgenda($id,'Cancelado');
        $this->assertSame('Cancelado',buscarPorCampo(lerJson('agendamentos.json'),'id',$id)['status']);
        $this->expectException(\RuntimeException::class);alterarStatusAgenda(1,'Cancelado');
    }

    public function test_recebimento_nao_duplica_receita(): void
    {
        $id=$this->agendar();$this->sessao('dono');
        $forma=referenciaPorNome($this->pdo,'forma_pagamento','Pix');
        $antes=resumoFinanceiro($this->pdo)['mes_atual']['receita_total'];
        confirmarPagamento($id,$forma);
        $depois=resumoFinanceiro($this->pdo)['mes_atual']['receita_total'];
        $this->assertSame(90.0,$depois-$antes);
        $this->assertTrue(buscarPorCampo(lerJson('agendamentos.json'),'id',$id)['pago']);
        $this->expectException(\InvalidArgumentException::class);confirmarPagamento($id,$forma);
    }

    public function test_despesa_e_valor_decimal_sao_calculados_corretamente(): void
    {
        $this->sessao('dono');$antes=resumoFinanceiro($this->pdo)['mes_atual'];
        registrarLancamento(['tipo'=>'Despesa','descricao'=>'Material teste','valor'=>'123.45']);
        $depois=resumoFinanceiro($this->pdo)['mes_atual'];
        $this->assertEqualsWithDelta(123.45,$depois['despesas_total']-$antes['despesas_total'],0.001);
        $this->assertEqualsWithDelta(-123.45,$depois['lucro']-$antes['lucro'],0.001);
        $this->assertSame('1234.56',valorMonetario('1.234,56'));
    }

    public function test_evolucao_e_odontograma_persistem_no_paciente_correto(): void
    {
        $this->agendar();$this->sessao('dentista');
        registrarEvolucao(['paciente'=>(string)$this->paciente,'procedimento'=>'Procedimento operação','observacoes'=>'Resina e orientação','dentes'=>'11,21']);
        $pr=buscarPorCampo(lerJson('prontuarios.json'),'id_paciente',$this->paciente);
        $this->assertCount(1,$pr['historico']);$this->assertSame('Resina e orientação',$pr['historico'][0]['obs']);
        $this->assertSame('tratado',$pr['odontograma'][11]['status']);
        salvarOdontograma($this->paciente,[11=>['status'=>'precisaTratamento','notas'=>'Reavaliar']]);
        $pr=buscarPorCampo(lerJson('prontuarios.json'),'id_paciente',$this->paciente);
        $this->assertSame('Reavaliar',$pr['odontograma'][11]['notas']);
    }

    public function test_token_senha_expira_e_e_de_uso_unico(): void
    {
        $this->sessao('dono');$token=emitirRecuperacao($this->usuarios['cliente']);
        $this->assertNotNull(consultarRecuperacao($token));
        redefinirSenha($token,'SenhaNova123');
        $this->assertNull(consultarRecuperacao($token));
        $this->assertIsArray(autenticarUsuario('operacao.cliente@teste.local','SenhaNova123','cliente'));
        $token=emitirRecuperacao($this->usuarios['cliente']);
        $this->pdo->exec("UPDATE recuperacao_senha SET expira_em=now()-interval '1 minute' WHERE id_usuario={$this->usuarios['cliente']}");
        $this->assertNull(consultarRecuperacao($token));
    }
}
