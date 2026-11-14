// Arcada em 3D do odontograma: modo realista e modo raio-X.
// Os dentes são gerados por código a partir de medidas médias de anatomia dental (altura da coroa, comprimento da raiz
// e larguras mésio-distal e vestíbulo-lingual de cada dente, em milímetros). Não é a boca real do paciente:
// é um modelo anatômico que mostra a situação anotada no odontograma (dente e faces).
// Biblioteca: three.js r170 (MIT), hospedada no próprio sistema (js/three-module-min.js) por causa da CSP.
import * as THREE from './three-module-min.js';
import { RoomEnvironment } from './three-ambiente.js';

const O = window.DentiaOdonto;
const caixa = document.getElementById('odonto-3d');
const esquema = document.getElementById('odontograma-wrapper');
const botoesVista = document.querySelectorAll('[data-vista]');

// Medidas médias por posição no quadrante (1 = incisivo central ... 8 = terceiro molar), em mm:
// ch = altura da coroa, rl = comprimento da raiz, md = largura mésio-distal, vl = largura vestíbulo-lingual.
const MEDIDAS = {
  superior: {
    1: { tipo: 'incisivo', ch: 10.5, rl: 13, md: 8.5, vl: 7, raizes: 'unica' },
    2: { tipo: 'incisivo', ch: 9, rl: 13, md: 6.5, vl: 6, raizes: 'unica' },
    3: { tipo: 'canino', ch: 10, rl: 17, md: 7.5, vl: 8, raizes: 'unica' },
    4: { tipo: 'premolar', ch: 8.5, rl: 14, md: 7, vl: 9, raizes: 'vestibular-palatina' },
    5: { tipo: 'premolar', ch: 8.5, rl: 14, md: 7, vl: 9, raizes: 'unica' },
    6: { tipo: 'molar', ch: 7.5, rl: 12.5, md: 10, vl: 11, raizes: 'tres' },
    7: { tipo: 'molar', ch: 7, rl: 11.5, md: 9, vl: 11, raizes: 'tres' },
    8: { tipo: 'molar', ch: 6.5, rl: 11, md: 8.5, vl: 10, raizes: 'fundidas' },
  },
  inferior: {
    1: { tipo: 'incisivo', ch: 9, rl: 12.5, md: 5, vl: 6, raizes: 'unica' },
    2: { tipo: 'incisivo', ch: 9.5, rl: 14, md: 5.5, vl: 6.5, raizes: 'unica' },
    3: { tipo: 'canino', ch: 11, rl: 16, md: 7, vl: 7.5, raizes: 'unica' },
    4: { tipo: 'premolar', ch: 8.5, rl: 14, md: 7, vl: 7.5, raizes: 'unica' },
    5: { tipo: 'premolar', ch: 8, rl: 14.5, md: 7, vl: 8, raizes: 'unica' },
    6: { tipo: 'molar', ch: 7.5, rl: 14, md: 11, vl: 10.5, raizes: 'mesial-distal' },
    7: { tipo: 'molar', ch: 7, rl: 13, md: 10.5, vl: 10, raizes: 'mesial-distal' },
    8: { tipo: 'molar', ch: 7, rl: 11, md: 10, vl: 9.5, raizes: 'fundidas' },
  },
};
const ABERTURA = 7;                 // metade da distância entre os planos oclusais (boca entreaberta), mm
const ARCO = { superior: { fundo: 46, c: 0.060 }, inferior: { fundo: 43, c: 0.066 } }; // parábola z = fundo - c·x²
const FACE = { mesial: 0, distal: 1, vestibular: 2, lingual: 3, palatina: 3, oclusal: 4, incisal: 4 };
const suave = (x) => { x = Math.min(1, Math.max(0, x)); return x * x * (3 - 2 * x); };

/** Coroa do dente no sistema local: +x mesial, +z vestibular, +y em direção à face oclusal/incisal; y = 0 é o colo. */
function geometriaDaCoroa({ tipo, ch, md, vl }) {
  const SEG = 44, pos = [], faces = [], alturas = [], indices = [];
  const posterior = tipo === 'molar' || tipo === 'premolar';
  const expoente = tipo === 'molar' ? 3.4 : tipo === 'premolar' ? 2.7 : 2.2; // seção em "superelipse": molar mais quadrado
  const aneis = [];
  if (posterior) {
    const PAREDE = 12, TAMPO = 8;
    for (let i = 0; i <= PAREDE; i++) {
      const t = i / PAREDE;
      const w = 0.80 + 0.20 * Math.sin(t * 1.95) / Math.sin(1.95);       // colo mais estreito, bojo no terço médio
      aneis.push({ a: md / 2 * w, b: vl / 2 * w, y: () => ch * 0.78 * t, t: 0.78 * t, tampo: false });
    }
    for (let k = 1; k < TAMPO; k++) {
      const s = 1 - k / TAMPO;                                           // 1 na borda, 0 no centro da face oclusal
      const cuspide = (ang) => tipo === 'molar' ? Math.pow(Math.abs(Math.sin(2 * ang)), 1.4) : Math.pow(Math.abs(Math.sin(ang)), 2.2);
      aneis.push({
        a: md / 2 * Math.pow(s, 0.9), b: vl / 2 * Math.pow(s, 0.9), tampo: true, t: 0.9,
        y: (ang) => ch * (0.78 + 0.15 * (1 - s * s)) + ch * 0.14 * cuspide(ang) * Math.sin(Math.PI * s) - ch * 0.09 * Math.pow(1 - s, 3),
      });
    }
    aneis.centro = ch * 0.84;                                            // fossa central
  } else {
    const N = 18;
    for (let i = 0; i <= N; i++) {
      const t = i / N;
      let A, B;
      if (tipo === 'canino') {
        A = t < 0.55 ? 0.72 + 0.28 * suave(t / 0.55) : 1 - 0.82 * Math.pow((t - 0.55) / 0.45, 1.6);
        B = t < 0.35 ? 0.82 + 0.18 * suave(t / 0.35) : 1 - 0.80 * Math.pow((t - 0.35) / 0.65, 1.4);
      } else {
        A = t < 0.65 ? 0.70 + 0.30 * suave(t / 0.65) : 1 - 0.05 * (t - 0.65) / 0.35;
        if (t > 0.9) A *= 1 - 0.14 * Math.pow((t - 0.9) / 0.1, 2);       // ângulos incisais arredondados
        B = t < 0.28 ? 0.80 + 0.20 * suave(t / 0.28) : 1 - 0.84 * Math.pow((t - 0.28) / 0.72, 1.25); // afina até a borda incisal
      }
      aneis.push({ a: md / 2 * A, b: vl / 2 * B, y: () => ch * t, t, tampo: false });
    }
    aneis.centro = ch;
  }
  for (const anel of aneis) {
    for (let j = 0; j < SEG; j++) {
      const ang = j / SEG * Math.PI * 2, co = Math.cos(ang), si = Math.sin(ang);
      pos.push(anel.a * Math.sign(co) * Math.pow(Math.abs(co), 2 / expoente), anel.y(ang), anel.b * Math.sign(si) * Math.pow(Math.abs(si), 2 / expoente));
      alturas.push(anel.t);
      const topo = posterior ? anel.tampo : anel.t > 0.80;
      faces.push(topo ? 4 : Math.abs(co) > Math.abs(si) ? (co > 0 ? 0 : 1) : (si > 0 ? 2 : 3));
    }
  }
  for (let i = 0; i < aneis.length - 1; i++) {
    for (let j = 0; j < SEG; j++) {
      const a = i * SEG + j, b = i * SEG + (j + 1) % SEG, c = a + SEG, d = b + SEG;
      indices.push(a, c, b, b, c, d);
    }
  }
  const centro = pos.length / 3, ultimo = (aneis.length - 1) * SEG;
  pos.push(0, aneis.centro, 0); alturas.push(1); faces.push(4);
  for (let j = 0; j < SEG; j++) indices.push(ultimo + j, centro, ultimo + (j + 1) % SEG);
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  g.setAttribute('altura', new THREE.Float32BufferAttribute(alturas, 1));
  g.setAttribute('color', new THREE.Float32BufferAttribute(new Float32Array(pos.length), 3));
  g.setAttribute('densidade', new THREE.Float32BufferAttribute(new Float32Array(pos.length / 3).fill(1), 1));
  g.setIndex(indices);
  g.computeVertexNormals();
  g.userData.faces = faces;
  return g;
}

/** Uma raiz: cone afilado do colo (y = 0) até o ápice (y = -comprimento), com leve curvatura. */
function geometriaDaRaiz(rx, rz, comprimento, curvaX, curvaZ, dx, dz) {
  const SEG = 20, N = 12, pos = [], indices = [];
  for (let i = 0; i <= N; i++) {
    const t = i / N, escala = (1 - Math.pow(t, 1.7)) * 0.96 + 0.04;
    for (let j = 0; j < SEG; j++) {
      const ang = j / SEG * Math.PI * 2;
      pos.push(dx + curvaX * t * t + rx * escala * Math.cos(ang), -comprimento * t, dz + curvaZ * t * t + rz * escala * Math.sin(ang));
    }
  }
  for (let i = 0; i < N; i++) for (let j = 0; j < SEG; j++) {
    const a = i * SEG + j, b = i * SEG + (j + 1) % SEG, c = a + SEG, d = b + SEG;
    indices.push(a, b, c, b, d, c);
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  g.setAttribute('densidade', new THREE.Float32BufferAttribute(new Float32Array(pos.length / 3).fill(0.72), 1));
  g.setIndex(indices);
  g.computeVertexNormals();
  return g;
}

function raizesDe({ raizes, md, vl, rl }) {
  const tronco = geometriaDaRaiz(md * 0.40, vl * 0.42, rl * 0.38, 0, 0, 0, 0);
  switch (raizes) {
    case 'vestibular-palatina': return [tronco, geometriaDaRaiz(md * 0.26, vl * 0.20, rl, -0.5, 1.0, 0, vl * 0.20), geometriaDaRaiz(md * 0.25, vl * 0.19, rl * 0.96, -0.5, -1.0, 0, -vl * 0.20)];
    case 'mesial-distal': return [tronco, geometriaDaRaiz(md * 0.20, vl * 0.36, rl, -1.3, 0, md * 0.24, 0), geometriaDaRaiz(md * 0.19, vl * 0.33, rl * 0.93, -0.4, 0, -md * 0.24, 0)];
    case 'tres': return [tronco, geometriaDaRaiz(md * 0.18, vl * 0.20, rl, -0.8, 0.6, md * 0.22, vl * 0.20), geometriaDaRaiz(md * 0.16, vl * 0.18, rl * 0.92, -0.9, 0.4, -md * 0.22, vl * 0.20),
      geometriaDaRaiz(md * 0.24, vl * 0.22, rl * 1.05, 0, -2.4, 0, -vl * 0.24)];
    case 'fundidas': return [geometriaDaRaiz(md * 0.36, vl * 0.38, rl, -1.6, 0, 0, 0)];
    default: return [geometriaDaRaiz(md * 0.36, vl * 0.40, rl, -0.8, 0, 0, 0)];
  }
}

/** Posição de cada dente ao longo do arco: percorre a parábola somando as larguras mésio-distais a partir da linha média. */
function posicoesNoArco(qual) {
  const { fundo, c } = ARCO[qual], lista = [];
  let s = 0, u = 0, percorrido = 0;
  for (let p = 1; p <= 8; p++) {
    const m = MEDIDAS[qual][p], alvo = s + m.md / 2 + 0.1;
    while (percorrido < alvo) { percorrido += Math.sqrt(1 + 4 * c * c * u * u) * 0.02; u += 0.02; }
    lista.push({ p, u, z: fundo - c * u * u, inclinacao: 2 * c * u });
    s += m.md + 0.2;
  }
  return lista;
}

/** Faixa da gengiva: varre uma elipse ao longo da linha do colo. meiaEspessura no sentido vestíbulo-lingual, meiaAltura no da raiz. */
function faixaGengival(linha, paraOclusal, material) {
  const MEIA_ESPESSURA = 5.6, MEIA_ALTURA = 8.6, CENTRO = 8.1, SEG = 22, PASSOS = 120;
  const curva = new THREE.CatmullRomCurve3(linha.map(g => new THREE.Vector3(g.x, g.y, g.z)));
  const pos = [], indices = [], cima = new THREE.Vector3(0, -paraOclusal, 0); // "cima" = em direção à raiz
  for (let i = 0; i <= PASSOS; i++) {
    const t = i / PASSOS, ponto = curva.getPoint(t), tangente = curva.getTangent(t);
    const fora = new THREE.Vector3().crossVectors(tangente, new THREE.Vector3(0, 1, 0)).normalize();
    if (fora.z < 0 && Math.abs(ponto.x) < 8) fora.negate();
    // Afina nas pontas do arco, atrás dos últimos molares.
    const ponta = Math.min(1, Math.min(t, 1 - t) / 0.05), escala = 0.55 + 0.45 * suave(ponta);
    const centro = ponto.clone().addScaledVector(cima, CENTRO);
    for (let j = 0; j < SEG; j++) {
      const ang = j / SEG * Math.PI * 2;
      const v = centro.clone().addScaledVector(fora, Math.cos(ang) * MEIA_ESPESSURA * escala).addScaledVector(cima, Math.sin(ang) * MEIA_ALTURA * escala);
      pos.push(v.x, v.y, v.z);
    }
  }
  for (let i = 0; i < PASSOS; i++) for (let j = 0; j < SEG; j++) {
    const a = i * SEG + j, b = i * SEG + (j + 1) % SEG, c = a + SEG, d = b + SEG;
    indices.push(a, b, c, b, d, c);
  }
  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  g.setAttribute('densidade', new THREE.Float32BufferAttribute(new Float32Array(pos.length / 3).fill(0.16), 1));
  g.setIndex(indices);
  g.computeVertexNormals();
  const malha = new THREE.Mesh(g, material);
  material.side = THREE.DoubleSide; // a faixa é aberta nas pontas e o sentido das faces muda entre as arcadas
  malha.userData.gengiva = true;
  return malha;
}

function iniciar() {
  const canvas = caixa.querySelector('canvas');
  const info = caixa.querySelector('[data-3d-info]');
  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  } catch (erro) {
    caixa.querySelector('[data-3d-erro]').hidden = false;
    canvas.hidden = true;
    return null;
  }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;
  const cena = new THREE.Scene();
  const ambiente = new THREE.PMREMGenerator(renderer).fromScene(new RoomEnvironment(), 0.04).texture;
  const camera = new THREE.PerspectiveCamera(32, 1, 1, 1000);
  cena.add(new THREE.HemisphereLight(0xffffff, 0x8a97a6, 0.55));
  const principal = new THREE.DirectionalLight(0xffffff, 1.7); principal.position.set(30, 60, 120); cena.add(principal);
  const contra = new THREE.DirectionalLight(0xbfd4ff, 0.6); contra.position.set(-60, -20, -40); cena.add(contra);

  // Raio-X: mais claro onde o raio atravessa mais material (bordas), como numa radiografia; a densidade por vértice
  // deixa a restauração radiopaca (clara) e a lesão radiolúcida (escura).
  const materialRaioX = (cor) => new THREE.ShaderMaterial({
    uniforms: { cor: { value: new THREE.Color(cor) }, ganho: { value: 1 } },
    vertexShader: 'attribute float densidade; varying vec3 vN; varying vec3 vV; varying float vD;'
      + 'void main(){ vec4 mv = modelViewMatrix * vec4(position,1.0); vN = normalize(normalMatrix * normal); vV = normalize(-mv.xyz); vD = densidade; gl_Position = projectionMatrix * mv; }',
    fragmentShader: 'uniform vec3 cor; uniform float ganho; varying vec3 vN; varying vec3 vV; varying float vD;'
      + 'void main(){ float borda = 1.0 - abs(dot(normalize(vN), normalize(vV))); float i = (0.09 + 0.62 * pow(borda, 2.2)) * vD * ganho;'
      + ' gl_FragColor = vec4(cor * i, 1.0); }',
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide,
  });
  const matRaiz = new THREE.MeshStandardMaterial({ color: 0xdcc79c, roughness: 0.72 });
  const matGengiva = new THREE.MeshPhysicalMaterial({ color: 0xc8656f, roughness: 0.42, clearcoat: 0.55, clearcoatRoughness: 0.35 });
  const xRaiz = materialRaioX(0xcfe0ff), xOsso = materialRaioX(0x9fb8d8);

  const dentes = {};      // numero -> { grupo, coroa, normal, raiox, raizes }
  const coroas = [];
  for (const qual of ['superior', 'inferior']) {
    const paraOclusal = qual === 'superior' ? -1 : 1;
    const linhaGengival = [];
    for (const lado of [-1, 1]) {            // -1 = lado direito do paciente (à esquerda de quem olha)
      const quadrante = qual === 'superior' ? (lado < 0 ? 1 : 2) : (lado < 0 ? 4 : 3);
      for (const { p, u, z, inclinacao } of posicoesNoArco(qual)) {
        const m = MEDIDAS[qual][p], numero = quadrante * 10 + p, x = lado * u;
        const norma = Math.hypot(1, inclinacao);
        const tangente = new THREE.Vector3(1, 0, -lado * inclinacao).divideScalar(norma);       // sentido de x crescente
        const paraFora = new THREE.Vector3(lado * inclinacao, 0, 1).divideScalar(norma);        // vestibular
        const mesial = tangente.clone().multiplyScalar(-lado);                                    // em direção à linha média
        const colo = paraOclusal * -(ABERTURA + m.ch);
        const grupo = new THREE.Group();
        grupo.matrixAutoUpdate = false;
        grupo.matrix.makeBasis(mesial, new THREE.Vector3(0, paraOclusal, 0), paraFora).setPosition(x, colo, z);
        const normal = new THREE.MeshPhysicalMaterial({ vertexColors: true, roughness: 0.2, clearcoat: 0.85, clearcoatRoughness: 0.18, ior: 1.62, envMapIntensity: 0.9 });
        const raiox = materialRaioX(0xe6f0ff);
        const coroa = new THREE.Mesh(geometriaDaCoroa(m), normal);
        coroa.userData.numero = numero;
        const raizes = raizesDe(m).map(g => new THREE.Mesh(g, matRaiz));
        grupo.add(coroa, ...raizes);
        cena.add(grupo);
        dentes[numero] = { grupo, coroa, normal, raiox, raizes, medida: m, arco: qual };
        coroas.push(coroa);
        linhaGengival.push({ x, y: colo, z, paraFora });
      }
    }
    // Gengiva e osso alveolar: uma faixa de seção oval (fina no sentido vestíbulo-lingual, alta no sentido da raiz)
    // que acompanha a linha do colo de cada dente e cobre as raízes.
    linhaGengival.sort((a, b) => a.x - b.x);
    const faixa = faixaGengival(linhaGengival, paraOclusal, matGengiva);
    faixa.userData.arco = qual;
    cena.add(faixa);
  }

  // Cor de cada vértice da coroa: esmalte (mais amarelado no colo, mais claro na borda) e, por cima, a situação da face.
  const ESMALTE_COLO = new THREE.Color(0xe7d8b8), ESMALTE = new THREE.Color(0xf3efe6);
  const RESTAURACAO = new THREE.Color(0x9fb0c2), LESAO = new THREE.Color(0x6e4322);
  function situacaoDasFaces(numero) {
    const dado = O.dados[String(numero)] || {}, status = O.status[numero] || 'saudavel';
    const faces = dado.faces && !Array.isArray(dado.faces) ? dado.faces : {};
    const porIndice = ['saudavel', 'saudavel', 'saudavel', 'saudavel', 'saudavel'];
    let alguma = false;
    for (const nome in faces) if (faces[nome] !== 'saudavel' && FACE[nome] !== undefined) { porIndice[FACE[nome]] = faces[nome]; alguma = true; }
    // Dente marcado inteiro, sem faces: a marca aparece na face oclusal/incisal.
    if (!alguma && (status === 'tratado' || status === 'precisaTratamento')) porIndice[4] = status;
    return { porIndice, ausente: status === 'ausente' };
  }
  let modo = '3d';
  const arcoVisivel = { superior: true, inferior: true }; // nas vistas oclusais só a arcada escolhida aparece
  function atualizar() {
    const cor = new THREE.Color();
    for (const numero in dentes) {
      const d = dentes[numero], g = d.coroa.geometry, faces = g.userData.faces;
      const { porIndice, ausente } = situacaoDasFaces(Number(numero));
      d.grupo.visible = !ausente && arcoVisivel[d.arco];
      const cores = g.attributes.color, dens = g.attributes.densidade, alturas = g.attributes.altura;
      for (let i = 0; i < faces.length; i++) {
        const s = porIndice[faces[i]];
        cor.copy(ESMALTE_COLO).lerp(ESMALTE, suave(alturas.getX(i) * 1.6));
        if (s === 'tratado') cor.lerp(RESTAURACAO, 0.9); else if (s === 'precisaTratamento') cor.lerp(LESAO, 0.9);
        cores.setXYZ(i, cor.r, cor.g, cor.b);
        dens.setX(i, s === 'tratado' ? 2.3 : s === 'precisaTratamento' ? 0.28 : 1);
      }
      cores.needsUpdate = true; dens.needsUpdate = true;
      const ativo = Number(numero) === Number(O.ativo);
      d.normal.emissive.set(ativo ? 0x2457f5 : 0x000000);
      d.normal.emissiveIntensity = ativo ? 0.35 : 0;
      d.raiox.uniforms.cor.value.set(ativo ? 0xffd27a : 0xe6f0ff);
      d.raiox.uniforms.ganho.value = ativo ? 1.5 : 1;
      d.coroa.material = modo === 'raiox' ? d.raiox : d.normal;
      for (const r of d.raizes) r.material = modo === 'raiox' ? xRaiz : matRaiz;
    }
    cena.traverse(o => {
      if (!o.userData.gengiva) return;
      o.material = modo === 'raiox' ? xOsso : matGengiva;
      o.visible = arcoVisivel[o.userData.arco];
    });
    cena.environment = modo === 'raiox' ? null : ambiente;
    caixa.dataset.modo = modo;
    if (info) {
      const n = O.ativo, nome = n && O.dentes[n] ? 'Dente ' + n + ' — ' + O.dentes[n].nome : 'Clique num dente para selecionar. Arraste para girar; role para aproximar.';
      info.textContent = nome;
    }
    desenhar();
  }

  // Câmera em órbita: arrastar gira, rolar aproxima. Clique sem arrastar seleciona o dente.
  const alvo = new THREE.Vector3(0, 0, 22);
  const orbita = { az: 0, el: 0.12, dist: 134 };
  let pendente = false;
  function posicionarCamera() {
    const { az, el, dist } = orbita;
    camera.position.set(alvo.x + dist * Math.sin(az) * Math.cos(el), alvo.y + dist * Math.sin(el), alvo.z + dist * Math.cos(az) * Math.cos(el));
    camera.lookAt(alvo);
    camera.updateMatrixWorld();
  }
  function desenhar() {
    if (pendente || caixa.hidden) return;
    pendente = true;
    requestAnimationFrame(() => {
      pendente = false;
      posicionarCamera();
      renderer.render(cena, camera);
    });
  }
  function ajustarTamanho() {
    const largura = caixa.clientWidth, altura = Math.round(Math.min(560, Math.max(320, largura * 0.62)));
    if (!largura) return;
    renderer.setSize(largura, altura, false);
    canvas.style.height = altura + 'px';
    camera.aspect = largura / altura;
    camera.updateProjectionMatrix();
    desenhar();
  }
  new ResizeObserver(ajustarTamanho).observe(caixa);

  let arrasto = null;
  canvas.addEventListener('pointerdown', (e) => {
    arrasto = { x: e.clientX, y: e.clientY, moveu: 0 };
    try { canvas.setPointerCapture(e.pointerId); } catch (erro) { /* ponteiro sem captura: o arrasto só para na borda do visor */ }
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!arrasto) return;
    const dx = e.clientX - arrasto.x, dy = e.clientY - arrasto.y;
    arrasto.moveu += Math.abs(dx) + Math.abs(dy);
    arrasto.x = e.clientX; arrasto.y = e.clientY;
    orbita.az -= dx * 0.008;
    orbita.el = Math.min(1.45, Math.max(-1.45, orbita.el + dy * 0.008));
    desenhar();
  });
  canvas.addEventListener('pointerup', (e) => {
    const foiClique = arrasto && arrasto.moveu < 6;
    arrasto = null;
    if (!foiClique) return;
    const r = canvas.getBoundingClientRect();
    const ponto = new THREE.Vector2((e.clientX - r.left) / r.width * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    // O clique não depende de um quadro já ter sido desenhado (aba em segundo plano adia o desenho).
    posicionarCamera();
    cena.updateMatrixWorld();
    const raio = new THREE.Raycaster();
    raio.setFromCamera(ponto, camera);
    const acerto = raio.intersectObjects(coroas.filter(c => c.parent.visible), false)[0];
    if (acerto) O.selecionar(acerto.object.userData.numero);
  });
  canvas.addEventListener('wheel', (e) => {
    e.preventDefault();
    orbita.dist = Math.min(260, Math.max(55, orbita.dist * (e.deltaY > 0 ? 1.08 : 0.92)));
    desenhar();
  }, { passive: false });
  const VISTAS = { frente: { az: 0, el: 0.12, dist: 134 }, superior: { az: 0, el: -1.2, dist: 112 }, inferior: { az: 0, el: 1.2, dist: 112 },
    direita: { az: -1.25, el: 0.08, dist: 134 }, esquerda: { az: 1.25, el: 0.08, dist: 134 } };
  caixa.addEventListener('click', (e) => {
    const b = e.target.closest('[data-camera]');
    if (!b || !VISTAS[b.dataset.camera]) return;
    Object.assign(orbita, VISTAS[b.dataset.camera]);
    // Vista oclusal: a outra arcada sai da frente.
    arcoVisivel.superior = b.dataset.camera !== 'inferior';
    arcoVisivel.inferior = b.dataset.camera !== 'superior';
    atualizar();
  });

  document.addEventListener('odonto:mudou', atualizar);
  return { definirModo(novo) { modo = novo; ajustarTamanho(); atualizar(); } };
}

if (O && caixa && esquema) {
  let visor = null, falhou = false;
  function mostrar(vista) {
    const tresD = vista !== '2d';
    if (tresD && !visor && !falhou) { visor = iniciar(); falhou = !visor; }
    esquema.hidden = tresD;
    caixa.hidden = !tresD;
    for (const b of botoesVista) b.setAttribute('aria-pressed', b.dataset.vista === vista ? 'true' : 'false');
    if (tresD && visor) visor.definirModo(vista === 'raiox' ? 'raiox' : '3d');
  }
  for (const b of botoesVista) b.addEventListener('click', () => mostrar(b.dataset.vista));
}
