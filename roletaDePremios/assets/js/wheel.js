/**
 * ============================================================
 *  DON SPIN — wheel.js
 *  Lógica de desenho do Canvas da roleta.
 *  Pura renderização — sem lógica de sorteio, sem localStorage.
 *  Exporta: drawWheel(canvas, rotOffset, slices, config)
 * ============================================================
 */

/**
 * Desenha a roleta no canvas fornecido.
 *
 * @param {HTMLCanvasElement} canvas    - Elemento canvas alvo
 * @param {number}            rotOffset - Rotação atual em radianos
 * @param {Array}             slices    - Array de fatias [{texto|text, cor|color, ...}]
 * @param {Object}            config    - Config da roleta {logo, pointerColor, ...}
 */
function drawWheel(canvas, rotOffset, slices, config) {
    if (!canvas || !slices || slices.length === 0) return;

    config = config || {};

    // ── Tamanho real do canvas baseado no wrapper ─────────────
    const wrapper = canvas.parentElement;
    const size = wrapper ? Math.min(wrapper.offsetWidth, wrapper.offsetHeight, 600) : 300;
    canvas.width  = size;
    canvas.height = size;

    const ctx    = canvas.getContext('2d');
    const W      = canvas.width;
    const cx     = W / 2;
    const cy     = W / 2;
    const R      = W / 2 - 2;
    const n      = slices.length;
    const sweep  = (Math.PI * 2) / n;

    ctx.clearRect(0, 0, W, W);

    slices.forEach((s, idx) => {
        const angle = (rotOffset - Math.PI / 2) + idx * sweep;

        // ── Fatia sólida ──────────────────────────────────────
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, R, angle, angle + sweep);
        ctx.closePath();
        ctx.fillStyle = s.cor || s.color || '#7c3aed';
        ctx.fill();

        // ── Separador de fatias ───────────────────────────────
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx + Math.cos(angle) * R, cy + Math.sin(angle) * R);
        ctx.strokeStyle = 'rgba(0,0,0,0.30)';
        ctx.lineWidth   = Math.max(1.5, R * 0.005);
        ctx.stroke();

        // ── Texto centrado e adaptativo ───────────────────────
        _drawSliceText(ctx, s, idx, cx, cy, R, sweep, rotOffset, n);
    });

    // ── Anel externo ─────────────────────────────────────────
    ctx.beginPath();
    ctx.arc(cx, cy, R, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(255,255,255,0.15)';
    ctx.lineWidth   = Math.max(2, R * 0.006);
    ctx.stroke();

    // ── Hub central com gradiente ─────────────────────────────
    const hubR    = R * 0.13;
    const hubGrad = ctx.createRadialGradient(cx - hubR * 0.3, cy - hubR * 0.3, 0, cx, cy, hubR);
    hubGrad.addColorStop(0, '#ffffff');
    hubGrad.addColorStop(0.6, '#e2e8f0');
    hubGrad.addColorStop(1, '#94a3b8');
    ctx.beginPath();
    ctx.arc(cx, cy, hubR, 0, Math.PI * 2);
    ctx.fillStyle = hubGrad;
    ctx.fill();

    // Anel de brilho no hub
    ctx.beginPath();
    ctx.arc(cx, cy, hubR, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(255,255,255,0.5)';
    ctx.lineWidth   = 1.5;
    ctx.stroke();

    // ── Logo no centro (se configurado) ──────────────────────
    if (config.logo) {
        const img = new Image();
        img.src   = config.logo;
        img.onload = () => {
            const lr = hubR * 0.82;
            ctx.save();
            ctx.beginPath();
            ctx.arc(cx, cy, lr, 0, Math.PI * 2);
            ctx.clip();
            ctx.drawImage(img, cx - lr, cy - lr, lr * 2, lr * 2);
            ctx.restore();
        };
    }
}

/**
 * Desenha o texto de uma fatia dentro dos limites corretos.
 * Usa posição radial com rotação para ficar legível.
 *
 * Lógica de tamanho adaptativo:
 *  - maxW = comprimento disponível ao longo do arco (corda da fatia no raio do texto)
 *  - maxH = espessura radial disponível (anel de texto)
 *  - Reduz fontSize até caber em ambas as dimensões
 *  - Se ainda não couber na largura, trunca com reticências
 *  - Suporte a multi-linha para textos com espaço (quebre em 2 linhas se necessário)
 */
function _drawSliceText(ctx, s, idx, cx, cy, R, sweep, rotOffset, totalSlices) {
    const label = (s.texto || s.text || '').trim();
    if (!label) return;

    const midAngle  = (rotOffset - Math.PI / 2) + idx * sweep + sweep / 2;

    // ── Zona de texto: entre 35% e 88% do raio ────────────────
    // Dá espaço para o hub (13%) e para a borda
    const rInner    = R * 0.22;   // começa depois do hub
    const rOuter    = R * 0.88;   // termina antes da borda
    const textDist  = (rInner + rOuter) / 2; // ponto médio radial

    const tx = cx + Math.cos(midAngle) * textDist;
    const ty = cy + Math.sin(midAngle) * textDist;

    ctx.save();
    ctx.translate(tx, ty);
    ctx.rotate(midAngle + Math.PI / 2);

    // Inverte texto que ficaria de cabeça para baixo
    if (ty > cy) ctx.rotate(Math.PI);

    ctx.textAlign    = 'center';
    ctx.textBaseline = 'middle';

    // ── Limites disponíveis ───────────────────────────────────
    // Largura: corda da fatia no raio do texto × fator de segurança
    const chordWidth  = 2 * textDist * Math.sin(sweep / 2) * 0.80;
    // Altura: espessura do anel de texto × fator de segurança
    const radialDepth = (rOuter - rInner) * 0.75;

    // ── Tamanho inicial de fonte baseado no raio e nº de fatias ─
    // Escala inversamente com nº de fatias para manter legibilidade
    const scaleFactor = Math.max(0.4, 1 - (totalSlices - 2) * 0.055);
    let fontSize      = Math.round(Math.min(
        R * 0.14 * scaleFactor,   // proporcional ao raio
        chordWidth * 0.35,         // proporcional à corda
        radialDepth * 0.8,         // não maior que a espessura disponível
        28                         // teto absoluto
    ));
    fontSize = Math.max(fontSize, 8); // piso absoluto

    // ── Tenta renderizar em 1 ou 2 linhas ────────────────────
    const words     = label.split(' ');
    let lines       = [label];
    let finalSize   = fontSize;

    // Só tenta 2 linhas se houver mais de 1 palavra e fatia for grande o suficiente
    if (words.length > 1 && sweep > Math.PI / 6) {
        // Divide aproximadamente ao meio
        const mid  = Math.ceil(words.length / 2);
        const line1 = words.slice(0, mid).join(' ');
        const line2 = words.slice(mid).join(' ');
        lines = [line1, line2];
    }

    ctx.font = `800 ${fontSize}px Inter, system-ui, sans-serif`;

    // ── Reduz fonte até todas as linhas caberem em largura e altura ─
    let fits = false;
    while (fontSize >= 8 && !fits) {
        ctx.font = `800 ${fontSize}px Inter, system-ui, sans-serif`;
        const maxLineW = Math.max(...lines.map(l => ctx.measureText(l).width));
        const totalH   = fontSize * lines.length * 1.2; // line-height 1.2
        if (maxLineW <= chordWidth && totalH <= radialDepth) {
            fits = true;
        } else {
            fontSize--;
        }
    }
    finalSize = fontSize;

    // ── Trunca com reticências se ainda não couber ────────────
    lines = lines.map(line => {
        if (ctx.measureText(line).width <= chordWidth) return line;
        let trimmed = line;
        while (trimmed.length > 1 && ctx.measureText(trimmed + '…').width > chordWidth) {
            trimmed = trimmed.slice(0, -1);
        }
        return trimmed + '…';
    });

    // ── Desenha sombra/contorno para legibilidade ─────────────
    const lineH = finalSize * 1.25;
    const startY = lines.length > 1 ? -lineH / 2 : 0;

    lines.forEach((line, li) => {
        const y = startY + li * lineH;

        // Contorno
        ctx.lineJoin    = 'round';
        ctx.strokeStyle = 'rgba(0,0,0,0.55)';
        ctx.lineWidth   = Math.max(2.5, finalSize * 0.18);
        ctx.strokeText(line, 0, y);

        // Texto branco
        ctx.fillStyle = '#ffffff';
        ctx.fillText(line, 0, y);
    });

    ctx.restore();
}
