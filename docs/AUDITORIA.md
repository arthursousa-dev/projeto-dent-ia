# Auditoria do DENT IA — outubro de 2026

## O que mudou e por quê

| Prioridade | Problema | Correção |
|---|---|---|
| Crítico | Todo dentista cadastrado recebia a senha `123456`, e ela aparecia na tela. | A conta nasce com uma senha aleatória que ninguém conhece (`senhaSemAcesso()`). O primeiro acesso é feito pelo link de Usuários (30 min, uso único). |
| Alto | Pacientes cadastrados pela recepção não tinham login, então só os 3 do seed acessavam o portal. | Paciente com e-mail ganha conta `cliente`, sem senha utilizável. O gestor emite o link de acesso em Usuários. |
| Alto | "Limite de consultas por dia" e "Antecedência mínima para o paciente" eram salvos em Configurações, mas nada os aplicava. | `validarRegrasClinica()` é chamada em `criarAgendamento()`. A contagem por data usa um lock no banco para que duas reservas simultâneas não ultrapassem o limite juntas. O portal e a API de horários respeitam a data mínima. |
| Alto | Não havia como remarcar. | `reagendarAgendamento()`: cancela a original e cria a nova com as mesmas regras, numa única transação (se a nova falhar, a original continua). Botão "Remarcar" na agenda da recepção. Consultas pagas não são remarcadas. |
| Médio | A sessão nunca expirava, com prontuário aberto em computador compartilhado. | Encerramento após 60 minutos sem uso, com nova identificação de sessão e aviso no login. O logout também apaga o cookie. |
| Médio | As 18 páginas mostravam erros com a classe `alerta-erro`, que não existia no CSS: o erro aparecia como texto sem cor. | Estilo adicionado. |
| Baixo | O script de horários era servido do cache depois de atualizado. | Versão automática no link (`?v=`). |
| Médio | Não havia como registrar falta; consultas passadas ficavam "Agendado/Confirmado" para sempre. | Migração `006_status_faltou.sql` (aplicada em 27/10/2026, com backup `backups\dentia_20261027_001635.dump`). Botão "Faltou" na agenda da recepção, só depois do horário e para consultas não pagas. A falta não entra em "a receber", não aceita pagamento nem check-in e não recebe evolução clínica. |

| Médio | Uma falta lançada por engano só podia ser corrigida no banco. | Botão "Desfazer falta" (só recepção, com confirmação). A consulta volta ao status anterior guardado no histórico; nada é apagado. |
| Médio | Não havia histórico de quem mudou o status de uma consulta. | Migração `007_historico_status.sql` (aplicada em 27/10/2026, backup `backups\dentia_20261027_004257.dump`). Toda mudança de status (agenda, falta, correção, conclusão pela evolução) grava de/para, usuário e horário. A agenda mostra o histórico em cada linha. |
| Médio | Ao registrar evolução, o dentista podia dar como "Concluída" uma consulta antiga, sem check-in, de outro dia. | A evolução só se vincula a consultas do dia ou que passaram pelo check-in. |
| Baixo | O PostgreSQL usava o fuso do servidor; no Railway (UTC) a data do banco poderia diferir da do PHP. | A conexão fixa `America/Cuiaba`, o mesmo fuso do PHP. |

## Regra de horário da falta
`faltaPermitida()` em `includes/operacoes.php` é usada pela tela e pelo servidor: a falta pode ser registrada **a partir do minuto marcado**, no relógio da clínica (America/Cuiaba). Consulta das 09:00: às 08:59:59 o botão não aparece e o servidor recusa; às 09:00:00 e depois, aparece e é aceito.

| Cenário | Como foi testado | Resultado |
|---|---|---|
| 1 s antes, exatamente no horário, depois, virada do dia | `testar_melhorias.php` com relógio fixo | OK |
| Tela: consulta 20 min antes, no minuto atual e 2 min depois | `dentia_teste` em 127.0.0.1:8001 | Botão nas duas primeiras; na terceira só apareceu quando o minuto chegou (00:48:51 oculto, 00:49:09 visível) |
| POST forjado antes do horário | requisição direta ao servidor | Recusado: "A falta só pode ser registrada a partir do horário da consulta." |

## Consultas sem desfecho
O seed foi gerado com data base 09/10/2026 e criou consultas "Agendado/Confirmado" para os 15 dias seguintes. Como os dias passaram sem regularização, 37 ficaram "Agendado/Confirmado" e mais 4 ficaram "Aguardando/Em atendimento" (41 no total, a mais antiga de 10/04/2026). Nenhuma foi alterada. A agenda mostra um aviso com o total e a lista **Consultas sem desfecho** (`recepcionista_agenda.php?pendentes=1`), com o selo "Sem desfecho", para a recepção registrar uma a uma o que aconteceu.

## O que foi verificado e está correto
- SQL: todas as consultas usam parâmetros; nomes de tabela dinâmicos passam por lista fixa.
- XSS: as saídas usam `limpar()`; os valores sem escape são números ou listas fixas, e o JSON do odontograma usa `JSON_HEX_*`.
- CSRF: há validação central para todo POST (`includes/functions.php`).
- Acesso: cada operação confere o perfil no servidor (`exigirPerfil`); o paciente só cancela as próprias consultas e o dentista só vê os pacientes vinculados (`pacientePermitido`).
- Sobreposição de horários: há trigger com lock no banco (`validar_sobreposicao_agenda`, migração 005 já aplicada).
- Senhas: bcrypt, bloqueio após 5 tentativas e recuperação com token de uso único (hash SHA-256).

## Não existe hoje (não foi simulado)
- **IA:** não há nenhuma integração de inteligência artificial no código; "IA" é só o nome.
- **Lembretes e confirmações automáticas** (WhatsApp, SMS, e-mail): não há serviço de envio configurado.
- **Pagamento online:** os pagamentos são registrados manualmente (forma e data). Não há provedor nem dados de cartão.
- **Radiografias e anexos:** não há upload no projeto.
- **Consultas passadas sem desfecho:** continuam como estavam (ver acima); a recepção regulariza pela lista de pendentes.
- **Agenda semanal e mensal, feriados e bloqueios de agenda.**
- **Histórico de outras alterações** (pagamentos, cadastros, prontuário): só os status de consulta têm histórico.
- **Envio do link de senha por e-mail:** o link continua sendo gerado pelo gestor em Usuários. (O cadastro do paciente pelo próprio portal já existe: "Cadastre-se" no login do paciente.)

## Depois da auditoria
A banca de revisão de 27/10/2026 (hacker, usuário confuso, programador sênior e testador) e as correções feitas estão em [BANCA_REVISAO.md](BANCA_REVISAO.md).
