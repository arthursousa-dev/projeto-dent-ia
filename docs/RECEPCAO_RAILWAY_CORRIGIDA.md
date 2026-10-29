# Recepção — Railway PostgreSQL existente

Esta correção usa o esquema atual fornecido pelo diagnóstico: `agendamentos.sala` é texto, e `pacientes`, `dentistas`, `procedimentos`, `agendamentos` já existem. **Não execute schema.sql nem seed.sql.**

Alterados: `includes/operacoes.php`, `includes/functions.php`, `recepcionista_agenda.php`, `recepcionista_dashboard.php`, `recepcionista_novo_agendamento.php`.

Fluxo: selecionar paciente ativo, procedimento e dentista, escolher data e horário, salvar no PostgreSQL com `RETURNING id`, redirecionar para agenda filtrada pela data. A restrição UNIQUE do banco impede horários duplicados por dentista, inclusive os cancelados. Para reaproveitar uma reserva cancelada no mesmo horário, remova ou altere essa restrição apenas após revisar sua regra de negócio.

Teste: conferir na tela a mensagem com ID; validar `SELECT id,id_paciente,id_dentista,data,hora,sala FROM agendamentos ORDER BY id DESC LIMIT 5;` no Railway. Sem credenciais do Railway, não foi possível executar teste real neste ambiente.

**Escopo**: recepção (listagem/agendamento/pagamento/status). As demais áreas ainda usam o modelo normalizado anterior e podem exigir migração adicional.
