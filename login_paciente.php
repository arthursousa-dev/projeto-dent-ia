<?php
// Login do paciente, com o link "Cadastre-se". A tela e as regras ficam em includes/login_form.php.
$login = [
    'perfil' => 'cliente', 'titulo' => 'Área do Paciente', 'icone' => 'bi-person-fill',
    'cores' => '--cor-role:#2b6cb0;--cor-painel:#12324f;--cor-marca:#8ec5f0;',
    'itens' => ['Agende suas consultas online', 'Acompanhe seu histórico de consultas', 'Gerencie seus dados pessoais', 'Cancele consultas com antecedência'],
    'chamada' => 'Bem-vindo de volta', 'subtitulo' => 'Acesse sua área de paciente para continuar',
    'rotulo_email' => 'E-mail', 'exemplo_email' => 'seu@email.com',
    'botao' => 'Entrar como Paciente', 'demo' => 'cliente@gmail.com', 'cadastro' => true,
];
require __DIR__ . '/includes/login_form.php';
