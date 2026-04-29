/* ====================================================
   SPIN & WIN  —  LÓGICA PRINCIPAL
   ==================================================== */

// ─── 1. CONFIGURAÇÃO PADRÃO ────────────────────────────
const DEFAULT_CONFIG = {
  loginLogo: '', loginBgColor: '#05040A', loginCardColor: '#0E0C1A',
  title: 'Gira e Ganha! 🎡', message: 'Tente a sua sorte e ganhe prêmios incríveis!',
  logo: '', background: '#0B0914',
  pointerColor: '#EC4899',
  cardColor: '#151226', cardBorderColor: 'rgba(255,255,255,0.06)',
  textColor: '#F8F9FA', accentColor: '#6366F1',
  winTitle: 'Parabéns! 🎉', winMessage: 'Você ganhou um prêmio incrível!',
  loseTitle: 'Quase lá! 😅', loseMessage: 'Não foi desta vez. Boa sorte na próxima!',
  popupBtnText: 'Fechar', spinLimit: 1,
  slices: [
    { id: 1, type: 'cupom', text: 'R$ 50 OFF', value: 'PROMO50', url: 'https://exemplo.com', probability: 20, color: '#6366F1' },
    { id: 2, type: 'premio', text: 'Frete Grátis', value: 'FRETEFREE', url: '', probability: 30, color: '#EC4899' },
    { id: 3, type: 'sem-premio', text: 'Tente Novamente', value: '', url: '', probability: 50, color: '#1E1A36' },
  ]
};

let cfg = JSON.parse(localStorage.getItem('sw_config') || 'null') || structuredClone(DEFAULT_CONFIG);

// Migration to new premium theme colors (if using old defaults)
if (cfg.background === '#0a0a0f') {
  cfg.background = '#0B0914';
  cfg.cardColor = '#151226';
  cfg.cardBorderColor = 'rgba(255,255,255,0.06)';
  cfg.pointerColor = '#EC4899';
  cfg.loginBgColor = '#05040A';
  cfg.loginCardColor = '#0E0C1A';
  cfg.textColor = '#F8F9FA';
  cfg.accentColor = '#6366F1';
  localStorage.setItem('sw_config', JSON.stringify(cfg));
}

let metrics = JSON.parse(localStorage.getItem('sw_metrics') || '{"spins":0,"wins":0,"views":0,"copies":0}');
let locked = localStorage.getItem('sw_locked') === '1';
let isSpinning = false;
let currentRotation = 0;
let isAdminAuth = sessionStorage.getItem('sw_admin_auth') === '1';
const isPublicView = window.location.search.includes('view=public');

// ─── 2. INICIALIZAÇÃO ─────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (isPublicView) {
    showScreen('public-view');
    applyPublicStyles();
    metrics.views++;
    saveMetrics();
    drawWheel(document.getElementById('pub-canvas'), 0, cfg.slices, cfg);
    updateSpinBtn();
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
  } else {
    if (isAdminAuth) {
      showScreen('admin-panel');
      initAdmin();
    } else {
      showScreen('login-screen');
    }
  }
});

function showScreen(id) {
  ['login-screen', 'register-screen', 'admin-panel', 'public-view'].forEach(s => {
    const el = document.getElementById(s);
    if (el) el.classList.add('hidden');
  });
  const target = document.getElementById(id);
  if (target) target.classList.remove('hidden');

  const root = document.documentElement;
  if (cfg.background) root.style.setProperty('--bg', cfg.background);
  if (cfg.cardColor) root.style.setProperty('--surface', cfg.cardColor);
  if (cfg.cardBorderColor) root.style.setProperty('--border', cfg.cardBorderColor);
  if (cfg.pointerColor) root.style.setProperty('--pointer-color', cfg.pointerColor);
  if (cfg.textColor) root.style.setProperty('--text', cfg.textColor);
  if (cfg.accentColor) root.style.setProperty('--accent', cfg.accentColor);

  if (id === 'login-screen' || id === 'register-screen') {
    if (cfg.loginBgColor) root.style.setProperty('--bg', cfg.loginBgColor);
    if (cfg.loginCardColor) root.style.setProperty('--surface', cfg.loginCardColor);

    const loginLogoImg = document.getElementById('login-logo-img');
    if (loginLogoImg) {
      if (cfg.loginLogo) {
        loginLogoImg.src = cfg.loginLogo;
        loginLogoImg.classList.remove('hidden');
      } else {
        loginLogoImg.classList.add('hidden');
      }
    }
  }
}

// ─── 3. LOGIN & REGISTO ───────────────────────────────
function handleLogin(e) {
  e.preventDefault();
  const email = document.getElementById('login-email').value.trim();
  const pass = document.getElementById('login-pass').value;
  const errEl = document.getElementById('login-error');

  // Verifica contas registadas no localStorage
  const accounts = JSON.parse(localStorage.getItem('sw_accounts') || '[]');
  // Compatibilidade com conta padrão admin/admin
  const defaultMatch = (email === 'admin' && pass === 'admin');
  const accountMatch = accounts.find(a => a.email === email && a.pass === pass);

  if (defaultMatch || accountMatch) {
    isAdminAuth = true;
    sessionStorage.setItem('sw_admin_auth', '1');
    errEl.classList.add('hidden');
    showScreen('admin-panel');
    initAdmin();
  } else {
    errEl.textContent = 'E-mail ou senha incorretos.';
    errEl.classList.remove('hidden');
  }
}

function handleRegister(e) {
  e.preventDefault();
  const name = document.getElementById('reg-name').value.trim();
  const email = document.getElementById('reg-email').value.trim();
  const pass = document.getElementById('reg-pass').value;
  const pass2 = document.getElementById('reg-pass2').value;
  const errEl = document.getElementById('register-error');
  const sucEl = document.getElementById('register-success');

  errEl.classList.add('hidden');
  sucEl.classList.add('hidden');

  if (pass.length < 6) {
    errEl.textContent = 'A senha deve ter pelo menos 6 caracteres.';
    errEl.classList.remove('hidden');
    return;
  }
  if (pass !== pass2) {
    errEl.textContent = 'As senhas não conferem.';
    errEl.classList.remove('hidden');
    return;
  }

  const accounts = JSON.parse(localStorage.getItem('sw_accounts') || '[]');
  if (accounts.find(a => a.email === email)) {
    errEl.textContent = 'Este e-mail já está cadastrado.';
    errEl.classList.remove('hidden');
    return;
  }

  accounts.push({ name, email, pass });
  localStorage.setItem('sw_accounts', JSON.stringify(accounts));
  sucEl.textContent = 'Conta criada com sucesso! Faça login agora.';
  sucEl.classList.remove('hidden');
  document.getElementById('register-form').reset();
  setTimeout(() => showScreen('login-screen'), 2000);
}

function showRegister() { showScreen('register-screen'); }
function showLogin() { showScreen('login-screen'); }

function logout() {
  isAdminAuth = false;
  sessionStorage.removeItem('sw_admin_auth');
  showScreen('login-screen');
}

// ─── 4. ADMIN — INICIALIZAÇÃO ─────────────────────────
function initAdmin() {
  switchTab('roleta');
  loadAdminInputs();
  drawWheel(document.getElementById('admin-canvas'), 0, cfg.slices, cfg);
  updateLockUI();
  // Link público
  const publicLink = `${location.origin}${location.pathname}?view=public`;
  const el = document.getElementById('public-link-field');
  if (el) el.value = publicLink;
}

function loadAdminInputs() {
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
  renderSlices();
}

// ─── 5. TABS ADMIN ────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
  document.querySelectorAll('.admin-tab').forEach(btn => btn.classList.remove('active'));
  const tabEl = document.getElementById(`tab-${tab}`);
  if (tabEl) {
    tabEl.classList.remove('hidden');
    tabEl.classList.add('flex');
  }
  const btn = document.querySelector(`[data-tab="${tab}"]`);
  if (btn) btn.classList.add('active');

  if (tab === 'metricas') updateMetricsUI();
  if (tab === 'roleta') {
    setTimeout(() => drawWheel(document.getElementById('admin-canvas'), 0, cfg.slices, cfg), 50);
  }
}

// ─── 6. CONFIGURAÇÃO ──────────────────────────────────
function c(key, val) {
  if (val !== undefined) {
    cfg[key] = val;
    localStorage.setItem('sw_config', JSON.stringify(cfg));
    drawWheel(document.getElementById('admin-canvas'), 0, cfg.slices, cfg);

    const root = document.documentElement;
    if (key === 'background') root.style.setProperty('--bg', val);
    if (key === 'cardColor') root.style.setProperty('--surface', val);
    if (key === 'cardBorderColor') root.style.setProperty('--border', val);
    if (key === 'pointerColor') root.style.setProperty('--pointer-color', val);
    if (key === 'textColor') root.style.setProperty('--text', val);
    if (key === 'accentColor') root.style.setProperty('--accent', val);
  }
  return cfg[key];
}

function imgUpload(input, key) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    c(key, e.target.result);
    const inp = document.getElementById(`c-${key}`);
    if (inp) inp.value = '';
  };
  reader.readAsDataURL(file);
}

// ─── 7. PRÊMIOS (SLICES) ──────────────────────────────
function renderSlices() {
  const container = document.getElementById('slices-list');
  if (!container) return;
  container.innerHTML = '';
  cfg.slices.forEach(s => {
    const div = document.createElement('div');
    div.className = 'card-sm p-4';
    div.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-2">
          <label class="field-label"><i class="fa-solid fa-tag mr-1"></i>Tipo</label>
          <select class="admin-input" onchange="updateSlice(${s.id},'type',this.value)">
            <option value="premio"     ${s.type === 'premio' ? 'selected' : ''}>Prêmio</option>
            <option value="cupom"      ${s.type === 'cupom' ? 'selected' : ''}>Cupom</option>
            <option value="sem-premio" ${s.type === 'sem-premio' ? 'selected' : ''}>Sem Prêmio</option>
          </select>
        </div>
        <div class="md:col-span-3">
          <label class="field-label"><i class="fa-solid fa-font mr-1"></i>Texto da Roda</label>
          <input type="text" class="admin-input" value="${s.text}" oninput="updateSlice(${s.id},'text',this.value)" placeholder="R$ 50 OFF" />
        </div>
        <div class="md:col-span-3">
          <label class="field-label"><i class="fa-solid fa-gift mr-1"></i>Valor / Cupom</label>
          <input type="text" class="admin-input" value="${s.value}" oninput="updateSlice(${s.id},'value',this.value)"
            placeholder="${s.type === 'sem-premio' ? '-' : 'Código ou detalhe'}" ${s.type === 'sem-premio' ? 'disabled' : ''} />
        </div>
        <div class="md:col-span-2">
          <label class="field-label"><i class="fa-solid fa-percent mr-1"></i>Prob. (%)</label>
          <input type="number" class="admin-input font-mono" value="${s.probability}" oninput="updateSlice(${s.id},'probability',this.value)" />
        </div>
        <div class="md:col-span-1">
          <label class="field-label"><i class="fa-solid fa-palette mr-1"></i>Cor</label>
          <input type="color" class="admin-input w-full p-1 h-[42px]" value="${s.color}" oninput="updateSlice(${s.id},'color',this.value)" />
        </div>
        <div class="md:col-span-1 flex items-end">
          <button onclick="removeSlice(${s.id})" class="btn btn-danger w-full py-2.5 text-xs">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </div>
      ${s.type !== 'sem-premio' ? `
      <div class="mt-3 pt-3 border-t" style="border-color:var(--border)">
        <label class="field-label"><i class="fa-solid fa-link mr-1"></i>Link do Produto (Opcional)</label>
        <input type="url" class="admin-input" value="${s.url || ''}" oninput="updateSlice(${s.id},'url',this.value)" placeholder="https://seusite.com/produto" />
      </div>` : ''}
    `;
    container.appendChild(div);
  });
}

function addSlice() {
  const id = Date.now();
  cfg.slices.push({ id, type: 'premio', text: 'Novo Prêmio', value: '', url: '', probability: 10, color: '#7c3aed' });
  saveCfg(); renderSlices();
  drawWheel(document.getElementById('admin-canvas'), 0, cfg.slices, cfg);
}

function removeSlice(id) {
  if (cfg.slices.length <= 2) return alert('A roleta precisa ter pelo menos 2 fatias.');
  cfg.slices = cfg.slices.filter(s => s.id !== id);
  saveCfg(); renderSlices();
  drawWheel(document.getElementById('admin-canvas'), 0, cfg.slices, cfg);
}

function updateSlice(id, key, val) {
  const s = cfg.slices.find(s => s.id === id);
  if (s) { s[key] = key === 'probability' ? parseFloat(val) || 0 : val; }
  saveCfg();
  drawWheel(document.getElementById('admin-canvas'), 0, cfg.slices, cfg);
  if (key === 'type') renderSlices();
}

function saveCfg() { localStorage.setItem('sw_config', JSON.stringify(cfg)); }

// ─── 8. LOCK / UNLOCK ─────────────────────────────────
function toggleLock() {
  locked = !locked;
  localStorage.setItem('sw_locked', locked ? '1' : '0');
  updateLockUI();
}

function updateLockUI() {
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

// ─── 9. LINK PÚBLICO ──────────────────────────────────
function copyPublicLink() {
  const publicLink = `${location.origin}${location.pathname}?view=public`;
  navigator.clipboard.writeText(publicLink).then(() => alert('Link copiado!'));
}

// ─── 10. CANVAS — DESENHO DA ROLETA ───────────────────
function drawWheel(canvas, rotOffset) {
  if (!canvas) return;
  const size = canvas.parentElement ? Math.min(canvas.parentElement.offsetWidth, 600) : 300;
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  const W = canvas.width;
  const cx = W / 2, cy = W / 2;
  const R = W / 2 - 2;
  const slices = cfg.slices;
  const sweep = (Math.PI * 2) / slices.length;

  ctx.clearRect(0, 0, W, W);

  slices.forEach((s, idx) => {
    const angle = (rotOffset - Math.PI / 2) + idx * sweep;

    // Fatia sólida (flat)
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, R, angle, angle + sweep);
    ctx.closePath();
    ctx.fillStyle = s.color || '#7c3aed';
    ctx.fill();

    // Separador entre fatias
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.lineTo(cx + Math.cos(angle) * R, cy + Math.sin(angle) * R);
    ctx.strokeStyle = 'rgba(0,0,0,0.25)';
    ctx.lineWidth = 2;
    ctx.stroke();

    // Texto sempre legível — posicionado no centro radial de cada fatia
    ctx.save();
    const midAngle = angle + sweep / 2;
    const textDist = R * 0.63;
    const tx = cx + Math.cos(midAngle) * textDist;
    const ty = cy + Math.sin(midAngle) * textDist;
    ctx.translate(tx, ty);

    // Rodar para ler ao longo da fatia (perpendicular ao raio)
    ctx.rotate(midAngle + Math.PI / 2);

    // Se o ponto de texto está na METADE INFERIOR do canvas (ty > cy), o texto ficaria
    // de cabeça para baixo — corrigir com flip de 180°
    if (ty > cy) {
      ctx.rotate(Math.PI);
    }

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    // Limites de tamanho baseados na posição e tamanho da fatia (textDist aproximado)
    const maxTextWidth = (R - (R * 0.63)) * 1.8; // ~37% do raio para cada lado a partir do centro do texto
    const maxTextHeight = (sweep * (R * 0.63)) * 0.8; // Altura baseada na largura da fatia naquele raio

    // Inicia com um tamanho máximo proporcional
    let fontSize = Math.round(Math.max(12, Math.min(32, R * 0.15)));
    ctx.font = `800 ${fontSize}px Inter, sans-serif`;

    // Reduz o tamanho da fonte até caber na fatia
    let label = s.text;
    while (fontSize > 10 && (ctx.measureText(label).width > maxTextWidth || fontSize > maxTextHeight)) {
      fontSize -= 1;
      ctx.font = `800 ${fontSize}px Inter, sans-serif`;
    }

    // Se atingiu o tamanho mínimo e ainda não cabe na largura, apara o texto (trim)
    let finalLabel = label;
    if (ctx.measureText(finalLabel).width > maxTextWidth) {
      while (ctx.measureText(finalLabel + '…').width > maxTextWidth && finalLabel.length > 2) {
        finalLabel = finalLabel.slice(0, -1);
      }
      finalLabel += '…';
    }

    // Desenhar contorno escuro sob o texto para máximo contraste e legibilidade
    ctx.lineJoin = 'round';
    ctx.strokeStyle = 'rgba(0, 0, 0, 0.4)';
    ctx.lineWidth = Math.max(3, fontSize * 0.15);
    ctx.strokeText(finalLabel, 0, 0);

    // Texto preenchido principal
    ctx.fillStyle = '#ffffff';
    ctx.fillText(finalLabel, 0, 0);
    ctx.restore();
  });

  // Anel externo fino
  ctx.beginPath();
  ctx.arc(cx, cy, R, 0, Math.PI * 2);
  ctx.strokeStyle = 'rgba(255,255,255,0.12)';
  ctx.lineWidth = 2;
  ctx.stroke();

  // Hub central (porca)
  const hubR = R * 0.14;
  const hubGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, hubR);
  hubGrad.addColorStop(0, '#ffffff');
  hubGrad.addColorStop(1, '#9ca3af');
  ctx.beginPath();
  ctx.arc(cx, cy, hubR, 0, Math.PI * 2);
  ctx.fillStyle = hubGrad;
  ctx.fill();

  // Logo no centro
  if (cfg.logo) {
    const img = new Image();
    img.src = cfg.logo;
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

// ─── 11. CANVAS RESPONSIVO ────────────────────────────
function resizeCanvas() {
  const canvas = document.getElementById('pub-canvas');
  if (!canvas) return;
  drawWheel(canvas, currentRotation, cfg.slices, cfg);
}

// ─── 12. ESTILOS DA VISTA PÚBLICA ─────────────────────
function applyPublicStyles() {
  const el = document.getElementById('pub-title');
  if (el) el.textContent = cfg.title || 'Gira e Ganha!';
  const el2 = document.getElementById('pub-message');
  if (el2) el2.textContent = cfg.message || 'Tente a sua sorte!';

  const logo = document.getElementById('pub-logo');
  if (logo) {
    if (cfg.loginLogo) {
      logo.src = cfg.loginLogo;
      logo.classList.remove('hidden');
    } else {
      logo.classList.add('hidden');
    }
  }

  // Redesenhar canvas com tamanho certo
  setTimeout(() => drawWheel(document.getElementById('pub-canvas'), 0, cfg.slices, cfg), 50);
}

// ─── 13. BOTÃO GIRAR — ESTADO ─────────────────────────
function updateSpinBtn() {
  const btn = document.getElementById('spin-btn');
  const msg = document.getElementById('spin-disabled-msg');
  if (!btn) return;

  const userId = getUserId();
  const spins = getUserSpins(userId);
  const limit = parseInt(cfg.spinLimit) || 1;
  const canSpin = !locked && spins < limit && !isSpinning;

  btn.disabled = !canSpin;
  if (msg) { msg.classList.toggle('hidden', canSpin); }
}

// ─── 14. GESTÃO DE GIROS POR USUÁRIO ──────────────────
function getUserId() {
  let uid = localStorage.getItem('sw_uid');
  if (!uid) { uid = Math.random().toString(36).slice(2); localStorage.setItem('sw_uid', uid); }
  return uid;
}

function getUserSpins(uid) {
  const data = JSON.parse(localStorage.getItem('sw_user_spins') || '{}');
  return data[uid] || 0;
}

function incrementUserSpins(uid) {
  const data = JSON.parse(localStorage.getItem('sw_user_spins') || '{}');
  data[uid] = (data[uid] || 0) + 1;
  localStorage.setItem('sw_user_spins', JSON.stringify(data));
}

// ─── 15. SORTEIO ──────────────────────────────────────
function pickWinner() {
  const total = cfg.slices.reduce((a, s) => a + Number(s.probability), 0) || 1;
  let rand = Math.random() * total;
  for (const s of cfg.slices) {
    rand -= Number(s.probability);
    if (rand <= 0) return s;
  }
  return cfg.slices[cfg.slices.length - 1];
}

function getSliceMidAngle(sliceId) {
  const sweep = (Math.PI * 2) / cfg.slices.length;
  for (let i = 0; i < cfg.slices.length; i++) {
    if (cfg.slices[i].id === sliceId) return i * sweep + sweep / 2;
  }
  return 0;
}

// ─── 16. ANIMAÇÃO DE GIRO ─────────────────────────────
function spinWheel() {
  if (isSpinning) return;
  const uid = getUserId();
  if (locked || getUserSpins(uid) >= (parseInt(cfg.spinLimit) || 1)) {
    updateSpinBtn(); return;
  }

  isSpinning = true;
  updateSpinBtn();

  const winner = pickWinner();
  const midAngle = getSliceMidAngle(winner.id);
  const alignAngle = -midAngle - Math.PI / 2;
  const visualSweep = (Math.PI * 2) / cfg.slices.length;
  const jitter = (Math.random() * 2 - 1) * (visualSweep / 2) * 0.65;
  const spins = Math.PI * 2 * (8 + Math.floor(Math.random() * 5));
  const target = currentRotation + spins + alignAngle + jitter - (currentRotation % (Math.PI * 2));

  const duration = 4500;
  const startTime = performance.now();
  const startRot = currentRotation;
  const canvas = document.getElementById('pub-canvas');

  // Métricas
  metrics.spins++;
  if (winner.type !== 'sem-premio') metrics.wins++;
  saveMetrics();
  incrementUserSpins(uid);

  function easeOut(t) { return 1 - Math.pow(1 - t, 4); }

  function animate(now) {
    const elapsed = now - startTime;
    const progress = Math.min(elapsed / duration, 1);
    currentRotation = startTime + (target - startRot) * easeOut(progress);
    currentRotation = startRot + (target - startRot) * easeOut(progress);
    drawWheel(canvas, currentRotation, cfg.slices, cfg);

    if (progress < 1) {
      requestAnimationFrame(animate);
    } else {
      isSpinning = false;
      updateSpinBtn();
      showResult(winner);
    }
  }
  requestAnimationFrame(animate);
}

// ─── 17. MODAL RESULTADO ──────────────────────────────
const motivationalMessages = [
  "Não desista agora, o próximo giro pode ser o seu!",
  "A sorte grande está chegando, continue tentando!",
  "Quase lá! Respire fundo e tente novamente.",
  "Dizem que a sorte favorece os persistentes...",
  "Hoje pode não ser o dia, mas o amanhã é um mistério!",
  "Sempre há uma próxima vez para brilhar.",
  "O mais importante é se divertir. Boa sorte na próxima!",
  "A persistência é o caminho do êxito!"
];

function showResult(slice) {
  const modal = document.getElementById('result-modal');
  const icon = document.getElementById('modal-icon');
  const title = document.getElementById('modal-title');
  const msg = document.getElementById('modal-msg');
  const prizeBox = document.getElementById('modal-prize-box');
  const pText = document.getElementById('modal-prize-text');
  const cBox = document.getElementById('modal-coupon-box');
  const cCode = document.getElementById('modal-coupon-code');
  const cUrl = document.getElementById('modal-coupon-url');
  const closeBtn = document.getElementById('modal-close-btn');

  const win = slice.type !== 'sem-premio';

  icon.textContent = win ? '🎉' : '😅';

  if (win) {
    title.textContent = cfg.winTitle || 'Parabéns!';
    msg.textContent = cfg.winMessage || 'Você ganhou!';

    // Disparar Confetes!
    if (typeof confetti === 'function') {
      confetti({
        particleCount: 150,
        spread: 80,
        origin: { y: 0.6 },
        colors: [cfg.accentColor, cfg.pointerColor, '#ffffff']
      });
    }
  } else {
    title.textContent = cfg.loseTitle || 'Quase lá!';
    // Usa uma mensagem motivacional aleatória se o usuário não configurou uma fixa,
    // ou se ele usou o padrão. Mas se for o padrão "Não foi desta vez...", a gente
    // pode substituir pela motivacional para ficar mais legal!
    const defaultLose = 'Não foi desta vez. Boa sorte na próxima!';
    let loseMsg = cfg.loseMessage || defaultLose;

    // Se estiver usando o padrão antigo, usamos aleatórias
    if (loseMsg === defaultLose || loseMsg === 'Tente novamente!') {
      loseMsg = motivationalMessages[Math.floor(Math.random() * motivationalMessages.length)];
    }

    msg.textContent = loseMsg;
  }

  closeBtn.textContent = cfg.popupBtnText || 'Fechar';

  // Ocultar tudo por padrão
  prizeBox.classList.add('hidden');
  cBox.classList.add('hidden');
  if (cUrl) { cUrl.classList.add('hidden'); cUrl.classList.remove('flex'); }

  if (win) {
    prizeBox.classList.remove('hidden');
    pText.textContent = slice.text;

    if (slice.value) {
      cBox.classList.remove('hidden');
      cBox.classList.add('flex');
      cCode.value = slice.value;
      if (slice.url) {
        cUrl.href = slice.url;
        cUrl.classList.remove('hidden');
        cUrl.classList.add('flex');
      }
    }
  }

  modal.classList.remove('hidden');
}

function closeModal() {
  document.getElementById('result-modal').classList.add('hidden');
}

function copyCoupon() {
  const code = document.getElementById('modal-coupon-code').value;
  navigator.clipboard.writeText(code).then(() => {
    const btn = document.getElementById('copy-coupon-btn');
    if (btn) {
      btn.innerHTML = '<i class="fa-solid fa-check"></i>';
      setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-copy"></i>'; }, 2000);
    }
    metrics.copies = (metrics.copies || 0) + 1;
    saveMetrics();
  });
}

// ─── 18. MÉTRICAS ─────────────────────────────────────
function saveMetrics() {
  localStorage.setItem('sw_metrics', JSON.stringify(metrics));
}

function updateMetricsUI() {
  const rate = metrics.spins > 0 ? Math.round((metrics.wins / metrics.spins) * 100) : 0;
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('m-visits', metrics.views);
  set('m-spins', metrics.spins);
  set('m-wins', metrics.wins);
  set('m-rate', rate + '%');
}

function resetMetrics() {
  if (!confirm('Confirma o reset de todas as métricas?')) return;
  metrics = { spins: 0, wins: 0, views: 0, copies: 0 };
  saveMetrics();
  updateMetricsUI();
}
