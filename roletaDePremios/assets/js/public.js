/**
 * ============================================================
 *  DON SPIN — public.js
 *  Lógica da Roleta Pública.
 *
 *  ✅ Carrega configurações via GET /api/config.php
 *  ✅ Carrega prêmios via GET /api/prizes.php
 *  ✅ Sorteio via POST /api/spin.php (SERVIDOR decide o vencedor)
 *  ✅ Token CSRF vem do PHP (meta tag) — sem localStorage na lógica crítica
 *  ❌ Nenhuma lógica de sorteio no frontend
 *  ❌ Sem localStorage para autenticação ou limite de giros
 * ============================================================
 */

// ── Estado da aplicação ──────────────────────────────────────
let cfg = {};
let premios = [];
let rotacaoAtual = 0;
let girando = false;

// ── Inicialização ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    await carregarDados();
    window.addEventListener('resize', () => {
        const canvas = document.getElementById('pub-canvas');
        if (canvas) drawWheel(canvas, rotacaoAtual, premios, cfg);
    });
});

/**
 * Carrega configuração e prêmios do backend.
 * Sem localStorage — dados sempre vêm do servidor.
 */
async function carregarDados() {
    try {
        const [resConfig, resPremios] = await Promise.all([
            fetch(BASE_URL + '/api/config.php'),
            fetch(BASE_URL + '/api/prizes.php'),
        ]);

        const dadosConfig = await resConfig.json();
        const dadosPremios = await resPremios.json();

        if (dadosConfig.success) cfg = dadosConfig.config;
        if (dadosPremios.success) premios = dadosPremios.premios;

        aplicarEstilosGlobais();
        aplicarTextoPublico();

        const canvas = document.getElementById('pub-canvas');
        drawWheel(canvas, 0, premios, cfg);

        atualizarBotaoGirar();

    } catch (err) {
        console.error('Erro ao carregar dados:', err);
    }
}

/**
 * Aplica variáveis CSS de cor vindas do backend.
 */
function aplicarEstilosGlobais() {
    const root = document.documentElement;
    if (cfg.background) root.style.setProperty('--bg', cfg.background);
    if (cfg.cardColor) root.style.setProperty('--surface', cfg.cardColor);
    if (cfg.cardBorderColor) root.style.setProperty('--border', cfg.cardBorderColor);
    if (cfg.pointerColor) root.style.setProperty('--pointer-color', cfg.pointerColor);
    if (cfg.textColor) root.style.setProperty('--text', cfg.textColor);
    if (cfg.accentColor) root.style.setProperty('--accent', cfg.accentColor);
}

/**
 * Preenche título, subtítulo e logo na página pública.
 */
function aplicarTextoPublico() {
    const titulo = document.getElementById('pub-title');
    const msg = document.getElementById('pub-message');
    const logo = document.getElementById('pub-logo');

    if (titulo) titulo.textContent = cfg.title || 'Gira e Ganha!';
    if (msg) msg.textContent = cfg.message || 'Tente a sua sorte!';

    if (logo) {
        if (cfg.loginLogo) {
            logo.src = cfg.loginLogo;
            logo.classList.remove('hidden');
        } else {
            logo.classList.add('hidden');
        }
    }

    // Redesenha o canvas após aplicar configs (tamanho correto)
    setTimeout(() => {
        const canvas = document.getElementById('pub-canvas');
        drawWheel(canvas, rotacaoAtual, premios, cfg);
    }, 50);
}

/**
 * Atualiza o estado do botão "Girar" baseado nas configs do servidor.
 * Não usa localStorage para isso.
 */
function atualizarBotaoGirar() {
    const btn = document.getElementById('spin-btn');
    const msgLock = document.getElementById('spin-disabled-msg');
    const msgLmt = document.getElementById('spin-limit-msg');

    if (!btn) return;

    if (cfg.locked) {
        btn.disabled = true;
        if (msgLock) msgLock.classList.remove('hidden');
        if (msgLmt) msgLmt.classList.add('hidden');
        return;
    }

    // Ativa botão — limite de giros é verificado NO SERVIDOR quando clicar
    btn.disabled = false;
    btn.onclick = girarRoleta;
    if (msgLock) msgLock.classList.add('hidden');
    if (msgLmt) msgLmt.classList.add('hidden');
}

/**
 * Chama o backend para sortear o prêmio.
 * O frontend NUNCA calcula quem ganhou — apenas anima o resultado.
 */
async function girarRoleta() {
    if (girando || premios.length === 0) return;

    const btn = document.getElementById('spin-btn');
    girando = true;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Girando…'; }

    try {
        // Envia requisição ao backend com token CSRF
        const res = await fetch(BASE_URL + '/api/spin.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': CSRF_TOKEN, // Token gerado pelo PHP na página
            },
        });

        const data = await res.json();

        if (!data.success) {
            // Roleta bloqueada ou limite atingido
            girando = false;
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-rotate-right mr-2"></i>GIRAR'; }

            if (data.limit_reached) {
                btn.disabled = true;
                const msgLmt = document.getElementById('spin-limit-msg');
                if (msgLmt) msgLmt.classList.remove('hidden');
            } else if (res.status === 403) {
                // Roleta bloqueada
                const msgLock = document.getElementById('spin-disabled-msg');
                if (msgLock) msgLock.classList.remove('hidden');
                if (btn) btn.disabled = true;
            }
            return;
        }

        // ── O servidor retornou o índice vencedor ────────────
        // O frontend apenas anima até esse índice
        const indiceVencedor = data.winner_index;
        animarRoleta(indiceVencedor, data);

    } catch (err) {
        console.error('Erro ao girar:', err);
        girando = false;
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-rotate-right mr-2"></i>GIRAR'; }
    }
}

/**
 * Anima a roleta até parar na fatia sorteada PELO SERVIDOR.
 *
 * @param {number} indice - Índice da fatia vencedora (retornado pela API)
 * @param {Object} resultado - Dados do prêmio para exibir no modal
 */
function animarRoleta(indice, resultado) {
    const canvas = document.getElementById('pub-canvas');
    const sweep = (Math.PI * 2) / premios.length;

    // Calcula ângulo do meio da fatia vencedora
    const midAngle = indice * sweep + sweep / 2;
    const alignAngle = -midAngle - Math.PI / 2;

    // Jitter visual pequeno (apenas estético — não muda o resultado)
    const jitter = (Math.random() * 2 - 1) * (sweep / 2) * 0.35;
    const voltas = Math.PI * 2 * (8 + Math.floor(Math.random() * 5));
    const target = rotacaoAtual + voltas + alignAngle + jitter - (rotacaoAtual % (Math.PI * 2));

    const duration = 4500;
    const startTime = performance.now();
    const startRot = rotacaoAtual;

    function easeOut(t) { return 1 - Math.pow(1 - t, 4); }

    function animate(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        rotacaoAtual = startRot + (target - startRot) * easeOut(progress);
        drawWheel(canvas, rotacaoAtual, premios, cfg);

        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            girando = false;
            exibirResultado(resultado);

            // Atualiza botão após giro
            const btn = document.getElementById('spin-btn');
            if (resultado.giros_restantes === 0) {
                if (btn) btn.disabled = true;
                const msgLmt = document.getElementById('spin-limit-msg');
                if (msgLmt) msgLmt.classList.remove('hidden');
            } else {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-rotate-right mr-2"></i>GIRAR';
                }
            }
        }
    }

    requestAnimationFrame(animate);
}

// ── Modal de Resultado ───────────────────────────────────────
const mensagensMotivacionais = [
    'Não desista agora, o próximo giro pode ser o seu!',
    'A sorte grande está chegando, continue tentando!',
    'Quase lá! Respire fundo e tente novamente.',
    'Dizem que a sorte favorece os persistentes...',
    'A persistência é o caminho do êxito!',
];

/**
 * Exibe o modal com o resultado do giro.
 * Dados vêm do servidor — nunca calculados no frontend.
 */
function exibirResultado(data) {
    const modal = document.getElementById('result-modal');
    const icon = document.getElementById('modal-icon');
    const titulo = document.getElementById('modal-title');
    const msg = document.getElementById('modal-msg');
    const prizeBox = document.getElementById('modal-prize-box');
    const prizeText = document.getElementById('modal-prize-text');
    const couponBox = document.getElementById('modal-coupon-box');
    const couponCode = document.getElementById('modal-coupon-code');
    const couponUrl = document.getElementById('modal-coupon-url');
    const closeBtn = document.getElementById('modal-close-btn');

    const ganhou = data.ganhou;

    icon.textContent = ganhou ? '🎉' : '😅';

    if (ganhou) {
        titulo.textContent = cfg.winTitle || 'Parabéns!';
        msg.textContent = cfg.winMessage || 'Você ganhou!';

        if (typeof confetti === 'function') {
            confetti({
                particleCount: 150,
                spread: 80,
                origin: { y: 0.6 },
                colors: [cfg.accentColor || '#6366F1', cfg.pointerColor || '#EC4899', '#ffffff'],
            });
        }
    } else {
        titulo.textContent = cfg.loseTitle || 'Quase lá!';
        // Mensagem motivacional aleatória se for padrão
        let loseMsg = cfg.loseMessage || '';
        if (!loseMsg || loseMsg === 'Não foi desta vez. Boa sorte na próxima!') {
            loseMsg = mensagensMotivacionais[Math.floor(Math.random() * mensagensMotivacionais.length)];
        }
        msg.textContent = loseMsg;
    }

    closeBtn.textContent = cfg.popupBtnText || 'Fechar';

    // Reseta visibilidade
    prizeBox.classList.add('hidden');
    couponBox.classList.add('hidden');
    if (couponUrl) { couponUrl.classList.add('hidden'); couponUrl.classList.remove('flex'); }

    if (ganhou && data.texto) {
        prizeBox.classList.remove('hidden');
        prizeText.textContent = data.texto;

        if (data.valor) {
            couponBox.classList.remove('hidden');
            couponBox.classList.add('flex');
            couponCode.value = data.valor;

            if (data.url) {
                couponUrl.href = data.url;
                couponUrl.classList.remove('hidden');
                couponUrl.classList.add('flex');
            }
        }
    }

    modal.classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('result-modal').classList.add('hidden');
}

function copiarCupom() {
    const code = document.getElementById('modal-coupon-code').value;
    if (!code) return;
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById('copy-coupon-btn');
        if (btn) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-copy"></i>'; }, 2000);
        }
    });
}
