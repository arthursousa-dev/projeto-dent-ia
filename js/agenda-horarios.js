// Agendamento: o PostgreSQL define os horários realmente disponíveis.
(() => {
  for (const form of document.querySelectorAll('form[data-agendamento-dinamico]')) {
    const campo = (nome) => form.elements.namedItem(nome);
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
        const corpo = await resposta.json();
        if (!resposta.ok) throw new Error(corpo.erro || 'Falha ao consultar horários');
        if (atual !== requisicao) return;
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
    for (const nome of ['data', 'procedimento', 'dentista', 'paciente_id']) {
      campo(nome)?.addEventListener('change', atualizar);
    }
    atualizar();
  }
})();
