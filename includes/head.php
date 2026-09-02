<?php
// includes/head.php
// cabeçalho HTML padrão — incluído no início de todas as páginas
// a variável $tituloPagina deve ser definida antes de incluir esse arquivo
?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="DENT IA — Clínica Odontológica Inteligente. Agendamento online e gestão completa.">
  <title><?= isset($tituloPagina) ? limpar($tituloPagina) . ' — DENT IA' : 'DENT IA' ?></title>

  <!--
    IMPORTANTE: o JavaScript de acessibilidade precisa vir ANTES do CSS.
    Isso evita o flash (piscar) quando o usuário tem o modo escuro ativado,
    porque o JS aplica o tema antes da página terminar de carregar.
  -->
  <script src="js/accessibility.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<!-- link pra pular pro conteúdo — aparece apenas quando navegando por teclado -->
<a href="#conteudo-principal" class="pular-conteudo">Pular para o conteúdo principal</a>
