// Núcleo de dados (dono_banco.php): grafo vivo das tabelas no estilo "graph view", pipeline de eventos e inspetor.
// JavaScript puro, sem bibliotecas. Tudo o que vem do banco entra na tela por textContent, nunca como HTML.
(() => {
  const fonte = document.getElementById('dados-banco');
  const canvas = document.getElementById('grafo');
  if (!fonte || !canvas) return;
  const dados = JSON.parse(fonte.textContent);
  const ctx = canvas.getContext('2d');
  const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const numero = new Intl.NumberFormat('pt-BR');
  const cores = { agenda: '#4dd8ff', clinico: '#b48cff', financeiro: '#ffc857', acesso: '#7aa2ff', sistema: '#5ef0a8' };
  const corOperacao = { INSERT: '#5ef0a8', UPDATE: '#4dd8ff', DELETE: '#ff6b7a' };
  const nomeOperacao = { INSERT: 'INSERT', UPDATE: 'UPDATE', DELETE: 'DELETE' };

  // ---------- nós e ligações ----------
  const nucleo = { id: '__nucleo', nome: 'DENT IA', grupo: 'nucleo', x: 0, y: 0, vx: 0, vy: 0, fixo: true, raio: 16, atividade: 0 };
  const nos = new Map([[nucleo.id, nucleo]]);
  const raioDe = (registros) => 5 + Math.min(16, 3.2 * Math.log10(registros + 1));
  dados.tabelas.forEach((t, i) => {
    const angulo = (i / dados.tabelas.length) * Math.PI * 2;
    nos.set(t.tabela, { id: t.tabela, nome: t.tabela, grupo: t.grupo, info: t, raio: raioDe(t.registros),
      x: Math.cos(angulo) * 260, y: Math.sin(angulo) * 260, vx: 0, vy: 0, atividade: t.na_hora > 0 ? 0.35 : 0 });
  });
  const ligacoes = dados.relacoes.filter(r => nos.has(r.origem) && nos.has(r.destino))
    .map(r => ({ a: nos.get(r.origem), b: nos.get(r.destino) }));
  const vizinhos = new Map([...nos.keys()].map(id => [id, new Set()]));
  ligacoes.forEach(l => { vizinhos.get(l.a.id).add(l.b.id); vizinhos.get(l.b.id).add(l.a.id); });

  // Estado da interação (declarado antes da simulação, que consulta o nó arrastado).
  let sobre = null, selecionado = null, arrastado = null, arrastandoFundo = null, moveu = false;

  // ---------- simulação de forças ----------
  let calor = 1;
  function passo() {
    const lista = [...nos.values()];
    for (let i = 0; i < lista.length; i++) {
      for (let j = i + 1; j < lista.length; j++) {
        const p = lista[i], q = lista[j];
        let dx = q.x - p.x, dy = q.y - p.y, d2 = dx * dx + dy * dy || 0.01;
        const forca = 5200 / d2, d = Math.sqrt(d2);
        dx /= d; dy /= d;
        p.vx -= dx * forca; p.vy -= dy * forca; q.vx += dx * forca; q.vy += dy * forca;
      }
    }
    for (const l of ligacoes) {
      const dx = l.b.x - l.a.x, dy = l.b.y - l.a.y, d = Math.hypot(dx, dy) || 0.01;
      const forca = (d - 95) * 0.02;
      l.a.vx += dx / d * forca; l.a.vy += dy / d * forca; l.b.vx -= dx / d * forca; l.b.vy -= dy / d * forca;
    }
    for (const n of lista) {
      n.vx -= n.x * 0.004; n.vy -= n.y * 0.004; // gravidade para o centro
      if (n.fixo || n === arrastado) { n.vx = n.vy = 0; continue; }
      n.vx *= 0.82; n.vy *= 0.82;
      n.x += n.vx * calor; n.y += n.vy * calor;
    }
    calor = Math.max(0.02, calor * 0.995);
  }
  for (let i = 0; i < 260; i++) passo(); // já abre organizado

  // ---------- câmera, tamanho e interação ----------
  const camera = { x: 0, y: 0, zoom: 1 };
  let largura = 0, altura = 0, dpr = 1;
  function redimensionar() {
    const r = canvas.getBoundingClientRect();
    dpr = window.devicePixelRatio || 1;
    largura = r.width; altura = r.height;
    canvas.width = Math.round(largura * dpr); canvas.height = Math.round(altura * dpr);
    const espaco = Math.min(largura, altura);
    camera.zoom = Math.max(0.45, Math.min(1.1, espaco / 760));
  }
  new ResizeObserver(redimensionar).observe(canvas);
  redimensionar();
  const paraMundo = (px, py) => ({ x: (px - largura / 2) / camera.zoom + camera.x, y: (py - altura / 2) / camera.zoom + camera.y });
  function noEm(px, py) {
    const m = paraMundo(px, py);
    let melhor = null, menor = Infinity;
    for (const n of nos.values()) {
      const d = Math.hypot(n.x - m.x, n.y - m.y);
      if (d < n.raio + 6 / camera.zoom && d < menor) { melhor = n; menor = d; }
    }
    return melhor;
  }

  canvas.addEventListener('pointerdown', (e) => {
    const r = canvas.getBoundingClientRect();
    const n = noEm(e.clientX - r.left, e.clientY - r.top);
    moveu = false;
    canvas.setPointerCapture(e.pointerId);
    if (n && n !== nucleo) arrastado = n; else arrastandoFundo = { x: e.clientX, y: e.clientY, cx: camera.x, cy: camera.y };
  });
  canvas.addEventListener('pointermove', (e) => {
    const r = canvas.getBoundingClientRect();
    const px = e.clientX - r.left, py = e.clientY - r.top;
    if (arrastado) {
      const m = paraMundo(px, py);
      arrastado.x = m.x; arrastado.y = m.y; calor = Math.max(calor, 0.4); moveu = true;
    } else if (arrastandoFundo) {
      camera.x = arrastandoFundo.cx - (e.clientX - arrastandoFundo.x) / camera.zoom;
      camera.y = arrastandoFundo.cy - (e.clientY - arrastandoFundo.y) / camera.zoom;
      moveu = true;
    } else {
      sobre = noEm(px, py);
      canvas.style.cursor = sobre ? 'pointer' : 'grab';
    }
  });
  canvas.addEventListener('pointerup', (e) => {
    const r = canvas.getBoundingClientRect();
    if (!moveu) {
      const n = noEm(e.clientX - r.left, e.clientY - r.top);
      if (n && n !== nucleo) selecionar(n.id);
    }
    arrastado = null; arrastandoFundo = null;
  });
  canvas.addEventListener('pointerleave', () => { sobre = null; });
  canvas.addEventListener('wheel', (e) => {
    e.preventDefault();
    const r = canvas.getBoundingClientRect();
    const antes = paraMundo(e.clientX - r.left, e.clientY - r.top);
    camera.zoom = Math.max(0.3, Math.min(3, camera.zoom * (e.deltaY < 0 ? 1.12 : 0.89)));
    const depois = paraMundo(e.clientX - r.left, e.clientY - r.top);
    camera.x += antes.x - depois.x; camera.y += antes.y - depois.y;
  }, { passive: false });
  document.getElementById('grafo-reorganizar').addEventListener('click', () => {
    dados.tabelas.forEach((t, i) => {
      const n = nos.get(t.tabela), a = (i / dados.tabelas.length) * Math.PI * 2;
      n.x = Math.cos(a) * 260; n.y = Math.sin(a) * 260; n.vx = n.vy = 0;
    });
    camera.x = camera.y = 0; calor = 1;
    for (let i = 0; i < 200; i++) passo();
  });

  // ---------- desenho ----------
  const particulas = [];
  function desenhar() {
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, largura, altura);
    ctx.save();
    ctx.translate(largura / 2, altura / 2);
    ctx.scale(camera.zoom, camera.zoom);
    ctx.translate(-camera.x, -camera.y);
    const foco = sobre || selecionado;
    const ligadoAoFoco = (id) => !foco || id === foco.id || vizinhos.get(foco.id)?.has(id);

    for (const l of ligacoes) {
      const ativa = foco && (l.a === foco || l.b === foco);
      ctx.strokeStyle = ativa ? 'rgba(77,216,255,0.75)' : (foco ? 'rgba(120,150,190,0.07)' : 'rgba(120,150,190,0.22)');
      ctx.lineWidth = (ativa ? 1.6 : 1) / camera.zoom;
      ctx.beginPath(); ctx.moveTo(l.a.x, l.a.y); ctx.lineTo(l.b.x, l.b.y); ctx.stroke();
    }
    for (const p of particulas) { // eventos viajando do núcleo até a tabela
      const t = p.t, x = nucleo.x + (p.alvo.x - nucleo.x) * t, y = nucleo.y + (p.alvo.y - nucleo.y) * t;
      ctx.strokeStyle = p.cor; ctx.globalAlpha = 0.25; ctx.lineWidth = 1 / camera.zoom;
      ctx.beginPath(); ctx.moveTo(nucleo.x, nucleo.y); ctx.lineTo(x, y); ctx.stroke();
      ctx.globalAlpha = 1; ctx.fillStyle = p.cor;
      ctx.beginPath(); ctx.arc(x, y, 3.2 / Math.sqrt(camera.zoom), 0, Math.PI * 2); ctx.fill();
    }
    for (const n of nos.values()) {
      const cor = n === nucleo ? '#e9f6ff' : cores[n.grupo] || '#9fb3c8';
      const apagado = !ligadoAoFoco(n.id) && n !== nucleo;
      if (n.atividade > 0.02) { // brilho de "recebeu dados"
        ctx.fillStyle = cor; ctx.globalAlpha = 0.18 * n.atividade;
        ctx.beginPath(); ctx.arc(n.x, n.y, n.raio + 14 * n.atividade + 4, 0, Math.PI * 2); ctx.fill();
      }
      ctx.globalAlpha = apagado ? 0.18 : 1;
      ctx.fillStyle = n === nucleo ? '#0d1a28' : cor;
      ctx.beginPath(); ctx.arc(n.x, n.y, n.raio, 0, Math.PI * 2); ctx.fill();
      if (n === nucleo) { ctx.strokeStyle = '#4dd8ff'; ctx.lineWidth = 2 / camera.zoom; ctx.stroke(); }
      if (n === selecionado) { ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 2 / camera.zoom; ctx.beginPath(); ctx.arc(n.x, n.y, n.raio + 4, 0, Math.PI * 2); ctx.stroke(); }
      const mostrarNome = n === nucleo || camera.zoom > 0.85 || (foco && ligadoAoFoco(n.id)) || n.atividade > 0.3;
      if (mostrarNome && !apagado) {
        ctx.font = `${n === nucleo ? 600 : 500} ${11 / Math.sqrt(camera.zoom)}px "IBM Plex Mono", monospace`;
        ctx.textAlign = 'center'; ctx.fillStyle = n === foco ? '#ffffff' : 'rgba(207,227,245,0.85)';
        ctx.fillText(n.nome, n.x, n.y + n.raio + 13 / Math.sqrt(camera.zoom));
      }
      ctx.globalAlpha = 1;
    }
    ctx.restore();
  }

  let ultimoQuadro = performance.now();
  function animar(agora) {
    const dt = Math.min(0.05, (agora - ultimoQuadro) / 1000);
    ultimoQuadro = agora;
    if (calor > 0.021 || arrastado) passo();
    for (const n of nos.values()) n.atividade = Math.max(0, n.atividade - dt * 0.25);
    for (let i = particulas.length - 1; i >= 0; i--) {
      const p = particulas[i];
      p.t += dt * 1.4;
      if (p.t >= 1) { p.alvo.atividade = 1; particulas.splice(i, 1); }
    }
    nucleo.atividade = Math.max(nucleo.atividade, particulas.length ? 0.6 : 0);
    desenhar();
    requestAnimationFrame(animar);
  }
  requestAnimationFrame(animar);

  // ---------- inspetor ----------
  const inspetor = document.getElementById('inspetor-corpo');
  function elemento(tag, texto, classe) { const e = document.createElement(tag); if (texto !== undefined) e.textContent = texto; if (classe) e.className = classe; return e; }
  function selecionar(id) {
    const n = nos.get(id);
    if (!n || !n.info) return;
    selecionado = n;
    const t = n.info;
    inspetor.replaceChildren();
    const cabeca = elemento('div', undefined, 'hud-inspetor-cabeca');
    const marca = elemento('i'); marca.className = 'grupo-' + t.grupo;
    cabeca.append(marca, elemento('strong', t.tabela, 'hud-mono'));
    const abrir = elemento('a', 'Ver conteúdo →', 'hud-botao');
    abrir.href = '?tabela=' + encodeURIComponent(t.tabela) + '#conteudo';
    cabeca.append(abrir);
    const lista = elemento('dl', undefined, 'hud-inspetor-dados');
    for (const [rotulo, valor] of [['Registros', numero.format(t.registros)], ['Última alteração', t.ultima || '—'],
      ['Inc. / Alt. / Exc.', t.contagem], ['Tamanho', t.tamanho], ['Colunas', String(t.colunas.length)]]) {
      const linha = elemento('div'); linha.append(elemento('dt', rotulo), elemento('dd', valor, 'hud-mono')); lista.append(linha);
    }
    const ligadas = elemento('div', undefined, 'hud-ligadas');
    ligadas.append(elemento('span', 'Ligada a: ', 'hud-fraco'));
    const viz = [...vizinhos.get(id)].sort();
    if (!viz.length) ligadas.append(elemento('span', 'nenhuma tabela', 'hud-fraco'));
    for (const v of viz) {
      const b = elemento('button', v, 'hud-link hud-mono'); b.type = 'button'; b.addEventListener('click', () => selecionar(v)); ligadas.append(b);
    }
    const cols = elemento('p', t.colunas.join(' · '), 'hud-mono hud-colunas');
    inspetor.append(cabeca, lista, ligadas, cols);
  }
  document.querySelectorAll('[data-selecionar]').forEach(b => b.addEventListener('click', () => {
    selecionar(b.dataset.selecionar);
    document.getElementById('inspetor').scrollIntoView({ behavior: semMovimento ? 'auto' : 'smooth', block: 'nearest' });
  }));
  if (dados.aberta && nos.has(dados.aberta)) selecionar(dados.aberta);

  // ---------- pipeline (fluxo de eventos) ----------
  const fluxo = document.getElementById('corpo-registro');
  const aviso = document.getElementById('registro-novos');
  const recentes = []; // horários dos eventos para a taxa por minuto
  let novos = 0;
  function evento(r, animarEvento) {
    const li = elemento('li', undefined, 'hud-evento op-' + r.operacao.toLowerCase());
    const linha1 = elemento('div', undefined, 'hud-evento-linha');
    linha1.append(elemento('span', r.momento, 'hud-mono hud-fraco'), elemento('span', nomeOperacao[r.operacao] || r.operacao, 'hud-op'),
      elemento('button', r.tabela, 'hud-link hud-mono'));
    linha1.lastChild.type = 'button';
    linha1.lastChild.addEventListener('click', () => selecionar(r.tabela));
    if (r.id_registro) linha1.append(elemento('span', '#' + r.id_registro, 'hud-mono hud-fraco'));
    const linha2 = elemento('div', undefined, 'hud-evento-detalhe hud-mono');
    linha2.textContent = (r.colunas ? '∆ ' + r.colunas + '  ' : '') + '← ' + r.usuario;
    li.append(linha1, linha2);
    fluxo.prepend(li);
    while (fluxo.children.length > 150) fluxo.lastChild.remove();
    const alvo = nos.get(r.tabela);
    if (alvo && animarEvento && !semMovimento) particulas.push({ alvo, t: 0, cor: corOperacao[r.operacao] || '#ffffff' });
    else if (alvo && animarEvento) alvo.atividade = 1;
    if (animarEvento) { li.classList.add('chegou-agora'); recentes.push(Date.now()); }
  }
  if (!dados.alteracoes.length) fluxo.append(elemento('li', 'Nenhuma alteração registrada ainda. Use o sistema em outra aba e acompanhe aqui.', 'hud-evento vazio'));
  dados.alteracoes.forEach(r => evento(r, false));

  // ---------- atualização ao vivo ----------
  const estado = document.getElementById('monitor-estado');
  const textoEstado = estado.querySelector('.hud-estado-texto');
  const botaoPausar = document.getElementById('monitor-pausar');
  let ultimoId = dados.ultimoId, pausado = false, ocupado = false;
  async function buscar() {
    if (pausado || ocupado || document.hidden) return;
    ocupado = true;
    try {
      const resposta = await fetch('api_banco.php?desde=' + ultimoId, { credentials: 'same-origin', cache: 'no-store' });
      const corpo = await resposta.json();
      if (!resposta.ok) throw new Error(corpo.erro || 'falha ao consultar');
      fluxo.querySelector('.vazio')?.remove();
      for (const r of corpo.alteracoes) { evento(r, true); ultimoId = Math.max(ultimoId, Number(r.id)); novos++; }
      let total = 0;
      for (const t of corpo.tabelas) {
        total += t.registros;
        const n = nos.get(t.tabela);
        if (!n) continue;
        Object.assign(n.info, t);
        n.raio = raioDe(t.registros);
      }
      if (selecionado) selecionar(selecionado.id);
      document.getElementById('kpi-registros').textContent = numero.format(total);
      document.getElementById('kpi-tamanho').textContent = corpo.resumo.tamanho;
      document.getElementById('kpi-hoje').textContent = numero.format(corpo.resumo.alteracoes_hoje);
      document.getElementById('kpi-conexoes').textContent = numero.format(corpo.resumo.conexoes);
      while (recentes.length && recentes[0] < Date.now() - 60000) recentes.shift();
      document.getElementById('kpi-taxa').textContent = String(recentes.length);
      if (novos) aviso.textContent = novos + ' evento(s) nesta sessão';
      estado.classList.remove('com-erro');
      textoEstado.textContent = 'Ao vivo · ' + new Date().toLocaleTimeString('pt-BR');
    } catch (erro) {
      estado.classList.add('com-erro');
      textoEstado.textContent = 'Sem conexão — tentando de novo';
    } finally {
      ocupado = false;
    }
  }
  botaoPausar.addEventListener('click', () => {
    pausado = !pausado;
    botaoPausar.setAttribute('aria-pressed', pausado ? 'true' : 'false');
    botaoPausar.replaceChildren(elemento('i', undefined, 'bi ' + (pausado ? 'bi-play-fill' : 'bi-pause-fill')), document.createTextNode(pausado ? ' Continuar' : ' Pausar'));
    estado.classList.toggle('pausado', pausado);
    textoEstado.textContent = pausado ? 'Pausado' : 'Ao vivo';
    if (!pausado) buscar();
  });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) buscar(); });
  setInterval(buscar, 3000);
})();
