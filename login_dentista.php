<?php
// Login do dentista. A tela e as regras ficam em includes/login_form.php.
$login = [
    'perfil' => 'dentista', 'titulo' => 'Área do Dentista', 'icone' => 'bi-person-badge-fill',
    'cores' => '--cor-role:#08806f;--cor-painel:#0b3b36;--cor-marca:#3dd6b8;',
    'itens' => ['Visualize sua agenda do dia', 'Acesse prontuários com odontograma', 'Registre tratamentos e evoluções', 'Acompanhe o histórico clínico completo'],
    'chamada' => 'Bem-vindo, Doutor(a)', 'subtitulo' => 'Acesse sua área clínica para continuar',
    'rotulo_email' => 'E-mail profissional', 'exemplo_email' => 'voce@suaclinica.com',
    'botao' => 'Entrar como Dentista', 'demo' => 'dentista@dentai.com',
];
require __DIR__ . '/includes/login_form.php';
