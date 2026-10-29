<?php
// Login da recepção. A tela e as regras ficam em includes/login_form.php.
$login = [
    'perfil' => 'recepcionista', 'titulo' => 'Área da Recepção', 'icone' => 'bi-folder2-open',
    'cores' => '--cor-role:#a85410;--cor-painel:#3a2108;--cor-marca:#f5b041;',
    'itens' => ['Gerencie a agenda do dia', 'Cadastre e consulte pacientes', 'Crie e confirme agendamentos', 'Controle o fluxo da clínica'],
    'chamada' => 'Área da Recepção', 'subtitulo' => 'Acesse para gerenciar a agenda e os pacientes',
    'rotulo_email' => 'E-mail', 'exemplo_email' => 'recepcao@suaclinica.com',
    'botao' => 'Entrar como Recepcionista', 'demo' => 'recepcao@dentai.com',
];
require __DIR__ . '/includes/login_form.php';
