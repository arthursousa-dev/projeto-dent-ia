// Gráfico receita x despesas (barras) + lucro (linha) em SVG, redesenhado ao redimensionar e ao ligar/desligar séries.
(function () {
    const alvo=document.getElementById('grafico-financeiro'), fonte=document.getElementById('dados-grafico');
    if (!alvo || !fonte) return;
    const dados=JSON.parse(fonte.textContent), dica=document.getElementById('grafico-dica');
    const visivel={receita:true,despesas:true,lucro:true};
    const brl=v=>v.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
    const fmt=(v,u)=>(v<0?'-':'')+(Math.abs(v)/u).toLocaleString('pt-BR',{maximumFractionDigits:1});
    const curto=v=>Math.abs(v)>=1e6 ? fmt(v,1e6)+' mi' : Math.abs(v)>=1e3 ? fmt(v,1e3)+' mil' : String(Math.round(v));
    const ns='http://www.w3.org/2000/svg';
    const el=(tag,attrs,pai)=>{const e=document.createElementNS(ns,tag);for(const k in attrs)e.setAttribute(k,attrs[k]);pai&&pai.appendChild(e);return e;};
    // Escala "redonda" do eixo: 1, 2, 2,5 ou 5 vezes uma potência de 10.
    function passo(bruto){const p=Math.pow(10,Math.floor(Math.log10(bruto)));for(const m of [1,2,2.5,5,10])if(m*p>=bruto)return m*p;}
    function desenhar() {
        alvo.innerHTML='';
        const W=alvo.clientWidth||600, H=240, m={t:12,r:8,b:28,l:52};
        const series=['receita','despesas','lucro'].filter(s=>visivel[s]);
        const vals=dados.flatMap(d=>series.map(s=>d[s]));
        let max=Math.max(0,...vals), min=Math.min(0,...vals);
        if (max===min) max=1;
        const st=passo((max-min)/4); max=Math.ceil(max/st)*st; min=Math.floor(min/st)*st;
        const y=v=>m.t+(max-v)/(max-min)*(H-m.t-m.b), larg=(W-m.l-m.r)/dados.length;
        const svg=el('svg',{width:W,height:H,viewBox:`0 0 ${W} ${H}`,'aria-hidden':'true'},alvo);
        const foco=el('rect',{x:0,y:m.t,width:larg,height:H-m.t-m.b,class:'faixa-foco',visibility:'hidden'},svg);
        for (let v=min; v<=max+st/2; v+=st) {
            el('line',{x1:m.l,x2:W-m.r,y1:y(v),y2:y(v),class:Math.abs(v)<st/2?'eixo-zero':'grade'},svg);
            el('text',{x:m.l-6,y:y(v)+4,'text-anchor':'end',class:'eixo-texto'},svg).textContent=curto(v);
        }
        const barras=['receita','despesas'].filter(s=>visivel[s]);
        const bw=Math.max(2,Math.min(28,(larg*0.7-2*(barras.length-1))/Math.max(1,barras.length)));
        dados.forEach((d,i)=>{
            const cx=m.l+larg*i+larg/2, x0=cx-(barras.length*bw+2*(barras.length-1))/2;
            barras.forEach((s,j)=>{
                const h=Math.abs(y(d[s])-y(0)), r=Math.min(4,h,bw/2), x=x0+j*(bw+2), topo=y(Math.max(d[s],0));
                // Topo arredondado, base reta apoiada no eixo.
                if (h>=1) el('path',{class:'barra-fin '+s,d:`M${x},${topo+h}V${topo+r}q0,-${r} ${r},-${r}h${bw-2*r}q${r},0 ${r},${r}V${topo+h}Z`},svg);
            });
            const rot=d.mes.split('/'), ultimo=i===dados.length-1;
            el('text',{x:cx,y:H-10,'text-anchor':'middle',class:'eixo-texto'+(ultimo?' forte':'')},svg)
                .textContent=larg<34 && i%2 && !ultimo ? '' : rot[0]+((i===0||rot[0]==='Jan') && larg>=50 ? '/'+rot[1].slice(2) : '');
        });
        if (visivel.lucro) {
            const pts=dados.map((d,i)=>[m.l+larg*i+larg/2,y(d.lucro)]);
            el('polyline',{points:pts.map(p=>p.join(',')).join(' '),class:'linha-lucro'},svg);
            pts.forEach(([px,py],i)=>el('circle',{cx:px,cy:py,r:4,class:'ponto-lucro'+(dados[i].lucro<0?' negativo':'')},svg));
        }
        dados.forEach((d,i)=>{
            const area=el('rect',{x:m.l+larg*i,y:0,width:larg,height:H,fill:'transparent'},svg);
            area.addEventListener('mouseenter',()=>{
                foco.setAttribute('x',m.l+larg*i);foco.setAttribute('visibility','visible');
                dica.innerHTML=`<strong>${d.mes}</strong>`
                    +`<span><i class="legenda-cor receita"></i>Receita <b>${brl(d.receita)}</b></span>`
                    +`<span><i class="legenda-cor despesas"></i>Despesas <b>${brl(d.despesas)}</b></span>`
                    +`<span><i class="legenda-cor lucro"></i>${d.lucro<0?'Prejuízo':'Lucro'} <b>${brl(d.lucro)}</b></span>`;
                dica.hidden=false;
                const cx=alvo.offsetLeft+m.l+larg*i+larg/2;
                dica.style.left=(cx+dica.offsetWidth+16<alvo.offsetLeft+W ? cx+12 : cx-dica.offsetWidth-12)+'px';
            });
            area.addEventListener('mouseleave',()=>{foco.setAttribute('visibility','hidden');dica.hidden=true;});
        });
    }
    document.querySelectorAll('.legenda-item').forEach(b=>b.addEventListener('click',()=>{
        const s=b.dataset.serie;
        if (visivel[s] && Object.values(visivel).filter(Boolean).length===1) return; // sempre fica ao menos uma série
        visivel[s]=!visivel[s]; b.setAttribute('aria-pressed',String(visivel[s])); desenhar();
    }));
    new ResizeObserver(()=>desenhar()).observe(alvo);
})();
