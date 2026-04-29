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
 * @param {HTMLCanvasElement} canvas   - Elemento canvas alvo
 * @param {number}            rotOffset - Rotação atual em radianos
 * @param {Array}             slices   - Array de fatias [{texto, cor, ...}]
 * @param {Object}            config   - Config da roleta {logo, pointerColor, ...}
 */
function drawWheel(canvas, rotOffset, slices, config) {
    if (!canvas || !slices || slices.length === 0) return;

    config = config || {};

    const size = canvas.parentElement
        ? Math.min(canvas.parentElement.offsetWidth, 600)
        : 300;
    canvas.width = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d');
    const W = canvas.width;
    const cx = W / 2;
    const cy = W / 2;
    const R = W / 2 - 2;
    const sweep = (Math.PI * 2) / slices.length;

    ctx.clearRect(0, 0, W, W);

    slices.forEach((s, idx) => {
        const angle = (rotOffset - Math.PI / 2) + idx * sweep;

        // ── Fatia sólida (flat) ──────────────────────────────
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, R, angle, angle + sweep);
        ctx.closePath();
        ctx.fillStyle = s.cor || s.color || '#7c3aed';
        ctx.fill();

        // ── Separador de fatias ──────────────────────────────
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx + Math.cos(angle) * R, cy + Math.sin(angle) * R);
        ctx.strokeStyle = 'rgba(0,0,0,0.25)';
        ctx.lineWidth = 2;
        ctx.stroke();

        // ── Texto centrado na fatia ──────────────────────────
        ctx.save();
        const midAngle = angle + sweep / 2;
        const textDist = R * 0.63;
        const tx = cx + Math.cos(midAngle) * textDist;
        const ty = cy + Math.sin(midAngle) * textDist;
        ctx.translate(tx, ty);
        ctx.rotate(midAngle + Math.PI / 2);

        // Corrige texto de cabeça para baixo na metade inferior
        if (ty > cy) ctx.rotate(Math.PI);

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        const maxTextWidth = (R - (R * 0.63)) * 1.8;
        const maxTextHeight = (sweep * (R * 0.63)) * 0.8;

        let fontSize = Math.round(Math.max(12, Math.min(32, R * 0.15)));
        ctx.font = `800 ${fontSize}px Inter, sans-serif`;

        const label = s.texto || s.text || '';
        while (fontSize > 10 && (ctx.measureText(label).width > maxTextWidth || fontSize > maxTextHeight)) {
            fontSize -= 1;
            ctx.font = `800 ${fontSize}px Inter, sans-serif`;
        }

        let finalLabel = label;
        if (ctx.measureText(finalLabel).width > maxTextWidth) {
            while (ctx.measureText(finalLabel + '…').width > maxTextWidth && finalLabel.length > 2) {
                finalLabel = finalLabel.slice(0, -1);
            }
            finalLabel += '…';
        }

        // Contorno escuro para contraste
        ctx.lineJoin = 'round';
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.4)';
        ctx.lineWidth = Math.max(3, fontSize * 0.15);
        ctx.strokeText(finalLabel, 0, 0);

        ctx.fillStyle = '#ffffff';
        ctx.fillText(finalLabel, 0, 0);
        ctx.restore();
    });

    // ── Anel externo ─────────────────────────────────────────
    ctx.beginPath();
    ctx.arc(cx, cy, R, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(255,255,255,0.12)';
    ctx.lineWidth = 2;
    ctx.stroke();

    // ── Hub central ──────────────────────────────────────────
    const hubR = R * 0.14;
    const hubGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, hubR);
    hubGrad.addColorStop(0, '#ffffff');
    hubGrad.addColorStop(1, '#9ca3af');
    ctx.beginPath();
    ctx.arc(cx, cy, hubR, 0, Math.PI * 2);
    ctx.fillStyle = hubGrad;
    ctx.fill();

    // ── Logo no centro (se configurado) ──────────────────────
    if (config.logo) {
        const img = new Image();
        img.src = config.logo;
        img.onload = () => {
            const lr = hubR * 0.85;
            ctx.save();
            ctx.beginPath();
            ctx.arc(cx, cy, lr, 0, Math.PI * 2);
            ctx.clip();
            ctx.drawImage(img, cx - lr, cy - lr, lr * 2, lr * 2);
            ctx.restore();
        };
    }
}
