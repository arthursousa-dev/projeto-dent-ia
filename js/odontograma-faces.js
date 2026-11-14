// Faces do dente no painel do odontograma, com a nomenclatura odontológica:
// mesial, distal, vestibular, lingual (inferiores) ou palatina (superiores), oclusal (posteriores) ou incisal (anteriores).
// O dentista clica nas faces em que trabalhou, marca a situação delas e anota na evolução ("26 (mesial, oclusal)").
// Conversa com o script da página pela ponte window.DentiaOdonto e pelos eventos "odonto:painel" e "odonto:mudou".
(() => {
  const O = window.DentiaOdonto;
  const svg = document.getElementById('faces-svg');
  const botoes = document.getElementById('faces-botoes');
  const resumo = document.getElementById('faces-resumo');
  if (!O || !svg || !botoes || !resumo) return;
  const campoDentes = document.getElementById('evolucao-dentes');
  const NS = 'http://www.w3.org/2000/svg';
  const ORDEM = ['mesial', 'distal', 'vestibular', 'lingual', 'palatina', 'oclusal', 'incisal'];
  const NOME = { mesial: 'Mesial', distal: 'Distal', vestibular: 'Vestibular', lingual: 'Lingual', palatina: 'Palatina', oclusal: 'Oclusal', incisal: 'Incisal' };
  const SIGLA = { mesial: 'M', distal: 'D', vestibular: 'V', lingual: 'L', palatina: 'P', oclusal: 'O', incisal: 'I' };
  const SITUACAO = { saudavel: 'saudável', tratado: 'tratada', precisaTratamento: 'precisa de tratamento' };
  const COR = { saudavel: '#f4ede1', tratado: '#48bb78', precisaTratamento: '#e53e3e' };
  // Regiões do diagrama em cruz (quadrado de 40 a 160, centro de 80 a 120).
  const REGIAO = {
    cima: '40,40 160,40 120,80 80,80', baixo: '40,160 160,160 120,120 80,120',
    esquerda: '40,40 80,80 80,120 40,160', direita: '160,40 120,80 120,120 160,160', centro: '80,80 120,80 120,120 80,120',
  };
  const ROTULO = { cima: [100, 26, 'middle'], baixo: [100, 184, 'middle'], esquerda: [30, 104, 'end'], direita: [170, 104, 'start'] };
  let selecao = new Set();
  let denteDaSelecao = null;

  const superior = (n) => [1, 2, 5, 6].includes(Math.floor(n / 10));
  /** As cinco faces do dente (mesma regra de facesDoDente no servidor). */
  const facesDe = (n) => ['mesial', 'distal', 'vestibular', superior(n) ? 'palatina' : 'lingual', n % 10 <= 3 ? 'incisal' : 'oclusal'];
  /** Onde cada face fica no diagrama, vista de frente para o paciente: vestibular para fora do arco, mesial para a linha média. */
  function posicoes(n) {
    const q = Math.floor(n / 10);
    const ladoDireitoDoPaciente = [1, 4, 5, 8].includes(q); // aparece à esquerda de quem olha
    return {
      cima: superior(n) ? 'vestibular' : 'lingual',
      baixo: superior(n) ? 'palatina' : 'vestibular',
      esquerda: ladoDireitoDoPaciente ? 'distal' : 'mesial',
      direita: ladoDireitoDoPaciente ? 'mesial' : 'distal',
      centro: n % 10 <= 3 ? 'incisal' : 'oclusal',
    };
  }
  function dadosDe(n) {
    const d = O.dados[String(n)] || (O.dados[String(n)] = { status: O.status[n] || 'saudavel' });
    // Dente sem faces chega do servidor como lista vazia ([]); precisa ser objeto para o JSON levar as faces.
    if (!d.faces || Array.isArray(d.faces)) d.faces = {};
    return d;
  }
  const situacaoDa = (n, face) => dadosDe(n).faces[face] || 'saudavel';
  const emLista = (itens) => itens.length <= 1 ? (itens[0] || '') : itens.slice(0, -1).join(', ') + ' e ' + itens[itens.length - 1];
  const emOrdem = (faces) => ORDEM.filter(f => faces.includes(f));
  const no = (nome, attrs, pai) => {
    const e = document.createElementNS(NS, nome);
    for (const k in attrs) e.setAttribute(k, attrs[k]);
    if (pai) pai.appendChild(e);
    return e;
  };

  function alternar(face) {
    if (selecao.has(face)) selecao.delete(face); else selecao.add(face);
    desenhar(O.ativo);
  }

  function desenhar(n) {
    svg.replaceChildren();
    botoes.replaceChildren();
    if (!n) { resumo.textContent = ''; return; }
    const pos = posicoes(n);
    for (const lugar of Object.keys(REGIAO)) {
      const face = pos[lugar];
      const situacao = situacaoDa(n, face);
      const escolhida = selecao.has(face);
      const regiao = no('polygon', {
        points: REGIAO[lugar], fill: COR[situacao], stroke: escolhida ? '#2457f5' : '#5b6b76', 'stroke-width': escolhida ? 5 : 1.5,
        'stroke-linejoin': 'round', tabindex: '0', role: 'button', 'aria-pressed': escolhida ? 'true' : 'false',
        'aria-label': 'Face ' + face + ', ' + SITUACAO[situacao], 'data-face': face, class: 'face-regiao',
      }, svg);
      regiao.style.cursor = 'pointer';
      if (lugar === 'centro') {
        no('text', { x: 100, y: 105, 'text-anchor': 'middle', class: 'face-sigla' }, svg).textContent = SIGLA[face];
      } else {
        const [x, y, ancora] = ROTULO[lugar];
        no('text', { x, y, 'text-anchor': ancora, class: 'face-rotulo' }, svg).textContent = NOME[face];
      }
    }
    no('text', { x: 100, y: 199, 'text-anchor': 'middle', class: 'face-rotulo face-centro-nome' }, svg).textContent = 'centro: ' + NOME[pos.centro].toLowerCase();

    for (const face of facesDe(n)) {
      const situacao = situacaoDa(n, face);
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'face-botao';
      b.dataset.face = face;
      b.setAttribute('aria-pressed', selecao.has(face) ? 'true' : 'false');
      const ponto = document.createElement('span');
      ponto.className = 'face-ponto';
      ponto.style.background = COR[situacao];
      b.append(ponto, NOME[face] + ' (' + SIGLA[face] + ')');
      b.title = 'Face ' + face + ': ' + SITUACAO[situacao];
      botoes.append(b);
    }

    const partes = [];
    const tratadas = emOrdem(facesDe(n).filter(f => situacaoDa(n, f) === 'tratado'));
    const pendentes = emOrdem(facesDe(n).filter(f => situacaoDa(n, f) === 'precisaTratamento'));
    if (tratadas.length) partes.push('Tratada' + (tratadas.length > 1 ? 's' : '') + ': ' + emLista(tratadas) + '.');
    if (pendentes.length) partes.push('Precisa' + (pendentes.length > 1 ? 'm' : '') + ' de tratamento: ' + emLista(pendentes) + '.');
    const escolhidas = emOrdem([...selecao]);
    resumo.textContent = (escolhidas.length
      ? 'Selecionada' + (escolhidas.length > 1 ? 's' : '') + ' no dente ' + n + ': ' + (escolhidas.length > 1 ? 'faces ' : 'face ') + emLista(escolhidas) + '. Use os botões abaixo para marcar só ' + (escolhidas.length > 1 ? 'elas' : 'ela') + '.'
      : 'Clique nas faces em que você trabalhou. Sem face selecionada, a marcação vale para o dente inteiro.')
      + (partes.length ? ' ' + partes.join(' ') : '');
  }

  svg.addEventListener('click', (e) => { const r = e.target.closest('[data-face]'); if (r) alternar(r.dataset.face); });
  svg.addEventListener('keydown', (e) => {
    const r = e.target.closest('[data-face]');
    if (r && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); alternar(r.dataset.face); svg.querySelector('[data-face="' + r.dataset.face + '"]')?.focus(); }
  });
  botoes.addEventListener('click', (e) => { const b = e.target.closest('[data-face]'); if (b) alternar(b.dataset.face); });

  // Chamado pelo script da página antes de marcar o dente inteiro: com faces selecionadas, a situação vale só para elas.
  O.aoMarcar = (situacao) => {
    const n = O.ativo;
    if (!n) return false;
    const d = dadosDe(n);
    if (!selecao.size) {
      // Dente inteiro saudável: as faces voltam a saudável junto.
      if (situacao === 'saudavel') for (const f of Object.keys(d.faces)) d.faces[f] = 'saudavel';
      return false;
    }
    for (const face of selecao) d.faces[face] = situacao;
    const todas = facesDe(n).map(f => d.faces[f] || 'saudavel');
    const doDente = todas.includes('precisaTratamento') ? 'precisaTratamento' : todas.includes('tratado') ? 'tratado' : 'saudavel';
    O.status[n] = doDente;
    d.status = doDente;
    return true;
  };

  document.getElementById('faces-para-evolucao')?.addEventListener('click', () => {
    const n = O.ativo;
    if (!n || !campoDentes) return;
    const escolhidas = emOrdem([...selecao]);
    const anotacao = n + (escolhidas.length ? ' (' + escolhidas.join(', ') + ')' : '');
    // Troca a anotação que já existir para este dente; senão, acrescenta no fim.
    const existente = new RegExp('(^|[\\s,;])' + n + '\\s*(\\([^()]*\\))?(?=$|[\\s,;])');
    const atual = campoDentes.value.trim();
    campoDentes.value = existente.test(atual) ? atual.replace(existente, '$1' + anotacao) : (atual ? atual.replace(/[\s,;]+$/, '') + ', ' : '') + anotacao;
    resumo.textContent = 'Anotado no campo "Dentes e faces" da evolução: ' + anotacao + '. Escolha o procedimento, descreva e salve o registro.';
    campoDentes.scrollIntoView({ behavior: 'smooth', block: 'center' });
    campoDentes.focus();
  });

  document.addEventListener('odonto:painel', (e) => {
    if (e.detail !== denteDaSelecao) { selecao = new Set(); denteDaSelecao = e.detail; }
    desenhar(e.detail);
  });
  document.addEventListener('odonto:mudou', () => {
    if (!O.ativo) { selecao = new Set(); denteDaSelecao = null; }
    desenhar(O.ativo);
  });
})();
