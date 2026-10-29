"""Gera dados PostgreSQL normalizados, sem modificar os JSONs legados."""
import json
import re
from datetime import date, timedelta
from zoneinfo import ZoneInfo
from datetime import datetime
import argparse

parser=argparse.ArgumentParser()
parser.add_argument("--data",type=date.fromisoformat,default=datetime.now(ZoneInfo("America/Cuiaba")).date())
HOJE=parser.parse_args().data
from pathlib import Path

RAIZ = Path(__file__).resolve().parents[1]
BASE = RAIZ / 'database/dados_legado'
SQL = ['-- Dados fictícios de demonstração', 'BEGIN;']
TABELAS = set()
REFERENCIAS = {}


def load(nome):
    return json.loads((BASE / (nome + '.json')).read_text())


def digitos(valor):
    return re.sub(r'\D', '', valor or '')


def esc(valor):
    if valor is None:
        return 'NULL'
    if isinstance(valor, bool):
        return 'true' if valor else 'false'
    if isinstance(valor, (int, float)):
        return str(valor)
    return "'" + str(valor).replace("'", "''") + "'"


def inserir(tabela, **campos):
    TABELAS.add(tabela)
    SQL.append(f"INSERT INTO {tabela} ({', '.join(campos)}) VALUES ({', '.join(map(esc, campos.values()))});")


def referencia(tabela, nome):
    if not nome:
        return None
    chave = (tabela, nome)
    if chave not in REFERENCIAS:
        # Referências iniciais pertencem ao schema.
        SQL.append(f"INSERT INTO {tabela} (nome) VALUES ({esc(nome)}) ON CONFLICT (nome) DO NOTHING;")
        REFERENCIAS[chave] = f"(SELECT id FROM {tabela} WHERE nome={esc(nome)})"
    return REFERENCIAS[chave]


def inserir_refs(tabela, campos, refs):
    TABELAS.add(tabela)
    valores = [esc(v) for v in campos.values()] + list(refs.values())
    SQL.append(f"INSERT INTO {tabela} ({', '.join(list(campos) + list(refs))}) VALUES ({', '.join(valores)});")


def cpf_ficticio(numero):
    base = f'{900000000 + numero:09d}'
    for tamanho in (9, 10):
        resto = sum(int(v) * (tamanho + 1 - i) for i, v in enumerate(base)) * 10 % 11
        base += str(0 if resto == 10 else resto)
    return base


def endereco(dados, identidade):
    cidade, uf = dados.get('cidade'), dados.get('estado')
    id_cidade = 'NULL'
    if cidade and uf:
        SQL.append(f"INSERT INTO cidade (nome, uf) VALUES ({esc(cidade)}, {esc(uf)}) ON CONFLICT (nome, uf) DO NOTHING;")
        id_cidade = f"(SELECT id FROM cidade WHERE nome={esc(cidade)} AND uf={esc(uf)})"
    logradouro, separador, numero = (dados.get('endereco') or '').rpartition(', ')
    if not separador:
        logradouro, numero = dados.get('endereco'), None
    inserir_refs('endereco', dict(id=identidade, logradouro=logradouro, numero=numero,
                                 bairro=dados.get('bairro'), cep=digitos(dados.get('cep')) or None),
                 dict(id_cidade=id_cidade))
    return identidade


usuarios = load('usuarios')
pacientes = load('pacientes')
dentistas = load('dentistas')
procedimentos = load('procedimentos')
agendamentos = load('agendamentos')
prontuarios = load('prontuarios')
# Um hash fixo, produzido pelo password_hash do PHP, mantém o seed reproduzível.
HASH = '$2y$10$GEmpivxXPvYn507Y6lkzduWXJBbiVyxRSzUyLMmKHKRZMPmNRok02'
usuarios.append(dict(id=8, email='recepcao2@dentai.com', tipo='recepcionista', nome='Beatriz Lima',
                     telefone='(67) 99211-9000', nascimento='1993-08-15', status='ativo', criado_em='2024-02-01', senha=HASH))
for id_paciente, nome in [(9, 'Mariana Ribeiro'), (10, 'Rafael Nunes')]:
    p = dict(pacientes[0], id=id_paciente, id_usuario=None, nome=nome,
             email=f'paciente{id_paciente}@dentia.local', nascimento='1992-06-12',
             telefone=f'(67) 99211-{id_paciente:04d}', telefone_emergencia=None,
             endereco=f'Rua dos Ipês, {id_paciente * 10}', alergias='Nenhuma conhecida',
             medicamentos_em_uso='Nenhum', doencas_preexistentes='Nenhuma', observacoes='Consulta preventiva.', criado_em=(HOJE-timedelta(days=180)).isoformat())
    pacientes.append(p)

# Contato clínico é independente do e-mail de autenticação.
contatos = {p['id_usuario']: p for p in pacientes if p.get('id_usuario')}
contatos.update({d['id_usuario']: d for d in dentistas if d.get('id_usuario')})
for u in usuarios:
    contato = contatos.get(u['id'], u)
    inserir('pessoa', id=u['id'], nome=u['nome'], cpf=cpf_ficticio(u['id']),
            nascimento=u.get('nascimento'), email=contato['email'], rg=contato.get('rg'), sexo=contato.get('sexo'))
    inserir('usuarios', id=u['id'], id_pessoa=u['id'], email=u['email'], senha_hash=u['senha'], tipo=u['tipo'],
            status=u['status'], criado_em=u['criado_em'])
    inserir('telefone', id_pessoa=u['id'], numero=digitos(u['telefone']), finalidade='principal')

for p in pacientes:
    identidade = p.get('id_usuario') or 100 + p['id']
    if not p.get('id_usuario'):
        inserir('pessoa', id=identidade, nome=p['nome'], cpf=cpf_ficticio(identidade),
                rg=p.get('rg'), nascimento=p['nascimento'], sexo=p.get('sexo'), email=p['email'])
        inserir('telefone', id_pessoa=identidade, numero=digitos(p['telefone']), finalidade='principal')
        if p['id'] > 8:
            inserir('usuarios', id=100 + p['id'], id_pessoa=identidade, email=p['email'], senha_hash=HASH,
                    tipo='cliente', status=p.get('status', 'Ativo').lower(), criado_em=p.get('criado_em'))
    if p.get('telefone_emergencia'):
        inserir('telefone', id_pessoa=identidade, numero=digitos(p['telefone_emergencia']), finalidade='emergencia')
    inserir_refs('pacientes', dict(id=p['id'], id_pessoa=identidade, id_endereco=endereco(p, 100 + p['id']),
                 estado_civil=p.get('estado_civil'), profissao=p.get('profissao'), numero_carteirinha=p.get('numero_carteirinha') or None,
                 observacoes=p.get('observacoes'), status=p.get('status', 'Ativo').lower(), criado_em=p.get('criado_em')),
                 dict(id_plano=referencia('plano_saude', p.get('plano') or 'Particular')))
    for chave, tipo in [('alergias', 'alergia'), ('medicamentos_em_uso', 'medicamento'), ('doencas_preexistentes', 'condicao')]:
        for descricao in dict.fromkeys(re.split(r',\s*', p.get(chave) or '')):
            if descricao.strip():
                inserir('historico_clinico', id_paciente=p['id'], tipo=tipo, descricao=descricao.strip())

c = load('clinica')
inserir('clinica', id=1, **{k: c[k] for k in ['nome', 'razao_social', 'email', 'horario', 'logo_texto', 'cor_primaria',
        'cor_destaque', 'limite_agendamentos_dia', 'antecedencia_min_dias', 'atualizado_em']},
        cnpj=digitos(c['cnpj']), id_endereco=endereco(c, 1))
inserir('telefone', id_clinica=1, numero=digitos(c['telefone']), finalidade='principal')
for dia in range(1,7):
    inserir('funcionamento_clinica',id_clinica=1,id_dia_semana=dia,abertura='08:00',fechamento='13:00' if dia==6 else '18:00')

for d in dentistas:
    inserir_refs('dentistas', dict(id=d['id'], id_pessoa=d['id_usuario'], cro_uf=d['cro'][4:6],
        cro_numero=digitos(d['cro']), **{k: d.get(k) for k in ['titulacao', 'faculdade', 'ano_formatura', 'admissao', 'cor']},
        status=d['status'].lower()), dict(id_especialidade=referencia('especialidade', d['especialidade']),
        id_sala=referencia('sala', d['sala'])))
    dias = {'Domingo':0,'Segunda':1,'Terça':2,'Quarta':3,'Quinta':4,'Sexta':5,'Sábado':6}
    for dia in d['dias_atendimento']:
        for hora in d['horarios']:
            inserir('disponibilidade_dentista', id_dentista=d['id'], id_dia_semana=dias[dia], hora=hora)

for p in procedimentos:
    inserir_refs('procedimentos', dict(id=p['id'], nome=p['nome'], duracao_min=p['duracao_min'], valor_base=p['valor_base']),
                 dict(id_categoria=referencia('categoria_procedimento', p['categoria'])))
ids_procedimentos = {p['nome']: p['id'] for p in procedimentos}
for d in dentistas:
    for nome in d['procedimentos']:
        if nome in ids_procedimentos:
            inserir('dentista_procedimentos', id_dentista=d['id'], id_procedimento=ids_procedimentos[nome])

# Histórico de seis meses e agenda futura, sem alterar os JSONs originais.
ids_proc={p['id']:p for p in procedimentos}
por_dent={d['id']:d for d in dentistas}
ocupados=[]
for a in agendamentos:
    duracao=ids_proc[ids_procedimentos[a['servico']]]['duracao_min']
    inicio=datetime.fromisoformat(a['data']+'T'+a['hora'])
    ocupados.append((a['id_paciente'],a['id_dentista'],a['sala'],inicio,inicio+timedelta(minutes=duracao)))

def acrescentar(paciente,dentista,servico,data,hora,status,pago):
    inicio=datetime.combine(data,datetime.strptime(hora,'%H:%M').time())
    fim=inicio+timedelta(minutes=ids_proc[ids_procedimentos[servico]]['duracao_min'])
    sala=por_dent[dentista]['sala']
    if any((p==paciente or d==dentista or s==sala) and inicio<f and fim>i for p,d,s,i,f in ocupados):
        return False
    agendamentos.append(dict(id=max(a['id'] for a in agendamentos)+1,id_paciente=paciente,id_dentista=dentista,
        servico=servico,data=data.isoformat(),hora=hora,sala=sala,status=status,
        valor=ids_proc[ids_procedimentos[servico]]['valor_base'],pago=pago,forma_pagamento='Pix' if pago else '',
        observacoes='Atendimento de demonstração; avaliação e orientações registradas.'))
    if status!='Cancelado':ocupados.append((paciente,dentista,sala,inicio,fim))
    return True

meses=[]
for deslocamento in range(5,-1,-1):
    indice=HOJE.year*12+HOJE.month-1-deslocamento
    primeiro=date(indice//12,indice%12+1,1);meses.append(primeiro)
    for d in dentistas:
        candidatos=[nome for nome in d['procedimentos'] if nome in ids_procedimentos]
        dias={'Segunda':0,'Terça':1,'Quarta':2,'Quinta':3,'Sexta':4,'Sábado':5,'Domingo':6}
        permitidos=[dias[n] for n in d['dias_atendimento']]
        feitas=0;data=primeiro
        while data.month==primeiro.month and data<=HOJE and feitas<5:
            paciente=1+(deslocamento+d['id']*2+feitas)%len(pacientes)
            if data.weekday() in permitidos and acrescentar(paciente,d['id'],candidatos[feitas%len(candidatos)],data,
                    '14:00','Concluída',feitas!=4):feitas+=1
            data+=timedelta(days=1)

# Hoje e próximos quinze dias: diferentes etapas da recepção.
ativos=[p['id'] for p in pacientes if p.get('status','Ativo').lower()=='ativo']
for deslocamento in range(16):
    data=HOJE+timedelta(days=deslocamento)
    for d in dentistas:
        dias={'Segunda':0,'Terça':1,'Quarta':2,'Quinta':3,'Sexta':4,'Sábado':5,'Domingo':6}
        if data.weekday() not in [dias[n] for n in d['dias_atendimento']]:continue
        servico=next(n for n in d['procedimentos'] if n in ids_procedimentos)
        hora='09:00' if d['id']==2 else '08:00'
        paciente=ativos[(deslocamento*3+d['id'])%len(ativos)]
        status='Aguardando' if deslocamento==0 and d['id']==1 else ('Confirmado' if deslocamento%2 else 'Agendado')
        acrescentar(paciente,d['id'],servico,data,hora,status,False)

for a in agendamentos:
    inserir_refs('agendamentos', {k:a[k] for k in ['id','id_paciente','id_dentista','data','hora','status','valor']}
        | dict(id_procedimento=ids_procedimentos[a['servico']], observacoes=a.get('observacoes') or None),
        dict(id_sala=referencia('sala',a['sala'])))
    if a['forma_pagamento'] or a['pago']:
        inserir_refs('pagamento', dict(id_agendamento=a['id'], valor=a['valor'],
                     pago_em=f"{a['data']} {a['hora']}:00+00" if a['pago'] else None),
                     dict(id_forma_pagamento=referencia('forma_pagamento', a['forma_pagamento'] or 'Pix')))

for p in pacientes:
    if not any(pr['id_paciente']==p['id'] for pr in prontuarios):
        prontuarios.append(dict(id=max(pr['id'] for pr in prontuarios)+1, id_paciente=p['id'], odontograma={
            str(q*10+n): dict(status='saudavel', notas='', procedimentos=[]) for q in range(1,5) for n in range(1,9)}))
dente_id = 0
for pr in prontuarios:
    inserir('prontuarios', id=pr['id'], id_paciente=pr['id_paciente'])
    for numero, info in pr['odontograma'].items():
        dente_id += 1
        inserir('odontograma_dentes', id=dente_id, id_prontuario=pr['id'], numero_dente=int(numero),
                status=info['status'], notas=info.get('notas'))
        for descricao in info.get('procedimentos', []):
            inserir('odontograma_procedimentos', id_dente=dente_id, descricao=descricao)
for a in agendamentos:
    if a['status']!='Concluída':continue
    pr=next(p for p in prontuarios if p['id_paciente']==a['id_paciente'])
    inserir('evolucao_clinica',id_prontuario=pr['id'],id_dentista=a['id_dentista'],id_procedimento=ids_procedimentos[a['servico']],
            id_agendamento=a['id'],observacoes=a['observacoes'] or 'Procedimento concluído, orientações de higiene reforçadas.',
            registrado_em=a['data']+' '+a['hora']+':00-04')

for indice,mes in enumerate(meses):
    for categoria,valor in [('Aluguel',1800),('Folha de pagamento',3000),('Materiais odontológicos',700+indice*65),('Serviços e energia',450+indice*20)]:
        inserir_refs('lancamento_financeiro',dict(data=mes.isoformat(),tipo='Despesa',descricao=categoria+' — '+mes.strftime('%m/%Y'),valor=valor,id_usuario=1),
                     dict(id_categoria=f"(SELECT id FROM categoria_lancamento WHERE nome={esc(categoria)})"))
    inserir_refs('lancamento_financeiro',dict(data=mes.isoformat(),tipo='Receita',descricao='Venda de kits de higiene — '+mes.strftime('%m/%Y'),valor=350+indice*40,id_usuario=1),
                 dict(id_categoria="(SELECT id FROM categoria_lancamento WHERE nome='Receitas diversas')"))
    if mes.month!=HOJE.month or mes.year!=HOJE.year:
        SQL.append(f"INSERT INTO faturamento_mensal (ano,mes_num,receita,despesas,qtd_procedimentos) SELECT ano,mes_num,receita,despesas,procedimentos FROM vw_resumo_financeiro_mensal WHERE ano={mes.year} AND mes_num={mes.month};")

SQL.append("""DO $$ DECLARE r RECORD; maior BIGINT; BEGIN
    FOR r IN SELECT table_name, column_name FROM information_schema.columns
             WHERE table_schema='public' AND is_identity='YES' LOOP
        EXECUTE format('SELECT max(%I) FROM %I', r.column_name, r.table_name) INTO maior;
        PERFORM setval(pg_get_serial_sequence(r.table_name, r.column_name), COALESCE(maior,1), maior IS NOT NULL);
    END LOOP;
END $$;""")
SQL.append('COMMIT;')
(RAIZ/'database/seed.sql').write_text('\n'.join(SQL)+'\n')
print(f'seed.sql: data base {HOJE}, {len(pacientes)} pacientes, {len(agendamentos)} agendamentos; seis meses de movimento.')
