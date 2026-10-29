<?php
// Login do gestor (dono). A tela e as regras ficam em includes/login_form.php.
$login = [
    'perfil' => 'dono', 'titulo' => 'Área do Gestor', 'icone' => 'bi-award-fill',
    'cores' => '--cor-role:#0e2a3f;--cor-painel:#0e2a3f;--cor-marca:#f5b041;',
    'itens' => ['Painel financeiro completo', 'Relatórios e indicadores da clínica', 'Gestão de dentistas e equipe', 'Configurações gerais do sistema'],
    'chamada' => 'Painel Administrativo', 'subtitulo' => 'Acesso restrito ao gestor e proprietário',
    'rotulo_email' => 'E-mail do gestor', 'exemplo_email' => 'gestor@suaclinica.com',
    'botao' => 'Entrar como Gestor', 'demo' => 'dono@dentai.com',
];
require __DIR__ . '/includes/login_form.php';
