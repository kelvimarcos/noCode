<?php
/**
 * ============================================================
 *  DON SPIN — Roleta Pública
 *  Acessível sem autenticação. Carrega config via API.
 *  O sorteio é feito NO SERVIDOR — apenas animação no frontend.
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

// Inicia sessão para gerar token CSRF para o usuário público
iniciarSessaoSegura();
headersSeguranca();

// Gera token CSRF para proteger a chamada /api/spin.php
$csrfToken = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Don Spin – Gira e Ganha!</title>
    <meta name="description" content="Gire a roleta e ganhe prêmios incríveis!">
    <!-- Token CSRF para chamar /api/spin.php de forma segura -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: {
                    bg:'var(--bg)', surface:'var(--surface)', surface2:'var(--surface2)',
                    accent:'var(--accent)', 'accent-hi':'var(--accent-hi)',
                    text:'var(--text)', muted:'var(--muted)'
                }
            }}
        };
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-bg text-text min-h-screen overflow-x-hidden">

<!-- ======================================================
     ROLETA PÚBLICA — configuração carregada via API
====================================================== -->
<div id="public-view" class="min-h-screen flex items-center justify-center p-4">
    <div class="card w-full max-w-md p-8 flex flex-col items-center gap-6">

        <!-- Header -->
        <div class="flex flex-col items-center gap-3 text-center">
            <img id="pub-logo" class="h-12 object-contain hidden" alt="Logo">
            <h1 id="pub-title" class="text-3xl font-black tracking-tight leading-tight">Carregando…</h1>
            <p id="pub-message" class="text-sm text-muted"></p>
        </div>

        <!-- Roleta -->
        <div class="relative flex flex-col items-center gap-0 w-full mt-4">
            <div id="wheel-wrapper" class="w-full max-w-[300px] aspect-square relative">
                <div id="pub-pointer" class="pointer-tri z-10"></div>
                <canvas id="pub-canvas" class="w-full h-full block rounded-full"></canvas>
            </div>
        </div>

        <!-- Botão Girar -->
        <div class="flex flex-col items-center gap-3 w-full">
            <button id="spin-btn" class="w-full sm:w-auto" disabled>
                <i class="fa-solid fa-rotate-right mr-2"></i>GIRAR
            </button>
            <p id="spin-disabled-msg" class="hidden text-xs text-muted flex items-center gap-1.5">
                <i class="fa-solid fa-lock"></i> Bloqueado pelo administrador
            </p>
            <p id="spin-limit-msg" class="hidden text-xs text-muted flex items-center gap-1.5">
                <i class="fa-solid fa-check-circle"></i> Você já usou todos os seus giros
            </p>
        </div>

    </div>
</div>

<!-- ======================================================
     MODAL: RESULTADO DO SORTEIO
====================================================== -->
<div id="result-modal" class="hidden modal-overlay">
    <div class="card w-full max-w-sm p-8 text-center pop-in flex flex-col items-center gap-4">

        <div id="modal-icon" class="text-5xl leading-none">🎉</div>

        <div>
            <h2 id="modal-title" class="text-2xl font-black tracking-tight"></h2>
            <p id="modal-msg" class="text-sm text-muted mt-2"></p>
        </div>

        <!-- Prêmio -->
        <div id="modal-prize-box" class="hidden w-full card-sm p-4 flex flex-col items-center gap-4">
            <p class="text-xs font-bold uppercase tracking-widest text-muted">Seu Prêmio</p>
            <p id="modal-prize-text" class="text-2xl font-black tracking-tight text-white"></p>

            <!-- Cupom -->
            <div id="modal-coupon-box" class="hidden w-full flex-col gap-3">
                <div class="divider w-full"></div>
                <p class="text-xs font-bold uppercase tracking-widest text-muted">Código de Desconto</p>
                <div class="flex gap-2 w-full">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-ticket absolute left-3.5 top-1/2 -translate-y-1/2 text-yellow-400/60 text-sm"></i>
                        <input id="modal-coupon-code" type="text" readonly class="admin-input font-mono text-center text-lg tracking-widest font-black text-yellow-300 pl-10">
                    </div>
                    <button onclick="copiarCupom()" id="copy-coupon-btn" class="btn btn-primary shrink-0 text-xs px-4 py-2.5">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                </div>
                <a id="modal-coupon-url" href="#" target="_blank" rel="noopener" class="hidden btn btn-success w-full text-sm py-3 mt-1">
                    Resgatar Agora <i class="fa-solid fa-arrow-up-right-from-square ml-1.5"></i>
                </a>
            </div>
        </div>

        <button id="modal-close-btn" onclick="fecharModal()" class="btn btn-ghost w-full text-sm py-3 mt-2">Fechar</button>
    </div>
</div>

<script>
    const BASE_URL   = '<?= BASE_URL ?>';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
</script>
<script src="<?= BASE_URL ?>/assets/js/wheel.js"></script>
<script src="<?= BASE_URL ?>/assets/js/public.js"></script>
</body>
</html>
