<?php
/**
 * includes/odontograma_svg.php
 *
 * Componente reutilizável: a base SVG do odontograma (gradientes,
 * arcos gengivais, labels de quadrante). Os dentes em si continuam
 * renderizados via JavaScript, lendo os dados do paciente atual —
 * este componente só fornece o "papel" onde eles são desenhados.
 *
 * Extraído de dentro de dentista_prontuario.php para poder ser
 * reaproveitado em qualquer tela que precise exibir um odontograma
 * (ex. uma futura visão somente leitura para o paciente).
 */
?>
        <!-- SVG Odontograma -->
        <div id="odontograma-wrapper" style="width:100%;overflow-x:auto;position:relative;">
          <svg id="odontograma" viewBox="0 0 620 340" style="width:100%;min-width:500px;max-width:720px;display:block;margin:0 auto;" role="img" aria-label="Odontograma interativo do paciente">
            <defs>
              <!-- Gradientes dente saudável -->
              <radialGradient id="g-saudavel" cx="35%" cy="25%" r="65%">
                <stop offset="0%" stop-color="#fffdf7"/>
                <stop offset="50%" stop-color="#f5ead8"/>
                <stop offset="100%" stop-color="#ddc9a8"/>
              </radialGradient>
              <!-- Gradiente tratado (verde) -->
              <radialGradient id="g-tratado" cx="35%" cy="25%" r="65%">
                <stop offset="0%" stop-color="#9ae6b4"/>
                <stop offset="50%" stop-color="#48bb78"/>
                <stop offset="100%" stop-color="#276749"/>
              </radialGradient>
              <!-- Gradiente precisa tratamento (vermelho) -->
              <radialGradient id="g-precisaTratamento" cx="35%" cy="25%" r="65%">
                <stop offset="0%" stop-color="#fc8181"/>
                <stop offset="50%" stop-color="#e53e3e"/>
                <stop offset="100%" stop-color="#742a2a"/>
              </radialGradient>
              <!-- Gradiente raio-x saudavel -->
              <radialGradient id="g-xray-saudavel" cx="35%" cy="25%" r="65%">
                <stop offset="0%" stop-color="#ffffff"/>
                <stop offset="60%" stop-color="#c0c0c0"/>
                <stop offset="100%" stop-color="#808080"/>
              </radialGradient>
              <!-- Gradiente raio-x tratado -->
              <radialGradient id="g-xray-tratado" cx="35%" cy="25%" r="65%">
                <stop offset="0%" stop-color="#ffffff"/>
                <stop offset="40%" stop-color="#f0f0f0"/>
                <stop offset="100%" stop-color="#d0d0d0"/>
              </radialGradient>
              <!-- Gradiente raio-x precisa -->
              <radialGradient id="g-xray-precisaTratamento" cx="35%" cy="25%" r="65%">
                <stop offset="0%" stop-color="#888"/>
                <stop offset="60%" stop-color="#444"/>
                <stop offset="100%" stop-color="#222"/>
              </radialGradient>
              <!-- Filtro sombra -->
              <filter id="sombra-dente" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="rgba(11,40,69,0.25)"/>
              </filter>
              <!-- Filtro hover glow -->
              <filter id="glow-hover" x="-30%" y="-30%" width="160%" height="160%">
                <feDropShadow dx="0" dy="0" stdDeviation="4" flood-color="#f5a623" flood-opacity="0.8"/>
              </filter>
            </defs>

            <!-- Fundo arco gengival superior -->
            <path d="M 30 168 Q 310 60 590 168" fill="none" stroke="rgba(200,180,160,0.3)" stroke-width="18" stroke-linecap="round"/>
            <!-- Fundo arco gengival inferior -->
            <path d="M 30 172 Q 310 280 590 172" fill="none" stroke="rgba(200,180,160,0.3)" stroke-width="18" stroke-linecap="round"/>

            <!-- Linha central vertical -->
            <line x1="310" y1="15" x2="310" y2="325" stroke="var(--borda)" stroke-width="1" stroke-dasharray="4,4"/>
            <!-- Linha horizontal -->
            <line x1="15" y1="170" x2="605" y2="170" stroke="var(--borda)" stroke-width="1" stroke-dasharray="4,4"/>

            <!-- Labels quadrantes -->
            <text x="160" y="14" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="10" fill="var(--texto-fraco)" font-weight="600">2° QUADRANTE</text>
            <text x="460" y="14" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="10" fill="var(--texto-fraco)" font-weight="600">1° QUADRANTE</text>
            <text x="160" y="335" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="10" fill="var(--texto-fraco)" font-weight="600">3° QUADRANTE</text>
            <text x="460" y="335" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="10" fill="var(--texto-fraco)" font-weight="600">4° QUADRANTE</text>

            <!-- Label superior/inferior -->
            <text x="14" y="158" font-family="DM Sans, sans-serif" font-size="9" fill="var(--texto-fraco)" transform="rotate(-90,14,158)">SUPERIOR</text>
            <text x="14" y="258" font-family="DM Sans, sans-serif" font-size="9" fill="var(--texto-fraco)" transform="rotate(-90,14,258)">INFERIOR</text>

            <!-- Todos os dentes são renderizados por JavaScript -->
            <g id="dentes-group"></g>
          </svg>
        </div>
