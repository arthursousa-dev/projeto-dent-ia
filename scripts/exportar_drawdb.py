"""Exporta o catálogo PostgreSQL para JSON nativo do drawDB."""
import argparse
import json
import os
import re
import subprocess
from pathlib import Path

parser=argparse.ArgumentParser()
parser.add_argument('destino',nargs='?',default='database/dentia-drawdb.json')
args=parser.parse_args()
env=os.environ.copy()
for pg,db in [('PGHOST','DB_HOST'),('PGPORT','DB_PORT'),('PGDATABASE','DB_NAME'),('PGUSER','DB_USER'),('PGPASSWORD','DB_PASS')]:
    if db in env:env[pg]=env[db]
query="""
SELECT json_build_object(
 'tables',(SELECT json_agg(json_build_object('oid',c.oid,'name',c.relname,'comment',obj_description(c.oid),
    'columns',(SELECT json_agg(json_build_object('num',a.attnum,'name',a.attname,'type',format_type(a.atttypid,a.atttypmod),
        'notnull',a.attnotnull,'identity',a.attidentity,'default',pg_get_expr(d.adbin,d.adrelid),'comment',col_description(c.oid,a.attnum)) ORDER BY a.attnum)
        FROM pg_attribute a LEFT JOIN pg_attrdef d ON d.adrelid=a.attrelid AND d.adnum=a.attnum
        WHERE a.attrelid=c.oid AND a.attnum>0 AND NOT a.attisdropped),
    'constraints',(SELECT json_agg(json_build_object('name',co.conname,'kind',co.contype,'keys',co.conkey,
        'ref',co.confrelid,'refkeys',co.confkey,'delete',co.confdeltype,'update',co.confupdtype,'definition',pg_get_constraintdef(co.oid)))
        FROM pg_constraint co WHERE co.conrelid=c.oid),
    'indexes',(SELECT json_agg(json_build_object('name',ci.relname,'unique',i.indisunique,'primary',i.indisprimary,
        'keys',i.indkey::smallint[],'where',pg_get_expr(i.indpred,i.indrelid),'definition',pg_get_indexdef(i.indexrelid)))
        FROM pg_index i JOIN pg_class ci ON ci.oid=i.indexrelid WHERE i.indrelid=c.oid)) ORDER BY c.relname)
    FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind='r'),
 'enums',(SELECT json_agg(json_build_object('name',t.typname,'values',v.vals)) FROM pg_type t JOIN pg_namespace n ON n.oid=t.typnamespace
    JOIN (SELECT enumtypid,json_agg(enumlabel ORDER BY enumsortorder) AS vals FROM pg_enum GROUP BY enumtypid) v ON v.enumtypid=t.oid WHERE n.nspname='public'),
 'views',(SELECT json_agg(json_build_object('name',viewname,'definition',definition)) FROM pg_views WHERE schemaname='public'),
 'triggers',(SELECT json_agg(json_build_object('name',tgname,'definition',pg_get_triggerdef(oid))) FROM pg_trigger WHERE NOT tgisinternal));
"""
result=subprocess.run(['psql','-X','-A','-t','-v','ON_ERROR_STOP=1','-c',query],env=env,text=True,capture_output=True,check=True)
catalog=json.loads(result.stdout)
areas=[('Identidade e acesso',['pessoa','usuarios','telefone','recuperacao_senha']),
       ('Endereços e clínica',['estado','cidade','endereco','clinica','funcionamento_clinica','dia_semana']),
       ('Cadastros e catálogo',['pacientes','plano_saude','dentistas','sala','especialidade','procedimentos','categoria_procedimento','dentista_procedimentos','disponibilidade_dentista']),
       ('Agenda e finanças',['agendamentos','pagamento','forma_pagamento','categoria_lancamento','lancamento_financeiro','faturamento_mensal']),
       ('Prontuário',['historico_clinico','prontuarios','odontograma_dentes','odontograma_procedimentos','evolucao_clinica','evolucao_dente'])]
positions={};colors=['#3182ce','#0b7285','#2f855a','#b7791f','#805ad5'];subject=[]
for group,(name,tables) in enumerate(areas):
    x=group*1050;y=0
    for i,name_table in enumerate(tables):positions[name_table]=(x+(i%2)*470,120+(i//2)*1100,colors[group])
    subject.append(dict(id=group,name=name,x=x-30,y=0,width=970,height=120+((len(tables)+1)//2)*1100,color=colors[group],locked=False))
tables=[];byoid={}
for idx,t in enumerate(catalog['tables']):
    keys=t['constraints'] or [];x,y,color=positions[t['name']]
    columns=[]
    for c in t['columns']:
        kind=c['type'];size='';m=re.match(r'(.+)\(([^)]+)\)$',kind)
        if m:kind,size=m.groups()
        aliases={'integer':'INT','smallint':'SMALLINT','bigint':'BIGINT','character varying':'VARCHAR','character':'CHAR',
                 'timestamp with time zone':'TIMESTAMPTZ','timestamp without time zone':'TIMESTAMP','time without time zone':'TIME',
                 'boolean':'BOOLEAN','numeric':'NUMERIC','text':'TEXT','date':'DATE'}
        kind=aliases.get(kind,kind)
        checks=[k['definition'] for k in keys if k['kind']=='c' and c['num'] in (k['keys'] or [])]
        columns.append(dict(id=c['num'],name=c['name'],type=kind,size=size,default=c['default'] or '',check='; '.join(checks),
            primary=any(k['kind']=='p' and c['num'] in k['keys'] for k in keys),
            unique=any(k['kind'] in ['p','u'] and k['keys']==[c['num']] for k in keys),
            notNull=c['notnull'],increment=bool(c['identity']),comment=c['comment'] or '',values=[]))
    name_by_num={c['num']:c['name'] for c in t['columns']}
    indexes=[]
    for i in t['indexes'] or []:
        if i['primary']:continue
        # drawDB cannot encode partial predicates in native indices: keep them in notes and metadata.
        if i['where']:continue
        indexes.append(dict(name=i['name'],unique=i['unique'],fields=[name_by_num[n] for n in i['keys'] if n in name_by_num]))
    notes=[k['definition'] for k in keys if k['kind']=='c']
    notes += [i['definition'] for i in t['indexes'] or [] if i['where']]
    table=dict(id=idx,name=t['name'],x=x,y=y,width=340,fields=columns,
        comment='\n'.join([t['comment'] or '']+notes).strip(),indices=indexes,color=color,
        locked=False,hidden=False,collapsed=False,uniqueConstraints=[dict(name=k['name'],fields=[name_by_num[n] for n in k['keys']]) for k in keys if k['kind']=='u'],inherits=[])
    tables.append(table);byoid[t['oid']]=table
relationships=[]
constraints={'a':'No action','r':'Restrict','c':'Cascade','n':'Set null','d':'Set default'}
for t in catalog['tables']:
    src=byoid[t['oid']]
    for fk in t['constraints'] or []:
        if fk['kind']!='f':continue
        for i,(key,refkey) in enumerate(zip(fk['keys'],fk['refkeys'])):
            relationships.append(dict(id=len(relationships),name=fk['name']+(f' [{i+1}/{len(fk["keys"])}]' if len(fk['keys'])>1 else ''),
                startTableId=src['id'],startFieldId=key,endTableId=byoid[fk['ref']]['id'],endFieldId=refkey,
                cardinality='one_to_one' if any(k['kind'] in ['p','u'] and k['keys']==fk['keys'] for k in t['constraints']) else 'many_to_one',
                updateConstraint=constraints[fk['update']],deleteConstraint=constraints[fk['delete']]))
diagram=dict(title='DENT IA — PostgreSQL operacional',database='postgresql',tables=tables,relationships=relationships,
    subjectAreas=subject,notes=[dict(id=0,x=0,y=-400,title='Regras PostgreSQL',content='Todos os campos e FKs vêm do catálogo real. FKs compostas aparecem com uma linha por coluna. Índices parciais, CHECKs e triggers estão nos comentários e em postgresqlCatalog; schema.sql é a fonte para execução. As views de leitura/indicadores estão em postgresqlCatalog.views.',color='#fff2cc',width=950,height=240,locked=False)],
    types=[],enums=catalog['enums'] or [],views=[],postgresqlCatalog=catalog)
path=Path(args.destino);path.parent.mkdir(parents=True,exist_ok=True);path.write_text(json.dumps(diagram,ensure_ascii=False,indent=2)+'\n')
print(f'drawDB: {len(tables)} tabelas, {sum(len(t["fields"]) for t in tables)} colunas, {len(relationships)} ligações, {len(diagram["enums"])} ENUMs.')
