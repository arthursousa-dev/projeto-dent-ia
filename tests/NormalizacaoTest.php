<?php
namespace Tests;

use App\Config\Database;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class NormalizacaoTest extends TestCase
{
    private PDO $pdo;
    private int $pessoa;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pdo->beginTransaction();
        $this->pessoa = (int) $this->pdo->query("INSERT INTO pessoa (nome,cpf) VALUES ('Teste de domínio','12345678909') RETURNING id")->fetchColumn();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
    }

    private function rejeitar(string $sql, string $estado): void
    {
        $this->pdo->exec('SAVEPOINT dominio');
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT dominio');
            $this->assertSame($estado, $e->getCode());
            return;
        }
        $this->fail('O banco aceitou dados inválidos');
    }

    public function test_principal_unico_e_numero_compartilhavel(): void
    {
        $id = $this->pessoa;
        $this->pdo->exec("INSERT INTO telefone (id_pessoa,numero,finalidade) VALUES ($id,'67912345678','principal')");
        $this->rejeitar("INSERT INTO telefone (id_pessoa,numero,finalidade) VALUES ($id,'67912345679','principal')", '23505');
        $outro = $this->pdo->query("INSERT INTO pessoa (nome,cpf) VALUES ('Outro','98765432100') RETURNING id")->fetchColumn();
        $this->assertSame(1, $this->pdo->exec("INSERT INTO telefone (id_pessoa,numero,finalidade) VALUES ($outro,'67912345678','principal')"));
        $this->rejeitar("INSERT INTO telefone (id_pessoa,numero,finalidade) VALUES ($id,'67912345678','secundario')", '23505');
    }

    public function test_numero_invalido_e_xor(): void
    {
        $id = $this->pessoa;
        $this->rejeitar("INSERT INTO telefone (id_pessoa,numero,finalidade) VALUES ($id,'123','principal')", '23514');
        $this->rejeitar("INSERT INTO telefone (id_pessoa,numero,finalidade) VALUES ($id,'10912345678','principal')", '23514');
        $this->rejeitar("INSERT INTO telefone (numero,finalidade) VALUES ('67912345678','principal')", '23514');
        $this->pdo->exec("INSERT INTO clinica (id,nome,razao_social,cnpj,email,horario,logo_texto,cor_primaria,cor_destaque)
            VALUES (1,'Teste','Teste','12345678000199','teste@local.com','08:00','TESTE','#123456','#654321') ON CONFLICT DO NOTHING");
        $this->rejeitar("INSERT INTO telefone (id_pessoa,id_clinica,numero,finalidade) VALUES ($id,1,'67912345678','principal')", '23514');
        $this->pdo->exec("DELETE FROM telefone WHERE id_clinica=1");
        $this->pdo->exec("INSERT INTO telefone (id_clinica,numero,finalidade) VALUES (1,'67912345678','principal')");
        $this->rejeitar("INSERT INTO telefone (id_clinica,numero,finalidade) VALUES (1,'67912345679','principal')", '23505');
    }

    public function test_cpf_invalido(): void
    {
        foreach (['00000000000','11111111111','12345678900'] as $cpf) {
            $this->rejeitar("INSERT INTO pessoa (nome,cpf) VALUES ('Inválido','$cpf')", '23514');
        }
        $this->rejeitar("INSERT INTO pessoa (nome,cpf) VALUES ('Inválido','123.456.789-09')", '22001');
    }

    private function agenda(): array
    {
        $paciente = (int) $this->pdo->query("INSERT INTO pacientes (id_pessoa) VALUES ({$this->pessoa}) RETURNING id")->fetchColumn();
        $outraPessoa = $this->pdo->query("INSERT INTO pessoa (nome,cpf) VALUES ('Dentista Teste','98765432100') RETURNING id")->fetchColumn();
        $esp = referenciaPorNome($this->pdo,'especialidade','Teste',true);
        $sala = referenciaPorNome($this->pdo,'sala','Teste',true);
        $categoria = referenciaPorNome($this->pdo,'categoria_procedimento','Teste',true);
        $dentista = $this->pdo->query("INSERT INTO dentistas (id_pessoa,cro_uf,cro_numero,id_especialidade,id_sala)
            VALUES ($outraPessoa,'MS','999999',$esp,$sala) RETURNING id")->fetchColumn();
        $proc = $this->pdo->query("INSERT INTO procedimentos (nome,id_categoria,duracao_min,valor_base)
            VALUES ('Procedimento Teste',$categoria,30,90) RETURNING id")->fetchColumn();
        $this->pdo->exec("INSERT INTO dentista_procedimentos VALUES ($dentista,$proc)");
        $id = $this->pdo->query("INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor)
            VALUES ($paciente,$dentista,$proc,$sala,'2030-01-10','08:00',90) RETURNING id")->fetchColumn();
        return compact('id','paciente','dentista','proc','sala');
    }

    public function test_conflitos_dentista_paciente_sala_e_cancelamento(): void
    {
        $a = $this->agenda();
        $outroPaciente = $this->pdo->query("INSERT INTO pessoa (nome,cpf) VALUES ('Paciente B','11144477735') RETURNING id")->fetchColumn();
        $outroPaciente = $this->pdo->query("INSERT INTO pacientes (id_pessoa) VALUES ($outroPaciente) RETURNING id")->fetchColumn();
        $pessoaD = $this->pdo->query("INSERT INTO pessoa (nome,cpf) VALUES ('Dentista B','52998224725') RETURNING id")->fetchColumn();
        $esp = referenciaPorNome($this->pdo,'especialidade','Teste',true);
        $d = $this->pdo->query("INSERT INTO dentistas (id_pessoa,cro_uf,cro_numero,id_especialidade) VALUES ($pessoaD,'MS','999998',$esp) RETURNING id")->fetchColumn();
        $this->pdo->exec("INSERT INTO dentista_procedimentos VALUES ($d,{$a['proc']})");
        $s = referenciaPorNome($this->pdo,'sala','Teste B',true);
        // Cada caso isola uma das três restrições de horário.
        foreach ([[$outroPaciente,$a['dentista'],$s],[$a['paciente'],$d,$s],[$outroPaciente,$d,$a['sala']]] as [$p,$dent,$sala]) {
            $this->rejeitar("INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor)
                VALUES ($p,$dent,{$a['proc']},$sala,'2030-01-10','08:00',90)", '23505');
        }
        $this->pdo->exec("UPDATE agendamentos SET status='Cancelado' WHERE id={$a['id']}");
        $this->assertSame(1,$this->pdo->exec("INSERT INTO agendamentos (id_paciente,id_dentista,id_procedimento,id_sala,data,hora,valor)
            VALUES ({$a['paciente']},{$a['dentista']},{$a['proc']},{$a['sala']},'2030-01-10','08:00',90)"));
    }

    public function test_pagamento_deriva_pago(): void
    {
        $a = $this->agenda();
        $id = $a['id'];
        $this->assertFalse($this->pdo->query("SELECT pago FROM vw_agendamentos WHERE id=$id")->fetchColumn());
        $forma = referenciaPorNome($this->pdo,'forma_pagamento','Pix',true);
        $this->pdo->exec("INSERT INTO pagamento (id_agendamento,id_forma_pagamento,valor) VALUES ($id,$forma,90)");
        $this->assertFalse($this->pdo->query("SELECT pago FROM vw_agendamentos WHERE id=$id")->fetchColumn());
        $this->pdo->exec("UPDATE pagamento SET pago_em=now() WHERE id_agendamento=$id");
        $this->assertTrue($this->pdo->query("SELECT pago FROM vw_agendamentos WHERE id=$id")->fetchColumn());
    }

    public function test_fdi_fk_composta_e_exclusao_restrita(): void
    {
        $a = $this->agenda();
        $categoria = referenciaPorNome($this->pdo,'categoria_procedimento','Teste',true);
        $proc = $this->pdo->query("INSERT INTO procedimentos (nome,id_categoria,duracao_min,valor_base)
            VALUES ('Não habilitado',$categoria,30,90) RETURNING id")->fetchColumn();
        $this->rejeitar("UPDATE agendamentos SET id_procedimento=$proc WHERE id={$a['id']}", '23503');
        // ON DELETE RESTRICT responde com restrict_violation (23001), não foreign_key_violation (23503).
        $this->rejeitar("DELETE FROM pacientes WHERE id={$a['paciente']}", '23001');
        $pr = $this->pdo->query("INSERT INTO prontuarios (id_paciente) VALUES ({$a['paciente']}) RETURNING id")->fetchColumn();
        foreach ([10,19,49,56,89,99] as $n) $this->rejeitar("INSERT INTO odontograma_dentes (id_prontuario,numero_dente) VALUES ($pr,$n)", '23514');
        $this->assertSame(1,$this->pdo->exec("INSERT INTO odontograma_dentes (id_prontuario,numero_dente) VALUES ($pr,55)"));
    }
}
