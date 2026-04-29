<?php
/**
 * ============================================================
 *  DON SPIN — Painel Admin
 *  Exige autenticação via sessão PHP.
 *  O admin.js carrega config e prêmios via API (sem localStorage).
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

// ❌ Se não autenticado, redireciona para login — sem exceções
exigirAuth();
headersSeguranca();

// Token CSRF disponível para o JavaScript (via meta tag)
$csrfToken = tokenCsrf();
$adminNome = htmlspecialchars($_SESSION['admin_nome'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Don Spin – Painel Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <!-- Token CSRF acessível ao JS via atributo dataset ou meta tag -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
     PAINEL ADMIN — carregado apenas com sessão PHP válida
====================================================== -->
<div id="admin-panel" class="min-h-screen flex flex-col">

    <!-- Header Admin -->
    <header class="sticky top-0 z-40 border-b" style="background:var(--surface);border-color:var(--border)">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-md bg-accent flex items-center justify-center">
                    <i class="fa-solid fa-rotate-right text-white text-xs"></i>
                </div>
                <span class="font-black text-base tracking-tight">Don Spin</span>
                <span class="badge badge-accent text-[0.6rem] ml-1">Admin</span>
                <span class="hidden sm:inline text-xs text-muted ml-1">— <?= $adminNome ?></span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="copiarLinkPublico()" class="btn btn-ghost text-xs py-2 px-3 gap-1.5">
                    <i class="fa-solid fa-share-nodes text-accent-hi"></i>
                    <span class="hidden sm:inline">Compartilhar</span>
                </button>
                <button onclick="fazerLogout()" class="btn btn-ghost text-xs py-2 px-3 gap-1.5 text-red-400 border-red-500/20 hover:bg-red-500/08">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span class="hidden sm:inline">Sair</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Conteúdo -->
    <div class="max-w-6xl mx-auto w-full px-4 py-6 flex flex-col gap-6 flex-1">

        <!-- Nav Tabs -->
        <nav class="admin-nav">
            <button class="admin-tab active" onclick="mudarTab('roleta')" data-tab="roleta">
                <i class="fa-solid fa-circle-dot mr-1.5"></i>Roleta
            </button>
            <button class="admin-tab" onclick="mudarTab('premios')" data-tab="premios">
                <i class="fa-solid fa-gift mr-1.5"></i>Prêmios
            </button>
            <button class="admin-tab" onclick="mudarTab('aparencia')" data-tab="aparencia">
                <i class="fa-solid fa-palette mr-1.5"></i>Aparência
            </button>
            <button class="admin-tab" onclick="mudarTab('metricas')" data-tab="metricas">
                <i class="fa-solid fa-chart-bar mr-1.5"></i>Métricas
            </button>
        </nav>

        <!-- ============ TAB: ROLETA ============ -->
        <div id="tab-roleta" class="tab-content flex flex-col gap-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Preview da roleta -->
                <div class="card p-6 flex flex-col items-center gap-6">
                    <div class="flex items-center justify-between w-full">
                        <h2 class="font-bold text-sm uppercase tracking-widest text-muted">Preview</h2>
                        <span id="admin-lock-badge" class="badge badge-green"><i class="fa-solid fa-unlock mr-1"></i>Desbloqueado</span>
                    </div>
                    <div id="admin-wheel-wrapper" class="w-full max-w-[260px] aspect-square relative mt-4">
                        <div id="admin-pointer" class="pointer-tri"></div>
                        <canvas id="admin-canvas" class="w-full h-full block rounded-full"></canvas>
                    </div>
                </div>

                <!-- Controles -->
                <div class="card p-6 flex flex-col gap-5">
                    <h2 class="font-bold text-sm uppercase tracking-widest text-muted">Controle de Acesso</h2>

                    <div class="card-sm p-4 flex flex-col gap-3">
                        <label class="field-label"><i class="fa-solid fa-rotate-right mr-1"></i>Limite de Giros por Usuário</label>
                        <input id="c-spinLimit" type="number" min="1" max="999" value="1" class="admin-input" oninput="atualizarConfig('spinLimit', this.value)">
                        <p class="text-xs text-muted">Número de vezes que cada usuário pode girar.</p>
                    </div>

                    <div class="card-sm p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold text-sm">Roleta Bloqueada</p>
                            <p class="text-xs text-muted mt-0.5">Quando ativo, ninguém pode girar.</p>
                        </div>
                        <button id="lockBtn" onclick="alternarBloqueio()" class="btn btn-ghost text-xs py-2 px-4">
                            <i class="fa-solid fa-lock mr-1.5"></i>Bloquear
                        </button>
                    </div>

                    <div class="card-sm p-4 flex flex-col gap-3">
                        <label class="field-label"><i class="fa-solid fa-link mr-1"></i>Link Público</label>
                        <div class="flex gap-2">
                            <input id="public-link-field" type="text" readonly class="admin-input font-mono text-xs">
                            <button onclick="copiarLinkPublico()" class="btn btn-ghost py-2 px-3 shrink-0">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TAB: PRÊMIOS ============ -->
        <div id="tab-premios" class="tab-content hidden flex-col gap-4">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sm uppercase tracking-widest text-muted">Prêmios</h2>
                <button onclick="adicionarPremio()" class="btn btn-primary text-xs py-2 px-4">
                    <i class="fa-solid fa-plus mr-1.5"></i>Adicionar
                </button>
            </div>
            <div id="slices-list" class="flex flex-col gap-3"></div>
            <!-- Botão salvar prêmios -->
            <div class="flex justify-end mt-2">
                <button onclick="salvarTodosPremios()" class="btn btn-primary text-sm py-2.5 px-6">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Salvar Prêmios
                </button>
            </div>
        </div>

        <!-- ============ TAB: APARÊNCIA ============ -->
        <div id="tab-aparencia" class="tab-content hidden flex-col gap-6">

            <!-- Identidade Visual -->
            <div class="card p-6 flex flex-col gap-5">
                <h3 class="font-bold text-sm uppercase tracking-widest text-muted border-b pb-3 mb-1" style="border-color:var(--border)">
                    <i class="fa-solid fa-star text-accent mr-2"></i>Identidade Visual
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="field-label"><i class="fa-solid fa-image mr-1"></i>Logo Global (URL)</label>
                        <input id="c-loginLogo" type="url" placeholder="https://..." class="admin-input" oninput="atualizarConfig('loginLogo', this.value)">
                        <input type="file" accept="image/*" onchange="fazerUploadImagem(this,'loginLogo')" class="mt-2 text-xs text-muted">
                        <p class="text-[0.65rem] text-muted mt-1">Visível na tela de login e cabeçalho público.</p>
                    </div>
                    <div>
                        <label class="field-label"><i class="fa-solid fa-droplet mr-1"></i>Cor Primária (Destaque)</label>
                        <div class="flex gap-3 items-center">
                            <input id="c-accentColor" type="color" class="admin-input w-14 p-1 h-12 cursor-pointer" oninput="atualizarConfig('accentColor', this.value)">
                            <span class="text-xs text-muted leading-tight">Usada em botões e<br>detalhes de destaque.</span>
                        </div>
                    </div>
                    <div>
                        <label class="field-label"><i class="fa-solid fa-font mr-1"></i>Cor do Texto Principal</label>
                        <div class="flex gap-3 items-center">
                            <input id="c-textColor" type="color" class="admin-input w-14 p-1 h-12 cursor-pointer" oninput="atualizarConfig('textColor', this.value)">
                            <span class="text-xs text-muted leading-tight">Títulos e legendas.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tema da Plataforma -->
            <div class="card p-6 flex flex-col gap-5">
                <h3 class="font-bold text-sm uppercase tracking-widest text-muted border-b pb-3 mb-1" style="border-color:var(--border)">
                    <i class="fa-solid fa-desktop text-accent mr-2"></i>Tema da Plataforma
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="field-label"><i class="fa-solid fa-fill-drip mr-1"></i>Cor de Fundo</label>
                        <input id="c-background" type="color" class="admin-input w-full p-1 h-10 cursor-pointer" oninput="atualizarConfig('background', this.value)">
                    </div>
                    <div>
                        <label class="field-label"><i class="fa-solid fa-layer-group mr-1"></i>Cor dos Cards</label>
                        <input id="c-cardColor" type="color" class="admin-input w-full p-1 h-10 cursor-pointer" oninput="atualizarConfig('cardColor', this.value)">
                    </div>
                    <div>
                        <label class="field-label"><i class="fa-solid fa-border-all mr-1"></i>Cor das Bordas</label>
                        <input id="c-cardBorderColor" type="color" class="admin-input w-full p-1 h-10 cursor-pointer" oninput="atualizarConfig('cardBorderColor', this.value)">
                    </div>
                </div>
            </div>

            <!-- Estilo da Roleta -->
            <div class="card p-6 flex flex-col gap-5">
                <h3 class="font-bold text-sm uppercase tracking-widest text-muted border-b pb-3 mb-1" style="border-color:var(--border)">
                    <i class="fa-solid fa-bullseye text-accent mr-2"></i>Estilo da Roleta
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="field-label"><i class="fa-solid fa-circle-dot mr-1"></i>Logo Central (URL)</label>
                        <input id="c-logo" type="url" placeholder="https://..." class="admin-input" oninput="atualizarConfig('logo', this.value)">
                        <input type="file" accept="image/*" onchange="fazerUploadImagem(this,'logo')" class="mt-2 text-xs text-muted">
                    </div>
                    <div>
                        <label class="field-label"><i class="fa-solid fa-caret-down mr-1"></i>Cor do Ponteiro</label>
                        <div class="flex gap-3 items-center">
                            <input id="c-pointerColor" type="color" class="admin-input w-14 p-1 h-12 cursor-pointer" oninput="atualizarConfig('pointerColor', this.value)">
                            <span class="text-xs text-muted leading-tight">Indicador de sorteio<br>no topo da roda.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Textos da Fachada -->
            <div class="card p-6 flex flex-col gap-5">
                <h3 class="font-bold text-sm uppercase tracking-widest text-muted border-b pb-3 mb-1" style="border-color:var(--border)">
                    <i class="fa-solid fa-language text-accent mr-2"></i>Textos da Fachada
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="field-label"><i class="fa-solid fa-heading mr-1"></i>Título Principal</label>
                        <input id="c-title" type="text" placeholder="Gira e Ganha!" class="admin-input" oninput="atualizarConfig('title', this.value)">
                    </div>
                    <div>
                        <label class="field-label"><i class="fa-solid fa-align-left mr-1"></i>Subtítulo</label>
                        <input id="c-message" type="text" placeholder="Tente a sua sorte!" class="admin-input" oninput="atualizarConfig('message', this.value)">
                    </div>
                    <div class="md:col-span-2 divider my-0 border-white/5"></div>
                    <div>
                        <label class="field-label" style="color:var(--green)"><i class="fa-solid fa-trophy"></i> Título: Ganhou</label>
                        <input id="c-winTitle" type="text" class="admin-input" oninput="atualizarConfig('winTitle', this.value)">
                    </div>
                    <div>
                        <label class="field-label" style="color:var(--green)"><i class="fa-solid fa-comment"></i> Mensagem: Ganhou</label>
                        <input id="c-winMessage" type="text" class="admin-input" oninput="atualizarConfig('winMessage', this.value)">
                    </div>
                    <div class="md:col-span-2 divider my-0 border-white/5"></div>
                    <div>
                        <label class="field-label" style="color:var(--red)"><i class="fa-solid fa-face-sad-tear"></i> Título: Perdeu</label>
                        <input id="c-loseTitle" type="text" class="admin-input" oninput="atualizarConfig('loseTitle', this.value)">
                    </div>
                    <div>
                        <label class="field-label" style="color:var(--red)"><i class="fa-solid fa-comment-slash"></i> Mensagem: Perdeu</label>
                        <input id="c-loseMessage" type="text" class="admin-input" oninput="atualizarConfig('loseMessage', this.value)">
                    </div>
                    <div class="md:col-span-2 divider my-0 border-white/5"></div>
                    <div>
                        <label class="field-label"><i class="fa-solid fa-x mr-1"></i>Texto Botão do Modal</label>
                        <input id="c-popupBtnText" type="text" class="admin-input" oninput="atualizarConfig('popupBtnText', this.value)">
                    </div>
                </div>
            </div>

            <!-- Botão Salvar Aparência -->
            <div class="flex justify-end">
                <button onclick="salvarAparencia()" class="btn btn-primary text-sm py-2.5 px-6">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Salvar Aparência
                </button>
            </div>
        </div>

        <!-- ============ TAB: MÉTRICAS ============ -->
        <div id="tab-metricas" class="tab-content hidden flex-col gap-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="metric-card">
                    <span class="metric-label"><i class="fa-solid fa-eye mr-1"></i>Acessos</span>
                    <span id="m-visits" class="metric-value">–</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="fa-solid fa-rotate-right mr-1"></i>Giros</span>
                    <span id="m-spins" class="metric-value">–</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="fa-solid fa-trophy mr-1"></i>Ganhos</span>
                    <span id="m-wins" class="metric-value">–</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label"><i class="fa-solid fa-percent mr-1"></i>Conversão</span>
                    <span id="m-rate" class="metric-value">–</span>
                </div>
            </div>
            <!-- Últimos giros -->
            <div class="card p-5">
                <h3 class="font-bold text-sm uppercase tracking-widest text-muted mb-4">Últimos Giros</h3>
                <div id="ultimos-giros" class="flex flex-col gap-2 text-sm text-muted"></div>
            </div>
            <button onclick="resetarMetricas()" class="btn btn-danger text-xs self-start">
                <i class="fa-solid fa-trash mr-1.5"></i>Resetar Métricas
            </button>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="<?= BASE_URL ?>/assets/js/wheel.js"></script>
<script>
    const BASE_URL   = '<?= BASE_URL ?>';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
</script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
