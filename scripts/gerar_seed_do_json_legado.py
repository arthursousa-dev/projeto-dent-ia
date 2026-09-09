"""
scripts/gerar_seed_do_json_legado.py

Ferramenta de migração usada UMA VEZ para converter os antigos arquivos
JSON (database/dados_legado/*.json) em instruções INSERT para o
PostgreSQL (database/seed.sql).

Mantido no repositório como documentação do processo de migração —
não faz parte do fluxo normal da aplicação, que já lê e escreve
direto no banco via includes/db.php.

Uso: python3 scripts/gerar_seed_do_json_legado.py
"""
import json, os

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = os.path.join(RAIZ, "database", "dados_legado")

def load(name):
    with open(os.path.join(BASE, name), encoding="utf-8") as f:
        return json.load(f)

def esc(v):
    """Escapa string para literal SQL Postgres."""
    if v is None:
        return "NULL"
    if isinstance(v, bool):
        return "true" if v else "false"
    if isinstance(v, (int, float)):
        return str(v)
    return "'" + str(v).replace("'", "''") + "'"

def pg_array(lst):
    itens = ",".join('"' + str(x).replace('"', '\\"') + '"' for x in lst)
    return "'{" + itens + "}'"

out = []
out.append("-- ============================================================")
out.append("-- DENT IA — Dados de demonstração (migrados do armazenamento")
out.append("-- em JSON original para o schema PostgreSQL)")
out.append("-- ============================================================\n")

# ---------------- usuarios ----------------
usuarios = load("usuarios.json")
out.append("-- usuarios")
out.append("INSERT INTO usuarios (id, email, senha_hash, tipo, nome, cpf, telefone, nascimento, cargo, status, criado_em) VALUES")
linhas = []
for u in usuarios:
    linhas.append(f"({u['id']}, {esc(u['email'])}, {esc(u['senha'])}, {esc(u['tipo'])}, {esc(u['nome'])}, {esc(u.get('cpf'))}, {esc(u.get('telefone'))}, {esc(u.get('nascimento'))}, {esc(u.get('cargo'))}, {esc(u.get('status','ativo'))}, {esc(u.get('criado_em'))}::timestamp)")
out.append(",\n".join(linhas) + ";")
out.append(f"SELECT setval('usuarios_id_seq', {max(u['id'] for u in usuarios)});\n")

# ---------------- clinica ----------------
c = load("clinica.json")
out.append("-- clinica (linha única)")
out.append("INSERT INTO clinica (id, nome, razao_social, cnpj, telefone, email, endereco, bairro, cidade, estado, cep, horario, logo_texto, cor_primaria, cor_destaque, limite_agendamentos_dia, antecedencia_min_dias, atualizado_em) VALUES")
out.append(f"(1, {esc(c['nome'])}, {esc(c['razao_social'])}, {esc(c['cnpj'])}, {esc(c['telefone'])}, {esc(c['email'])}, {esc(c['endereco'])}, {esc(c['bairro'])}, {esc(c['cidade'])}, {esc(c['estado'])}, {esc(c['cep'])}, {esc(c['horario'])}, {esc(c['logo_texto'])}, {esc(c['cor_primaria'])}, {esc(c['cor_destaque'])}, {c['limite_agendamentos_dia']}, {c['antecedencia_min_dias']}, {esc(c['atualizado_em'])}::timestamp);\n")

# ---------------- pacientes ----------------
pacientes = load("pacientes.json")
out.append("-- pacientes")
out.append("INSERT INTO pacientes (id, id_usuario, nome, cpf, rg, email, telefone, telefone_emergencia, nascimento, sexo, estado_civil, profissao, plano, numero_carteirinha, endereco, bairro, cidade, estado, cep, alergias, medicamentos_em_uso, doencas_preexistentes, observacoes, status, criado_em) VALUES")
linhas = []
for p in pacientes:
    linhas.append(f"({p['id']}, {p.get('id_usuario') or 'NULL'}, {esc(p['nome'])}, {esc(p['cpf'])}, {esc(p.get('rg'))}, {esc(p['email'])}, {esc(p['telefone'])}, {esc(p.get('telefone_emergencia'))}, {esc(p['nascimento'])}, {esc(p.get('sexo'))}, {esc(p.get('estado_civil'))}, {esc(p.get('profissao'))}, {esc(p.get('plano','Particular'))}, {esc(p.get('numero_carteirinha') or None)}, {esc(p.get('endereco'))}, {esc(p.get('bairro'))}, {esc(p.get('cidade'))}, {esc(p.get('estado'))}, {esc(p.get('cep'))}, {esc(p.get('alergias'))}, {esc(p.get('medicamentos_em_uso'))}, {esc(p.get('doencas_preexistentes'))}, {esc(p.get('observacoes'))}, {esc(p.get('status','Ativo').lower())}, {esc(p.get('criado_em'))}::timestamp)")
out.append(",\n".join(linhas) + ";")
out.append(f"SELECT setval('pacientes_id_seq', {max(p['id'] for p in pacientes)});\n")

# ---------------- dentistas ----------------
dentistas = load("dentistas.json")
out.append("-- dentistas")
out.append("INSERT INTO dentistas (id, id_usuario, nome, email, telefone, cpf, nascimento, cro, especialidade, titulacao, faculdade, ano_formatura, admissao, cor, sala, status, horarios, dias_atendimento) VALUES")
linhas = []
for d in dentistas:
    linhas.append(f"({d['id']}, {d.get('id_usuario') or 'NULL'}, {esc(d['nome'])}, {esc(d['email'])}, {esc(d['telefone'])}, {esc(d['cpf'])}, {esc(d.get('nascimento'))}, {esc(d['cro'])}, {esc(d['especialidade'])}, {esc(d.get('titulacao'))}, {esc(d.get('faculdade'))}, {d.get('ano_formatura') or 'NULL'}, {esc(d.get('admissao'))}, {esc(d.get('cor','#00c9a7'))}, {esc(d.get('sala'))}, {esc(d.get('status','Ativo').lower())}, {pg_array(d.get('horarios',[]))}, {pg_array(d.get('dias_atendimento',[]))})")
out.append(",\n".join(linhas) + ";")
out.append(f"SELECT setval('dentistas_id_seq', {max(d['id'] for d in dentistas)});\n")

# ---------------- procedimentos ----------------
procedimentos = load("procedimentos.json")
out.append("-- procedimentos")
out.append("INSERT INTO procedimentos (id, nome, categoria, duracao_min, valor_base) VALUES")
linhas = [f"({p['id']}, {esc(p['nome'])}, {esc(p['categoria'])}, {p['duracao_min']}, {p['valor_base']})" for p in procedimentos]
out.append(",\n".join(linhas) + ";")
out.append(f"SELECT setval('procedimentos_id_seq', {max(p['id'] for p in procedimentos)});\n")

# ---------------- dentista_procedimentos ----------------
nome_para_id_proc = {p['nome']: p['id'] for p in procedimentos}
out.append("-- dentista_procedimentos")
linhas = []
for d in dentistas:
    for nome_proc in d.get('procedimentos', []):
        idp = nome_para_id_proc.get(nome_proc)
        if idp:
            linhas.append(f"({d['id']}, {idp})")
if linhas:
    out.append("INSERT INTO dentista_procedimentos (id_dentista, id_procedimento) VALUES")
    out.append(",\n".join(linhas) + ";\n")

# ---------------- agendamentos ----------------
agendamentos = load("agendamentos.json")
nome_para_id_dentista = {d['nome']: d['id'] for d in dentistas}
out.append("-- agendamentos")
out.append("INSERT INTO agendamentos (id, id_paciente, id_dentista, id_procedimento, data, hora, sala, status, valor, pago, forma_pagamento, observacoes) VALUES")
linhas = []
for a in agendamentos:
    idp = nome_para_id_proc.get(a.get('servico'))
    linhas.append(f"({a['id']}, {a['id_paciente']}, {a['id_dentista']}, {idp or 'NULL'}, {esc(a['data'])}, {esc(a['hora'])}, {esc(a.get('sala'))}, {esc(a['status'])}, {a['valor']}, {esc(a['pago'])}, {esc(a.get('forma_pagamento'))}, {esc(a.get('observacoes') or None)})")
out.append(",\n".join(linhas) + ";")
out.append(f"SELECT setval('agendamentos_id_seq', {max(a['id'] for a in agendamentos)});\n")

# ---------------- prontuarios + odontograma ----------------
prontuarios = load("prontuarios.json")
out.append("-- prontuarios")
linhas = [f"({p['id']}, {p['id_paciente']})" for p in prontuarios]
out.append("INSERT INTO prontuarios (id, id_paciente) VALUES")
out.append(",\n".join(linhas) + ";")
out.append(f"SELECT setval('prontuarios_id_seq', {max(p['id'] for p in prontuarios)});\n")

out.append("-- odontograma_dentes + odontograma_procedimentos")
dente_linhas = []
proc_linhas = []
dente_id_counter = 1
proc_id_counter = 1
dente_id_map = []  # (id_prontuario, numero_dente) -> id gerado
for p in prontuarios:
    for numero, info in p['odontograma'].items():
        status = info['status']
        notas = info.get('notas')
        dente_linhas.append(f"({dente_id_counter}, {p['id']}, {numero}, {esc(status)}, {esc(notas)})")
        for descricao in info.get('procedimentos', []):
            proc_linhas.append(f"({proc_id_counter}, {dente_id_counter}, {esc(descricao)})")
            proc_id_counter += 1
        dente_id_counter += 1

out.append("INSERT INTO odontograma_dentes (id, id_prontuario, numero_dente, status, notas) VALUES")
out.append(",\n".join(dente_linhas) + ";")
out.append(f"SELECT setval('odontograma_dentes_id_seq', {dente_id_counter - 1});\n")

if proc_linhas:
    out.append("INSERT INTO odontograma_procedimentos (id, id_dente, descricao) VALUES")
    out.append(",\n".join(proc_linhas) + ";")
    out.append(f"SELECT setval('odontograma_procedimentos_id_seq', {proc_id_counter - 1});\n")

# ---------------- faturamento_mensal ----------------
fat = load("faturamento.json")
out.append("-- faturamento_mensal")
out.append("INSERT INTO faturamento_mensal (ano, mes_num, receita, despesas, qtd_procedimentos) VALUES")
linhas = [f"({m['ano']}, {m['mes_num']}, {m['receita']}, {m['despesas']}, {m['procedimentos']})" for m in fat['historico_mensal']]
out.append(",\n".join(linhas) + ";\n")

with open(os.path.join(RAIZ, "database", "seed.sql"), "w", encoding="utf-8") as f:
    f.write("\n".join(out))

print("seed.sql gerado com sucesso")
