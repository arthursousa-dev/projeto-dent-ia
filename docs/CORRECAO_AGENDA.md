# Correção da agenda da recepção (PostgreSQL)

## O que mudou

- A **Agenda do Dia** executa SELECT na `vw_agendamentos` filtrando pela data enviada (`YYYY-MM-DD`) e, se houver, pelo ID do dentista. A lista fica ordenada por hora e ID.
- O **Painel da Recepção** busca apenas agendamentos do dia atual no PostgreSQL; estatísticas passam a refletir essa mesma lista.
- Após criar um agendamento, o sistema redireciona para a agenda da data agendada, com o ID criado.
- Ao fazer check-in/cancelar/alterar status, a página redireciona para a data selecionada após persistir a mudança.
- O botão Novo Agendamento da agenda transporta a data escolhida para o formulário, quando não estiver no passado.

## Instalação e verificação

1. Faça backup da base PostgreSQL **antes** de alterar dados: `pg_dump -Fc -f backup_dentia.dump nome_do_banco`.
2. Substitua os arquivos do projeto pelos deste ZIP, mantendo suas credenciais locais no `.env`. **Não execute `seed.sql` novamente em banco com dados reais**; ele insere dados de demonstração.
3. Confira a extensão `pdo_pgsql` com `php -m` e a conexão pelo script `php scripts/diagnostico.php`.
4. Entre como recepcionista, selecione um paciente, procedimento, dentista, data futura e horário livre. Salve, confirme o ID exibido e visite a agenda daquela data. Confira outra data: a consulta não deve aparecer nela.
5. Para conferir direto no banco: `SELECT id, id_paciente, id_dentista, data, hora, status FROM agendamentos ORDER BY id DESC LIMIT 20;`

## Sobre os registros antigos do print

As consultas antigas podem ter sido inseridas pelo `database/seed.sql`: são registros **persistidos no PostgreSQL**, não apenas exemplos HTML. Não foram apagados automaticamente nesta atualização para evitar perda de dados reais. Remova ou cancele registros de demonstração apenas depois de verificar seus IDs e vínculos (pagamentos e prontuários).

## Limitações desta validação

Verificações de sintaxe PHP estão disponíveis, mas este ambiente não tem o driver `pdo_pgsql` nem uma instância PostgreSQL configurada. É necessário executar o fluxo em sua instalação para confirmar conexão e gravação.
