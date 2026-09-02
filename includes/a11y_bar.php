<!--
  includes/a11y_bar.php
  Painel de acessibilidade flutuante — aparece em todas as páginas.
  O botão fica fixo no canto inferior direito da tela.
  O JavaScript em js/accessibility.js controla todo o comportamento.
-->

<!-- botão flutuante para abrir o painel -->
<button
  id="botao-acessibilidade"
  class="botao-acessibilidade"
  aria-label="Abrir painel de acessibilidade"
  aria-expanded="false"
  aria-controls="painel-acessibilidade"
  title="Configurações de acessibilidade">
  <span aria-hidden="true"><i class="bi bi-universal-access" aria-hidden="true"></i></span>
</button>

<!-- painel de acessibilidade (começa fechado) -->
<div
  id="painel-acessibilidade"
  class="painel-acessibilidade"
  role="dialog"
  aria-label="Painel de acessibilidade"
  aria-modal="false">

  <div class="painel-titulo" aria-hidden="true"><i class="bi bi-universal-access" aria-hidden="true"></i> Acessibilidade</div>

  <!-- TEMA -->
  <div class="painel-secao">
    <div class="painel-secao-label" id="label-tema">Tema</div>
    <div class="grupo-botoes-painel" role="group" aria-labelledby="label-tema">
      <button class="botao-painel" data-botao-tema="light" aria-pressed="true"  title="Ativar tema claro"><i class="bi bi-sun-fill" aria-hidden="true"></i> Claro</button>
      <button class="botao-painel" data-botao-tema="dark"  aria-pressed="false" title="Ativar tema escuro"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i> Escuro</button>
    </div>
  </div>

  <!-- TAMANHO DO TEXTO -->
  <div class="painel-secao">
    <div class="painel-secao-label" id="label-fonte">Tamanho do texto</div>
    <div class="grupo-botoes-painel" role="group" aria-labelledby="label-fonte">
      <button class="botao-painel" data-botao-fonte="xs" aria-pressed="false" title="Texto muito pequeno" style="font-size:0.72rem;">A</button>
      <button class="botao-painel" data-botao-fonte="sm" aria-pressed="false" title="Texto pequeno"       style="font-size:0.85rem;">A</button>
      <button class="botao-painel" data-botao-fonte="md" aria-pressed="true"  title="Texto normal"        style="font-size:1rem;"   >A</button>
      <button class="botao-painel" data-botao-fonte="lg" aria-pressed="false" title="Texto grande"        style="font-size:1.15rem;">A</button>
      <button class="botao-painel" data-botao-fonte="xl" aria-pressed="false" title="Texto muito grande"  style="font-size:1.3rem;" >A</button>
    </div>
  </div>

  <!-- ESPAÇAMENTO ENTRE LINHAS -->
  <div class="painel-secao">
    <div class="painel-secao-label" id="label-espac">Espaçamento entre linhas</div>
    <div class="grupo-botoes-painel" role="group" aria-labelledby="label-espac">
      <button class="botao-painel" data-botao-espacamento="normal"     aria-pressed="true"  title="Espaçamento normal">Normal</button>
      <button class="botao-painel" data-botao-espacamento="amplo"      aria-pressed="false" title="Espaçamento amplo">Amplo</button>
      <button class="botao-painel" data-botao-espacamento="muito-amplo" aria-pressed="false" title="Espaçamento máximo">Máximo</button>
    </div>
  </div>

  <!-- VISÃO E CORES -->
  <div class="painel-secao">
    <div class="painel-secao-label">Visão e cores</div>

    <div class="linha-toggle">
      <label class="label-toggle" for="toggle-contraste"><i class="bi bi-brightness-high-fill" aria-hidden="true"></i> Alto contraste</label>
      <label class="switch">
        <input type="checkbox" id="toggle-contraste" role="switch"
               aria-label="Ativar alto contraste (fundo preto, texto branco)">
        <span class="trilha-switch" aria-hidden="true"></span>
      </label>
    </div>

    <div class="linha-toggle">
      <label class="label-toggle" for="toggle-daltonico"><i class="bi bi-palette-fill" aria-hidden="true"></i> Modo daltônico</label>
      <label class="switch">
        <input type="checkbox" id="toggle-daltonico" role="switch"
               aria-label="Ativar paleta de cores para daltônico">
        <span class="trilha-switch" aria-hidden="true"></span>
      </label>
    </div>
  </div>

  <!-- LEITURA -->
  <div class="painel-secao">
    <div class="painel-secao-label">Leitura</div>

    <div class="linha-toggle">
      <label class="label-toggle" for="toggle-dislexia"><i class="bi bi-book-fill" aria-hidden="true"></i> Fonte para dislexia</label>
      <label class="switch">
        <input type="checkbox" id="toggle-dislexia" role="switch"
               aria-label="Ativar fonte OpenDyslexic para facilitar a leitura">
        <span class="trilha-switch" aria-hidden="true"></span>
      </label>
    </div>
  </div>

  <!-- MOVIMENTO -->
  <div class="painel-secao">
    <div class="painel-secao-label">Movimento</div>

    <div class="linha-toggle">
      <label class="label-toggle" for="toggle-movimento"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> Reduzir animações</label>
      <label class="switch">
        <input type="checkbox" id="toggle-movimento" role="switch"
               aria-label="Desativar animações e transições do sistema">
        <span class="trilha-switch" aria-hidden="true"></span>
      </label>
    </div>
  </div>

  <!-- BOTÃO DE REDEFINIR -->
  <button id="botao-redefinir" class="botao-redefinir" type="button"
          aria-label="Redefinir todas as preferências de acessibilidade para o padrão">
    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Redefinir tudo
  </button>
</div>

<!-- região invisível usada para anunciar mudanças para leitores de tela -->
<div id="regiao-anuncio" class="apenas-leitores" aria-live="polite" aria-atomic="true"></div>
