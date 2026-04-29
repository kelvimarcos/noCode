<?php
/**
 * ============================================================
 *  DON SPIN — Login Admin
 *  Exibe o formulário de login e processa via API /api/login.php
 *  O processamento real está no backend (api/login.php).
 *  Esta página apenas renderiza o HTML e fornece o token CSRF.
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

iniciarSessaoSegura();
headersSeguranca();

// Se já está autenticado, redireciona direto para o admin
if (estaAutenticado()) {
    header('Location: ' . BASE_URL . '/admin.php');
    exit;
}

// Gera (ou recupera) o token CSRF para o formulário
$csrfToken = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Don Spin – Painel Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- TELA DE LOGIN ADMIN -->
<div id="login-screen" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;">
    <div class="card" style="width:100%;max-width:380px;padding:36px;">

        <!-- Logo / Branding -->
        <div style="margin-bottom:32px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                <div style="width:34px;height:34px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-rotate-right" style="color:#fff;font-size:0.85rem;"></i>
                </div>
                <span style="font-weight:900;font-size:1.1rem;letter-spacing:-0.02em;">Don Spin</span>
            </div>
            <h1 style="font-size:1.6rem;font-weight:900;letter-spacing:-0.02em;margin-bottom:6px;">Painel Admin</h1>
            <p style="font-size:0.82rem;color:var(--muted);">Acesso restrito a administradores.</p>
        </div>

        <!-- Mensagem de erro (preenchida pelo JS) -->
        <p id="login-error" style="display:none;font-size:0.78rem;color:#fca5a5;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:10px;padding:10px 14px;text-align:center;margin-bottom:16px;"></p>

        <!-- Formulário -->
        <form id="login-form" style="display:flex;flex-direction:column;gap:16px;" novalidate>
            <!-- Token CSRF embutido no form -->
            <input type="hidden" id="csrf-token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div>
                <label class="field-label"><i class="fa-solid fa-envelope" style="margin-right:4px;"></i>E-mail</label>
                <input id="login-email" type="email" autocomplete="email" placeholder="admin@seusite.com" class="admin-input" required>
            </div>
            <div>
                <label class="field-label"><i class="fa-solid fa-lock" style="margin-right:4px;"></i>Senha</label>
                <input id="login-pass" type="password" autocomplete="current-password" placeholder="••••••••" class="admin-input" required>
            </div>

            <button type="submit" id="login-btn" class="btn btn-primary" style="width:100%;padding:14px;margin-top:8px;font-size:0.82rem;letter-spacing:0.1em;text-transform:uppercase;">
                Entrar
            </button>
        </form>

        <!-- Link para criar conta (visível apenas se permitido) -->
        <div style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border);text-align:center;">
            <p style="font-size:0.75rem;color:var(--muted);margin-bottom:8px;">Primeiro acesso?</p>
            <a href="<?= BASE_URL ?>/setup.php" style="font-size:0.78rem;font-weight:700;color:var(--accent-hi);text-decoration:none;">
                Criar Conta Administrativa
            </a>
        </div>
    </div>
</div>

<script>
/* ── Login via API PHP — sem localStorage ──────────────────── */
document.getElementById('login-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn    = document.getElementById('login-btn');
    const errEl  = document.getElementById('login-error');
    const email  = document.getElementById('login-email').value.trim();
    const senha  = document.getElementById('login-pass').value;
    const csrf   = document.getElementById('csrf-token').value;

    errEl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Entrando…';

    try {
        // Envia para o backend — sessão é gerenciada pelo PHP
        const form = new FormData();
        form.append('email', email);
        form.append('senha', senha);
        form.append('csrf_token', csrf);

        const res  = await fetch('<?= BASE_URL ?>/api/login.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            // Login OK — redireciona para painel (sem armazenar nada no cliente)
            window.location.href = '<?= BASE_URL ?>/admin.php';
        } else {
            errEl.textContent    = data.error || 'Credenciais inválidas.';
            errEl.style.display  = 'block';
            btn.disabled         = false;
            btn.textContent      = 'Entrar';
        }
    } catch (err) {
        errEl.textContent   = 'Erro de conexão. Tente novamente.';
        errEl.style.display = 'block';
        btn.disabled        = false;
        btn.textContent     = 'Entrar';
    }
});
</script>
</body>
</html>
