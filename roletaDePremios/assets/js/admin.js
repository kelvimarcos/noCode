/**
 * ============================================================
 *  DON SPIN — admin.js
 *  Lógica do Painel Administrativo.
 *
 *  ✅ Carrega dados via API (sem localStorage)
 *  ✅ Salva configs via POST autenticado com CSRF
 *  ✅ CRUD de prêmios via API
 *  ✅ Métricas reais do banco MySQL
 *  ❌ Sem localStorage para lógica crítica
 * ============================================================
 */

// ── Estado local (apenas para UI, não para lógica crítica) ──
let cfg = {};      // Config carregada do servidor
let premios = [];     // Prêmios carregados do servidor
let locked = false;  // Estado de bloqueio (do servidor)

// ── Inicialização ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    mostrarSkeletonAdmin();
    await carregarTudo();
    mudarTab('roleta');
    // Link público
    const publicLink = `${location.origin}${BASE_URL}/public.php`;
    const el = document.getElementById('public-link-field');
    if (el) el.value = publicLink;
});

/**
 * Exibe skeleton loader enquanto os dados carregam.
 */
function mostrarSkeletonAdmin() {
    const canvas = document.getElementById('admin-canvas');
    if (canvas && canvas.parentElement) {
        canvas.parentElement.classList.add('skeleton', 'skeleton-circle');
    }
}

function ocultarSkeletonAdmin() {
    const canvas = document.getElementById('admin-canvas');
    if (canvas && canvas.parentElement) {
        canvas.parentElement.classList.remove('skeleton', 'skeleton-circle');
    }
}

/**
 * Carrega configurações e prêmios do servidor.
 */
async function carregarTudo() {
    try {
        const [resConfig, resPremios] = await Promise.all([
            fetch(BASE_URL + '/api/config.php'),
            fetch(BASE_URL + '/api/prizes.php'),
        ]);
        const dadosConfig = await resConfig.json();
        const dadosPremios = await resPremios.json();

        if (dadosConfig.success) {
            cfg = dadosConfig.config;
            locked = cfg.locked;
        }
        if (dadosPremios.success) {
            premios = dadosPremios.premios;
        }

        aplicarEstilosGlobais();
        carregarInputsAdmin();
        renderizarPremios();
        atualizarLockUI();
        ocultarSkeletonAdmin();
        setTimeout(() => drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg), 50);
    } catch (err) {
        console.error('Erro ao carregar dados:', err);
        ocultarSkeletonAdmin();
        mostrarToast('Erro ao conectar com o servidor.', 'error');
    }
}

/**
 * Aplica variáveis CSS de cores vindas da API.
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
 * Preenche os inputs do admin com os valores vindos do banco.
 */
function carregarInputsAdmin() {
    const map = [
        ['c-loginLogo', 'loginLogo'], ['c-logo', 'logo'],
        ['c-background', 'background'], ['c-cardColor', 'cardColor'],
        ['c-cardBorderColor', 'cardBorderColor'], ['c-pointerColor', 'pointerColor'],
        ['c-textColor', 'textColor'], ['c-accentColor', 'accentColor'],
        ['c-loginBgColor', 'loginBgColor'], ['c-loginCardColor', 'loginCardColor'],
        ['c-title', 'title'], ['c-message', 'message'],
        ['c-winTitle', 'winTitle'], ['c-winMessage', 'winMessage'],
        ['c-loseTitle', 'loseTitle'], ['c-loseMessage', 'loseMessage'],
        ['c-popupBtnText', 'popupBtnText'], ['c-spinLimit', 'spinLimit'],
    ];
    map.forEach(([id, key]) => {
        const el = document.getElementById(id);
        if (el && cfg[key] !== undefined) el.value = cfg[key];
    });
}

// ── Tabs ─────────────────────────────────────────────────────
function mudarTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.admin-tab').forEach(btn => btn.classList.remove('active'));

    const tabEl = document.getElementById(`tab-${tab}`);
    if (tabEl) { tabEl.classList.remove('hidden'); tabEl.classList.add('flex'); }

    const btn = document.querySelector(`[data-tab="${tab}"]`);
    if (btn) btn.classList.add('active');

    if (tab === 'metricas') carregarMetricas();
    if (tab === 'roleta') setTimeout(() => drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg), 50);
}

// ── Atualização de config (preview em tempo real) ────────────
function atualizarConfig(key, val) {
    cfg[key] = val;
    // Aplica preview visual imediato (sem salvar)
    const root = document.documentElement;
    if (key === 'background') root.style.setProperty('--bg', val);
    if (key === 'cardColor') root.style.setProperty('--surface', val);
    if (key === 'cardBorderColor') root.style.setProperty('--border', val);
    if (key === 'pointerColor') root.style.setProperty('--pointer-color', val);
    if (key === 'textColor') root.style.setProperty('--text', val);
    if (key === 'accentColor') root.style.setProperty('--accent', val);
    // Redesenha preview
    drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg);
}

// ── Salvar Aparência (POST para API) ─────────────────────────
async function salvarAparencia() {
    const campos = [
        'title', 'message', 'winTitle', 'winMessage', 'loseTitle', 'loseMessage',
        'popupBtnText', 'spinLimit', 'background', 'cardColor', 'cardBorderColor',
        'textColor', 'accentColor', 'pointerColor', 'loginBgColor', 'loginCardColor',
        'logo', 'loginLogo',
    ];

    const form = new FormData();
    form.append('csrf_token', CSRF_TOKEN);
    campos.forEach(c => {
        if (cfg[c] !== undefined) form.append(c, cfg[c]);
    });

    try {
        const res = await fetch(BASE_URL + '/api/config.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            mostrarToast('Aparência salva com sucesso!', 'success');
        } else {
            mostrarToast(data.error || 'Erro ao salvar.', 'error');
        }
    } catch {
        mostrarToast('Erro de conexão.', 'error');
    }
}

// ── Controle de Bloqueio ─────────────────────────────────────
async function alternarBloqueio() {
    locked = !locked;

    // Salva no servidor imediatamente
    const form = new FormData();
    form.append('csrf_token', CSRF_TOKEN);
    form.append('locked', locked ? '1' : '0');

    try {
        await fetch(BASE_URL + '/api/config.php', { method: 'POST', body: form });
        cfg.locked = locked;
        atualizarLockUI();
    } catch {
        locked = !locked; // Reverte se falhar
        mostrarToast('Erro ao alterar bloqueio.', 'error');
    }
}

function atualizarLockUI() {
    const btn = document.getElementById('lockBtn');
    const badge = document.getElementById('admin-lock-badge');
    if (!btn) return;

    if (locked) {
        btn.innerHTML = '<i class="fa-solid fa-unlock mr-1.5"></i>Desbloquear';
        btn.className = 'btn btn-success text-xs py-2 px-4';
        if (badge) { badge.className = 'badge badge-red'; badge.innerHTML = '<i class="fa-solid fa-lock mr-1"></i>Bloqueado'; }
    } else {
        btn.innerHTML = '<i class="fa-solid fa-lock mr-1.5"></i>Bloquear';
        btn.className = 'btn btn-ghost text-xs py-2 px-4';
        if (badge) { badge.className = 'badge badge-green'; badge.innerHTML = '<i class="fa-solid fa-unlock mr-1"></i>Desbloqueado'; }
    }
}

// ── Limit de Giros (salva config via API) ───────────────────
document.addEventListener('change', e => {
    if (e.target && e.target.id === 'c-spinLimit') {
        atualizarConfig('spinLimit', e.target.value);
        // Auto-salva o spinLimit ao alterar
        const form = new FormData();
        form.append('csrf_token', CSRF_TOKEN);
        form.append('spinLimit', e.target.value);
        fetch(BASE_URL + '/api/config.php', { method: 'POST', body: form });
    }
});

// ── PRÊMIOS ──────────────────────────────────────────────────
function renderizarPremios() {
    const container = document.getElementById('slices-list');
    if (!container) return;
    container.innerHTML = '';

    premios.forEach((s) => {
        const div = document.createElement('div');
        div.className = 'card-sm p-4';
        div.dataset.id = s.id;
        div.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
          <div class="md:col-span-2">
            <label class="field-label"><i class="fa-solid fa-tag mr-1"></i>Tipo</label>
            <select class="admin-input" data-field="tipo" onchange="atualizarPremioLocal(${s.id},'tipo',this.value)">
              <option value="premio"     ${s.tipo === 'premio' ? 'selected' : ''}>Prêmio</option>
              <option value="cupom"      ${s.tipo === 'cupom' ? 'selected' : ''}>Cupom</option>
              <option value="sem-premio" ${s.tipo === 'sem-premio' ? 'selected' : ''}>Sem Prêmio</option>
            </select>
          </div>
          <div class="md:col-span-3">
            <label class="field-label"><i class="fa-solid fa-font mr-1"></i>Texto da Roda</label>
            <input type="text" class="admin-input" value="${escaparHTML(s.texto)}" oninput="atualizarPremioLocal(${s.id},'texto',this.value)" placeholder="R$ 50 OFF">
          </div>
          <div class="md:col-span-3">
            <label class="field-label"><i class="fa-solid fa-gift mr-1"></i>Valor / Cupom</label>
            <input type="text" class="admin-input" value="${escaparHTML(s.valor || '')}"
              oninput="atualizarPremioLocal(${s.id},'valor',this.value)"
              placeholder="${s.tipo === 'sem-premio' ? '-' : 'Código ou detalhe'}"
              ${s.tipo === 'sem-premio' ? 'disabled' : ''}>
          </div>
          <div class="md:col-span-2">
            <label class="field-label"><i class="fa-solid fa-percent mr-1"></i>Prob. (%)</label>
            <input type="number" class="admin-input font-mono" value="${s.probabilidade}"
              oninput="atualizarPremioLocal(${s.id},'probabilidade',this.value)" step="0.01" min="0.01" max="100">
          </div>
          <div class="md:col-span-1">
            <label class="field-label"><i class="fa-solid fa-palette mr-1"></i>Cor</label>
            <input type="color" class="admin-input w-full p-1 h-[42px]" value="${escaparHTML(s.cor || '#6366F1')}"
              oninput="atualizarPremioLocal(${s.id},'cor',this.value)">
          </div>
          <div class="md:col-span-1 flex items-end">
            <button onclick="removerPremio(${s.id})" class="btn btn-danger w-full py-2.5 text-xs">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </div>
        ${s.tipo !== 'sem-premio' ? `
        <div class="mt-3 pt-3 border-t" style="border-color:var(--border)">
          <label class="field-label"><i class="fa-solid fa-link mr-1"></i>Link do Produto (Opcional)</label>
          <input type="url" class="admin-input" value="${escaparHTML(s.url || '')}"
            oninput="atualizarPremioLocal(${s.id},'url',this.value)" placeholder="https://seusite.com/produto">
        </div>` : ''}
        `;
        container.appendChild(div);
    });
}

function atualizarPremioLocal(id, campo, valor) {
    const s = premios.find(p => p.id === id);
    if (!s) return;
    s[campo] = campo === 'probabilidade' ? parseFloat(valor) || 0 : valor;
    // Preview imediato no canvas
    drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg);
    // Rerenderiza se mudou o tipo (para habilitar/desabilitar campos)
    if (campo === 'tipo') renderizarPremios();
}

function adicionarPremio() {
    // Adiciona localmente — será salvo ao clicar "Salvar Prêmios"
    const novoId = -(Date.now()); // ID negativo = ainda não está no banco
    premios.push({
        id: novoId, tipo: 'premio', texto: 'Novo Prêmio',
        valor: '', url: '', probabilidade: 10, cor: '#7c3aed', ordem: premios.length,
    });
    renderizarPremios();
    drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg);
}

async function removerPremio(id) {
    if (premios.length <= 2) {
        mostrarToast('A roleta precisa ter pelo menos 2 fatias.', 'error');
        return;
    }

    // Se o prêmio ainda não foi salvo (id negativo), remove apenas localmente
    if (id < 0) {
        premios = premios.filter(p => p.id !== id);
        renderizarPremios();
        drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg);
        return;
    }

    try {
        const res = await fetch(`${BASE_URL}/api/prizes.php?id=${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
        });
        const data = await res.json();
        if (data.success) {
            premios = premios.filter(p => p.id !== id);
            renderizarPremios();
            drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg);
            mostrarToast('Prêmio removido.', 'success');
        } else {
            mostrarToast(data.error || 'Erro ao remover.', 'error');
        }
    } catch {
        mostrarToast('Erro de conexão.', 'error');
    }
}

async function salvarTodosPremios() {
    try {
        for (const s of premios) {
            const form = new FormData();
            form.append('csrf_token', CSRF_TOKEN);
            form.append('tipo', s.tipo);
            form.append('texto', s.texto);
            form.append('valor', s.valor || '');
            form.append('url', s.url || '');
            form.append('probabilidade', s.probabilidade);
            form.append('cor', s.cor);
            form.append('ordem', s.ordem || 0);

            // Se ID positivo, envia para atualizar; se negativo, cria novo
            if (s.id > 0) form.append('id', s.id);

            const res = await fetch(BASE_URL + '/api/prizes.php', { method: 'POST', body: form });
            const data = await res.json();

            // Atualiza o ID local se era novo (negativo)
            if (data.success && s.id < 0) s.id = data.id;
        }

        mostrarToast('Prêmios salvos com sucesso!', 'success');
        // Recarrega do servidor para sincronizar IDs
        const resPremios = await fetch(BASE_URL + '/api/prizes.php');
        const dadosPremios = await resPremios.json();
        if (dadosPremios.success) { premios = dadosPremios.premios; renderizarPremios(); }

    } catch {
        mostrarToast('Erro ao salvar prêmios.', 'error');
    }
}

// ── Upload de Imagem ─────────────────────────────────────────
async function fazerUploadImagem(input, campo) {
    const file = input.files[0];
    if (!file) return;

    const form = new FormData();
    form.append('file', file);
    form.append('campo', campo);
    form.append('csrf_token', CSRF_TOKEN);

    try {
        const res = await fetch(BASE_URL + '/api/upload.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            cfg[campo] = data.url;
            const inputUrl = document.getElementById(`c-${campo}`);
            if (inputUrl) inputUrl.value = data.url;
            drawWheel(document.getElementById('admin-canvas'), 0, premios, cfg);
            mostrarToast('Imagem enviada!', 'success');
        } else {
            mostrarToast(data.error || 'Erro no upload.', 'error');
        }
    } catch {
        mostrarToast('Erro de conexão.', 'error');
    }
}

// ── Métricas ─────────────────────────────────────────────────
async function carregarMetricas() {
    try {
        const res = await fetch(BASE_URL + '/api/metrics.php');
        const data = await res.json();
        if (!data.success) return;

        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('m-visits', data.visitas);
        set('m-spins', data.giros);
        set('m-wins', data.ganhos);
        set('m-rate', data.taxa + '%');

        const container = document.getElementById('ultimos-giros');
        if (container && data.ultimos) {
            container.innerHTML = data.ultimos.map(g => `
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                    <span>${escaparHTML(g.premio_texto)}</span>
                    <span style="color:${g.ganhou ? 'var(--green)' : 'var(--muted)'}">${g.ganhou ? '✓ Ganhou' : '✗ Perdeu'}</span>
                    <span style="font-size:0.7rem;opacity:0.5;">${g.girado_em}</span>
                </div>
            `).join('') || '<p style="opacity:0.4;">Nenhum giro registrado ainda.</p>';
        }
    } catch (err) {
        console.error('Erro ao carregar métricas:', err);
    }
}

async function resetarMetricas() {
    if (!confirm('Confirma o reset de todas as métricas?')) return;
    try {
        const res = await fetch(BASE_URL + '/api/metrics.php', {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
        });
        const data = await res.json();
        if (data.success) {
            mostrarToast('Métricas resetadas.', 'success');
            carregarMetricas();
        }
    } catch {
        mostrarToast('Erro ao resetar.', 'error');
    }
}

// ── Link Público ─────────────────────────────────────────────
function copiarLinkPublico() {
    const publicLink = `${location.origin}${BASE_URL}/public.php`;
    navigator.clipboard.writeText(publicLink).then(() => mostrarToast('Link copiado!', 'success'));
}

// ── Logout ───────────────────────────────────────────────────
async function fazerLogout() {
    await fetch(BASE_URL + '/api/logout.php', { method: 'POST' });
    window.location.href = BASE_URL + '/login.php';
}

// ── Helpers ──────────────────────────────────────────────────
function escaparHTML(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

let _toastTimer;
function mostrarToast(msg, tipo = 'success') {
    // Remove toast anterior se existir
    const old = document.getElementById('admin-toast');
    if (old) old.remove();

    const icons = { success: '✓', error: '✕' };

    const toast = document.createElement('div');
    toast.id        = 'admin-toast';
    toast.className = `toast toast--${tipo}`;
    toast.innerHTML = `<span style="font-size:1rem">${icons[tipo] || '•'}</span><span>${msg}</span>`;
    document.body.appendChild(toast);

    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => {
        toast.classList.add('toast--out');
        setTimeout(() => toast.remove(), 230);
    }, 3200);
}
