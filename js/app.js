// js/app.js — menu em gaveta no celular e proteção contra envio duplo de formulários.
(function () {
  'use strict';

  // ---- gaveta do menu (só visível abaixo de 768px; ver css/style.css) ----
  var botao = document.querySelector('.botao-menu');
  var menu = document.getElementById('menu-lateral');
  var fundo = document.querySelector('.fundo-menu');

  function alternarMenu(abrir) {
    if (!botao || !menu) return;
    menu.classList.toggle('aberto', abrir);
    botao.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    botao.setAttribute('aria-label', abrir ? 'Fechar menu' : 'Abrir menu');
    if (fundo) fundo.hidden = !abrir;
    if (abrir) { var link = menu.querySelector('a'); if (link) link.focus(); }
    else botao.focus();
  }

  if (botao && menu) {
    botao.addEventListener('click', function () { alternarMenu(!menu.classList.contains('aberto')); });
    if (fundo) fundo.addEventListener('click', function () { alternarMenu(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('aberto')) alternarMenu(false);
    });
  }

  // ---- envio de formulário: indica "salvando" e bloqueia o segundo clique ----
  // O botão não é desabilitado de propósito: desabilitar tira o name/value dele do POST,
  // e várias páginas dependem disso (ex.: isset($_POST['salvar'])).
  // ---- mostrar/ocultar senha: <button data-alternar-senha="id-do-campo"> ----
  document.addEventListener('click', function (e) {
    var botao = e.target.closest('[data-alternar-senha]');
    if (!botao) return;
    var campo = document.getElementById(botao.getAttribute('data-alternar-senha'));
    if (!campo) return;
    var mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    botao.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
    botao.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
    var icone = botao.querySelector('i');
    if (icone) icone.className = 'bi ' + (mostrar ? 'bi-eye-slash' : 'bi-eye');
  });

  // ---- confirmação antes de ações sensíveis ----
  // <form data-confirmar="Cancelar a consulta de Maria?">: o texto vem escapado no HTML e é lido como texto,
  // nunca montado dentro de código JavaScript (nome com aspas não vira script).
  document.addEventListener('submit', function (e) {
    var pergunta = e.target.getAttribute('data-confirmar');
    if (pergunta && !window.confirm(pergunta)) e.preventDefault();
  }, true);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented) return; // confirm() cancelado ou validação de outra página
    // GET (busca), downloads (data-download) e formulários com target não trocam de página: não podem ficar travados.
    if (form.method.toLowerCase() !== 'post' || form.target || form.hasAttribute('data-download')) return;
    if (form.dataset.enviando === '1') { e.preventDefault(); return; }
    form.dataset.enviando = '1';
    var acionador = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (acionador) acionador.setAttribute('aria-busy', 'true');
  });

  // Ao voltar pelo histórico, a página pode vir do cache com o estado "salvando".
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-enviando]').forEach(function (f) { delete f.dataset.enviando; });
    document.querySelectorAll('[aria-busy="true"]').forEach(function (b) { b.removeAttribute('aria-busy'); });
  });
})();
