// Agendamento: o PostgreSQL define os horários realmente disponíveis.
(() => {
  for (const form of document.querySelectorAll('form[data-agendamento-dinamico]')) {
    // Na remarcação, paciente e procedimento vêm de um campo oculto com o mesmo nome do select desabilitado.
    const campo = (nome) => {
      const el = form.elements.namedItem(nome);
      return el instanceof RadioNodeList ? [...el].find(e => !e.disabled) : el;
    };
    const horario = campo('horario');
    if (!horario) continue;
    let requisicao = 0;
    async function atualizar() {
      const data = campo('data')?.value;
      const proc = campo('procedimento')?.value;
      const dentista = campo('dentista')?.value || '0';
      const paciente = campo('paciente_id')?.value || '0';
      if (!data || !proc) return;
      const atual = ++requisicao;
      const antigo = horario.value;
      horario.disabled = true;
      const url = new URL('api_horarios.php', window.location.href);
      url.searchParams.set('data', data);
      url.searchParams.set('procedimento', proc);
      url.searchParams.set('dentista', dentista);
      url.searchParams.set('paciente_id', paciente);
      try {
        const resposta = await fetch(url, {credentials: 'same-origin'});
        const corpo = await resposta.json().catch(() => ({}));
        if (atual !== requisicao) return;
        if (!resposta.ok) {
          // Mostra a explicação do servidor (ex.: "Pelo portal, agende a partir de..."), não um erro genérico.
          horario.replaceChildren(new Option(corpo.erro || 'Erro ao consultar horários. Recarregue a página.', ''));
          horario.disabled = true;
          return;
        }
        const horas = corpo.horarios || [];
        horario.replaceChildren(...(horas.length ? horas.map(h => new Option(h, h)) : [new Option('Sem horários disponíveis', '')]));
        if (horas.includes(antigo)) horario.value = antigo;
        horario.disabled = horas.length === 0;
      } catch (erro) {
        if (atual !== requisicao) return;
        horario.replaceChildren(new Option('Erro ao consultar horários. Recarregue a página.', ''));
        horario.disabled = true;
      }
    }

    // ---- Quadro da recepção: todos os horários livres de cada dentista no dia escolhido ----
    // <div data-quadro-horarios> na página. Clicar num horário preenche dentista e horário no formulário.
    const quadro = document.querySelector('[data-quadro-horarios]');
    const lista = quadro?.querySelector('[data-quadro-lista]');
    const rotuloData = quadro?.querySelector('[data-quadro-data]');
    const aviso = quadro?.querySelector('[data-quadro-aviso]');
    let pedidoQuadro = 0;
    const el = (tag, classe, texto) => {
      const no = document.createElement(tag);
      if (classe) no.className = classe;
      if (texto !== undefined) no.textContent = texto;
      return no;
    };
    const dataPorExtenso = (iso) => {
      const texto = new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR', {weekday: 'long', day: '2-digit', month: 'long'});
      return texto.charAt(0).toUpperCase() + texto.slice(1);
    };
    function marcarEscolhido() {
      if (!lista) return;
      const dentista = campo('dentista')?.value || '';
      for (const botao of lista.querySelectorAll('.horario-livre')) {
        const escolhido = botao.dataset.dentista === dentista && botao.dataset.hora === horario.value && !horario.disabled;
        botao.setAttribute('aria-pressed', escolhido ? 'true' : 'false');
      }
    }
    async function atualizarQuadro() {
      if (!quadro || !lista) return;
      const data = campo('data')?.value;
      if (!data) return;
      const atual = ++pedidoQuadro;
      const url = new URL('api_horarios.php', window.location.href);
      url.searchParams.set('quadro', '1');
      url.searchParams.set('data', data);
      url.searchParams.set('procedimento', campo('procedimento')?.value || '');
      url.searchParams.set('paciente_id', campo('paciente_id')?.value || '0');
      if (rotuloData) rotuloData.textContent = dataPorExtenso(data);
      quadro.setAttribute('aria-busy', 'true');
      try {
        const resposta = await fetch(url, {credentials: 'same-origin'});
        const corpo = await resposta.json().catch(() => ({}));
        if (atual !== pedidoQuadro) return;
        if (!resposta.ok) {
          lista.replaceChildren(el('p', 'quadro-vazio', corpo.erro || 'Não foi possível consultar os horários. Recarregue a página.'));
          if (aviso) aviso.textContent = '';
          return;
        }
        if (aviso) aviso.textContent = corpo.aviso || '';
        const linhas = (corpo.dentistas || []).map((d) => {
          const linha = el('div', 'quadro-dentista');
          const quem = el('div', 'quadro-quem');
          quem.append(el('strong', '', d.nome), el('span', '', d.especialidade + ' · ' + (d.consultas === 1 ? '1 consulta no dia' : d.consultas + ' consultas no dia')));
          const horas = el('div', 'quadro-horas');
          if (!d.atende) horas.append(el('span', 'quadro-vazio', 'Não realiza o procedimento escolhido.'));
          else if (!d.horarios.length) horas.append(el('span', 'quadro-vazio', 'Sem horário livre neste dia.'));
          else for (const hora of d.horarios) {
            const botao = el('button', 'horario-livre', hora);
            botao.type = 'button';
            botao.dataset.dentista = String(d.id);
            botao.dataset.hora = hora;
            botao.setAttribute('aria-pressed', 'false');
            botao.setAttribute('aria-label', hora + ' com ' + d.nome);
            horas.append(botao);
          }
          linha.append(quem, horas);
          return linha;
        });
        lista.replaceChildren(...(linhas.length ? linhas : [el('p', 'quadro-vazio', 'Nenhum dentista ativo cadastrado.')]));
        marcarEscolhido();
      } catch (erro) {
        if (atual !== pedidoQuadro) return;
        lista.replaceChildren(el('p', 'quadro-vazio', 'Não foi possível consultar os horários. Recarregue a página.'));
      } finally {
        if (atual === pedidoQuadro) quadro.removeAttribute('aria-busy');
      }
    }
    if (quadro && lista) {
      lista.addEventListener('click', async (e) => {
        const botao = e.target.closest('.horario-livre');
        if (!botao) return;
        const dentista = campo('dentista');
        if (!campo('procedimento')?.value) {
          if (aviso) aviso.textContent = 'Escolha o procedimento no formulário para marcar este horário.';
          campo('procedimento')?.focus();
          return;
        }
        if (dentista) dentista.value = botao.dataset.dentista;
        await atualizar();
        if ([...horario.options].some(o => o.value === botao.dataset.hora)) horario.value = botao.dataset.hora;
        marcarEscolhido();
        if (aviso) aviso.textContent = horario.value === botao.dataset.hora
          ? 'Selecionado: ' + botao.getAttribute('aria-label') + ', ' + dataPorExtenso(campo('data').value).toLowerCase() + '. Confira e confirme no formulário.'
          : 'Esse horário acabou de ser ocupado. Escolha outro.';
      });
      for (const seta of quadro.querySelectorAll('[data-quadro-dia]')) {
        seta.addEventListener('click', () => {
          const data = campo('data');
          if (!data?.value) return;
          const dia = new Date(data.value + 'T12:00:00');
          dia.setDate(dia.getDate() + Number(seta.dataset.quadroDia));
          const novo = dia.toISOString().slice(0, 10);
          if (data.min && novo < data.min) return;
          data.value = novo;
          data.dispatchEvent(new Event('change', {bubbles: true}));
        });
      }
      horario.addEventListener('change', marcarEscolhido);
    }

    for (const nome of ['data', 'procedimento', 'dentista', 'paciente_id']) {
      campo(nome)?.addEventListener('change', () => { atualizar().then(marcarEscolhido); });
    }
    for (const nome of ['data', 'procedimento', 'paciente_id']) {
      campo(nome)?.addEventListener('change', atualizarQuadro);
    }
    atualizar().then(marcarEscolhido);
    atualizarQuadro();
  }
})();
