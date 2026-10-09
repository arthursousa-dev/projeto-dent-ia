<?php
namespace Tests;

use App\Config\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class PerfilPersistenciaTest extends TestCase
{
    private PDO $pdo;
    private int $pessoa;
    private int $usuario;
    private int $paciente;
    private int $dentista;
    private ?array $clinica;
    private array $enderecos = [];

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();
        $this->pessoa = (int) $this->pdo->query("INSERT INTO pessoa (nome,cpf,email,nascimento)
            VALUES ('Perfil Teste','52998224725','contato@teste.local','1990-01-01') RETURNING id")->fetchColumn();
        $stmt = $this->pdo->prepare("INSERT INTO usuarios (id_pessoa,email,senha_hash,tipo) VALUES (:pessoa,'login@teste.local',:senha,'dentista') RETURNING id");
        $stmt->execute(['pessoa'=>$this->pessoa,'senha'=>password_hash('123456',PASSWORD_BCRYPT)]);
        $this->usuario = (int) $stmt->fetchColumn();
        $this->paciente = (int) $this->pdo->query("INSERT INTO pacientes (id_pessoa) VALUES ({$this->pessoa}) RETURNING id")->fetchColumn();
        $esp = referenciaPorNome($this->pdo,'especialidade','Especialidade Teste');
        $this->dentista = (int) $this->pdo->query("INSERT INTO dentistas (id_pessoa,cro_uf,cro_numero,id_especialidade)
            VALUES ({$this->pessoa},'MS','999997',$esp) RETURNING id")->fetchColumn();
        upsertTelefone($this->pdo,$this->pessoa,null,'(67) 91234-5678');
        $this->clinica = lerJsonObjeto('clinica.json') ?: null;
        if (!$this->clinica) $this->pdo->exec("INSERT INTO clinica (id,nome,razao_social,cnpj,email,horario,logo_texto,cor_primaria,cor_destaque)
            VALUES (1,'Teste','Teste','12345678000199','teste@local.com','08:00','TESTE','#123456','#654321')");
    }

    protected function tearDown(): void
    {
        $idEndereco = $this->pdo->query("SELECT id_endereco FROM pacientes WHERE id={$this->paciente}")->fetchColumn();
        if ($idEndereco) $this->enderecos[] = (int) $idEndereco;
        $this->pdo->exec("DELETE FROM historico_clinico WHERE id_paciente={$this->paciente}");
        $this->pdo->exec("DELETE FROM disponibilidade_dentista WHERE id_dentista={$this->dentista}");
        $this->pdo->exec("DELETE FROM dentista_procedimentos WHERE id_dentista={$this->dentista}");
        $this->pdo->exec("DELETE FROM dentistas WHERE id={$this->dentista}");
        $this->pdo->exec("DELETE FROM pacientes WHERE id={$this->paciente}");
        $this->pdo->exec("DELETE FROM usuarios WHERE id={$this->usuario}");
        $this->pdo->exec("DELETE FROM telefone WHERE id_pessoa={$this->pessoa}");
        $this->pdo->exec("DELETE FROM pessoa WHERE id={$this->pessoa}");
        if ($this->clinica) {
            salvarJson('clinica.json',$this->clinica);
        } else {
            $id = $this->pdo->query('SELECT id_endereco FROM clinica WHERE id=1')->fetchColumn();
            $this->pdo->exec('DELETE FROM telefone WHERE id_clinica=1');
            $this->pdo->exec('DELETE FROM clinica WHERE id=1');
            if ($id) $this->enderecos[] = (int) $id;
        }
        foreach (array_unique($this->enderecos) as $id) $this->pdo->exec("DELETE FROM endereco WHERE id=$id");
    }

    public function test_perfil_usuario_compartilha_pessoa_e_preserva_email_contato(): void
    {
        $u = buscarPorCampo(lerJson('usuarios.json'),'id',$this->usuario);
        $u['nome']='Nome alterado'; $u['telefone']='(67) 99876-5432'; $u['nascimento']='1991-02-03';
        $this->assertTrue(salvarJson('usuarios.json',[$u]));
        $p = buscarPorCampo(lerJson('pacientes.json'),'id',$this->paciente);
        $this->assertSame('Nome alterado',$p['nome']);
        $this->assertSame('(67) 99876-5432',$p['telefone']);
        $this->assertSame('contato@teste.local',$p['email']);
        $this->assertSame('1991-02-03',$p['nascimento']);
        $this->assertIsArray(autenticarUsuario('login@teste.local','123456','dentista'));
    }

    public function test_paciente_endereco_plano_historico_e_telefone_invalido(): void
    {
        $this->assertTrue(salvarJson('pacientes.json',[[
            'id'=>$this->paciente, 'nome'=>'Paciente alterado', 'telefone'=>'inválido',
            'telefone_emergencia'=>'(67) 92345-6789', 'endereco'=>'Rua Teste, 25', 'bairro'=>'Centro',
            'cidade'=>'Campo Grande', 'estado'=>'MS', 'cep'=>'79002-000', 'plano'=>'Plano Teste',
            'medicamentos_em_uso'=>'Atenolol 25mg, Losartana 50mg', 'alergias'=>'Lidocaína',
        ]]));
        $p = buscarPorCampo(lerJson('pacientes.json'),'id',$this->paciente);
        $this->assertSame('Paciente alterado',$p['nome']);
        $this->assertSame('(67) 91234-5678',$p['telefone']);
        $this->assertSame('(67) 92345-6789',$p['telefone_emergencia']);
        $this->assertSame('Rua Teste, 25',$p['endereco']);
        $this->assertSame('Plano Teste',$p['plano']);
        $this->assertSame(2,(int)$this->pdo->query("SELECT count(*) FROM historico_clinico WHERE id_paciente={$this->paciente} AND tipo='medicamento'")->fetchColumn());
        $this->assertSame('25',$this->pdo->query("SELECT e.numero FROM endereco e JOIN pacientes p ON p.id_endereco=e.id WHERE p.id={$this->paciente}")->fetchColumn());
    }

    public function test_dentista_sala_disponibilidade_e_titulacao(): void
    {
        $this->assertTrue(salvarJson('dentistas.json',[[
            'id'=>$this->dentista, 'nome'=>'Dentista alterado', 'sala'=>'Sala Teste', 'titulacao'=>'Especialista',
            'horarios'=>['08:00','09:00'], 'dias_atendimento'=>['Segunda','Quarta']
        ]]));
        $d = buscarPorCampo(lerJson('dentistas.json'),'id',$this->dentista);
        $this->assertSame('Sala Teste',$d['sala']);
        $this->assertSame('Especialista',$d['titulacao']);
        $this->assertSame(['08:00','09:00'],$d['horarios']);
        $this->assertSame(['Segunda','Quarta'],$d['dias_atendimento']);
        $this->assertSame(4,(int)$this->pdo->query("SELECT count(*) FROM disponibilidade_dentista WHERE id_dentista={$this->dentista}")->fetchColumn());
    }

    public function test_clinica_grava_campos_normalizados(): void
    {
        $c=lerJsonObjeto('clinica.json');
        $c['nome']='Clínica alterada';$c['telefone']='(67) 93456-7890';$c['endereco']='Rua Nova, 99';
        $c['cidade']='Campo Grande';$c['estado']='MS';$c['cep']='79002-000';
        $this->assertTrue(salvarJson('clinica.json',$c));
        $l=lerJsonObjeto('clinica.json');
        $this->assertSame('Clínica alterada',$l['nome']);
        $this->assertSame('(67) 93456-7890',$l['telefone']);
        $this->assertSame('Rua Nova, 99',$l['endereco']);
        $this->assertSame(soDigitos($c['cnpj']), $this->pdo->query('SELECT cnpj FROM clinica WHERE id=1')->fetchColumn());
    }

    public function test_falha_reverte_transacao_completa(): void
    {
        $this->assertFalse(salvarJson('pacientes.json',[
            ['id'=>$this->paciente,'nome'=>'Não persistir'],
            ['id'=>$this->paciente,'nome'=>'CPF inválido','cpf'=>'00000000000'],
        ]));
        $p=buscarPorCampo(lerJson('pacientes.json'),'id',$this->paciente);
        $this->assertSame('Perfil Teste',$p['nome']);
    }
    public function test_insercao_paciente_vincula_usuario_e_ajusta_identity(): void
    {
        $this->pdo->exec("DELETE FROM pacientes WHERE id={$this->paciente}");
        $this->paciente = (int)$this->pdo->query('SELECT COALESCE(max(id),0)+100 FROM pacientes')->fetchColumn();
        $this->assertTrue(salvarJson('pacientes.json',[[
            'id'=>$this->paciente,'id_usuario'=>$this->usuario,'nome'=>'Novo paciente',
            'email'=>'contato@teste.local','plano'=>'Particular'
        ]]));
        $p=buscarPorCampo(lerJson('pacientes.json'),'id',$this->paciente);
        $this->assertSame($this->usuario,$p['id_usuario']);
        $this->assertSame($this->pessoa,(int)$this->pdo->query("SELECT id_pessoa FROM pacientes WHERE id={$this->paciente}")->fetchColumn());
        $this->assertGreaterThan($this->paciente,(int)$this->pdo->query("SELECT nextval(pg_get_serial_sequence('pacientes','id'))")->fetchColumn());
    }

}
