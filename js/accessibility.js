/**
 * Painel de acessibilidade — DENT IA
 * Controla tema, tamanho de fonte, contraste, modo daltônico, fonte para
 * dislexia, espaçamento entre linhas e redução de animações.
 * Preferências salvas no localStorage do navegador.
 *
 * Autor: Arthur Sousa da Costa
 */

;(function () {
  'use strict';

  var CHAVE_ARMAZENAMENTO = 'dentai_acessibilidade_v2';

  var CONFIGURACOES_PADRAO = {
    tema:         'light',
    tamanhoFonte: 'md',
    contraste:    'normal',
    daltonico:    false,
    dislexia:     false,
    espacamento:  'normal',
    movimento:    'normal',
  };

  function carregarPreferencias() {
    try {
      var salvo = localStorage.getItem(CHAVE_ARMAZENAMENTO);
      return salvo
        ? Object.assign({}, CONFIGURACOES_PADRAO, JSON.parse(salvo))
        : Object.assign({}, CONFIGURACOES_PADRAO);
    } catch (erro) {
      return Object.assign({}, CONFIGURACOES_PADRAO);
    }
  }

  function salvarPreferencias(preferencias) {
    try {
      localStorage.setItem(CHAVE_ARMAZENAMENTO, JSON.stringify(preferencias));
    } catch (erro) {
      // localStorage indisponível (modo privado, cookies bloqueados etc.)
    }
  }

  function aplicarPreferencias(preferencias) {
    var raiz = document.documentElement;
    raiz.setAttribute('data-tema',          preferencias.tema);
    raiz.setAttribute('data-tamanho-fonte', preferencias.tamanhoFonte);
    raiz.setAttribute('data-contraste',     preferencias.contraste);
    raiz.setAttribute('data-daltonico',     preferencias.daltonico ? 'true' : 'false');
    raiz.setAttribute('data-dislexia',      preferencias.dislexia  ? 'true' : 'false');
    raiz.setAttribute('data-espacamento',   preferencias.espacamento);
    raiz.setAttribute('data-movimento',     preferencias.movimento);
  }

  // aplica antes do DOMContentLoaded para evitar flash de tema errado
  aplicarPreferencias(carregarPreferencias());

  document.addEventListener('DOMContentLoaded', function () {
    var preferencias = carregarPreferencias();
    aplicarPreferencias(preferencias);

    var botaoFlutuante = document.getElementById('botao-acessibilidade');
    var painelPrincipal = document.getElementById('painel-acessibilidade');
    if (!botaoFlutuante || !painelPrincipal) return;

    botaoFlutuante.addEventListener('click', function () {
      var estaAberto = painelPrincipal.classList.toggle('aberto');

      botaoFlutuante.setAttribute('aria-expanded', estaAberto ? 'true' : 'false');
      botaoFlutuante.setAttribute(
        'aria-label',
        estaAberto ? 'Fechar painel de acessibilidade' : 'Abrir painel de acessibilidade'
      );

      if (estaAberto) {
        var primeiroElemento = painelPrincipal.querySelector('button, input, [tabindex="0"]');
        if (primeiroElemento) {
          setTimeout(function () { primeiroElemento.focus(); }, 60);
        }
      }
    });

    document.addEventListener('keydown', function (evento) {
      if (evento.key === 'Escape' && painelPrincipal.classList.contains('aberto')) {
        painelPrincipal.classList.remove('aberto');
        botaoFlutuante.setAttribute('aria-expanded', 'false');
        botaoFlutuante.focus();
      }
    });

    document.addEventListener('mousedown', function (evento) {
      var clicouFora = !painelPrincipal.contains(evento.target) && evento.target !== botaoFlutuante;
      if (painelPrincipal.classList.contains('aberto') && clicouFora) {
        painelPrincipal.classList.remove('aberto');
        botaoFlutuante.setAttribute('aria-expanded', 'false');
      }
    });

    vincularGrupoBotoes('data-botao-tema', function (valor) {
      preferencias.tema = valor;
      aplicarPreferencias(preferencias);
      salvarPreferencias(preferencias);
      sincronizarInterface(preferencias);
      anunciar(valor === 'dark' ? 'Modo escuro ativado' : 'Modo claro ativado');
    });

    vincularGrupoBotoes('data-botao-fonte', function (valor) {
      preferencias.tamanhoFonte = valor;
      aplicarPreferencias(preferencias);
      salvarPreferencias(preferencias);
      sincronizarInterface(preferencias);
      var nomes = { xs:'muito pequena', sm:'pequena', md:'normal', lg:'grande', xl:'muito grande' };
      anunciar('Fonte ' + (nomes[valor] || valor));
    });

    vincularGrupoBotoes('data-botao-espacamento', function (valor) {
      preferencias.espacamento = valor;
      aplicarPreferencias(preferencias);
      salvarPreferencias(preferencias);
      sincronizarInterface(preferencias);
      var nomes = { normal:'normal', amplo:'amplo', 'muito-amplo':'muito amplo' };
      anunciar('Espaçamento ' + (nomes[valor] || valor));
    });

    vincularToggle('toggle-contraste', function (ativado) {
      preferencias.contraste = ativado ? 'alto' : 'normal';
      aplicarPreferencias(preferencias);
      salvarPreferencias(preferencias);
      anunciar(ativado ? 'Alto contraste ativado' : 'Alto contraste desativado');
    });

    vincularToggle('toggle-daltonico', function (ativado) {
      preferencias.daltonico = ativado;
      aplicarPreferencias(preferencias);
      salvarPreferencias(preferencias);
      anunciar(ativado ? 'Modo daltônico ativado' : 'Modo daltônico desativado');
    });

    vincularToggle('toggle-dislexia', function (ativado) {
      preferencias.dislexia = ativado;
      aplicarPreferencias(preferencias);
      salvarPreferencias(preferencias);
      anunciar(ativado ? 'Fonte para dislexia ativada' : 'Fonte padrão restaurada');
    });

    vincularToggle('toggle-movimento', function (ativado) {
      preferencias.movimento = ativado ? 'reduzido' : 'normal';
      aplicarPreferencias(preferencias);
      salvarPreferencias(preferencias);
      anunciar(ativado ? 'Animações reduzidas' : 'Animações normais');
    });

    var botaoRedefinir = document.getElementById('botao-redefinir');
    if (botaoRedefinir) {
      botaoRedefinir.addEventListener('click', function () {
        preferencias = Object.assign({}, CONFIGURACOES_PADRAO);
        aplicarPreferencias(preferencias);
        salvarPreferencias(preferencias);
        sincronizarInterface(preferencias);
        anunciar('Todas as configurações de acessibilidade foram redefinidas');
      });
    }

    sincronizarInterface(preferencias);
  });

  function vincularGrupoBotoes(atributo, aoClicar) {
    document.querySelectorAll('[' + atributo + ']').forEach(function (botao) {
      botao.addEventListener('click', function () {
        aoClicar(botao.getAttribute(atributo));
      });
    });
  }

  function vincularToggle(identificador, aoMudar) {
    var elemento = document.getElementById(identificador);
    if (!elemento) return;
    elemento.addEventListener('change', function () {
      aoMudar(elemento.checked);
    });
  }

  function sincronizarInterface(preferencias) {
    sincronizarGrupo('data-botao-tema',        preferencias.tema);
    sincronizarGrupo('data-botao-fonte',       preferencias.tamanhoFonte);
    sincronizarGrupo('data-botao-espacamento', preferencias.espacamento);

    definirToggle('toggle-contraste',  preferencias.contraste === 'alto');
    definirToggle('toggle-daltonico',  !!preferencias.daltonico);
    definirToggle('toggle-dislexia',   !!preferencias.dislexia);
    definirToggle('toggle-movimento',  preferencias.movimento === 'reduzido');
  }

  function sincronizarGrupo(atributo, valorAtivo) {
    document.querySelectorAll('[' + atributo + ']').forEach(function (botao) {
      var esteEstaAtivo = botao.getAttribute(atributo) === valorAtivo;
      botao.classList.toggle('ativo', esteEstaAtivo);
      botao.setAttribute('aria-pressed', esteEstaAtivo ? 'true' : 'false');
    });
  }

  function definirToggle(identificador, marcado) {
    var elemento = document.getElementById(identificador);
    if (elemento) elemento.checked = marcado;
  }

  // região aria-live usada por leitores de tela (NVDA, JAWS) para anunciar mudanças
  function anunciar(mensagem) {
    var regiao = document.getElementById('regiao-anuncio');
    if (!regiao) {
      regiao = document.createElement('div');
      regiao.id = 'regiao-anuncio';
      regiao.setAttribute('aria-live', 'polite');
      regiao.setAttribute('aria-atomic', 'true');
      regiao.className = 'apenas-leitores';
      document.body.appendChild(regiao);
    }
    regiao.textContent = '';
    requestAnimationFrame(function () {
      regiao.textContent = mensagem;
    });
  }

})();
