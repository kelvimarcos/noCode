<?php
/**
 * ============================================================
 *  DON SPIN — API: Login Admin
 *  POST /api/login.php
 *  Body: { email, senha, csrf_token }
 *
 *  Valida credenciais no banco e cria sessão PHP segura.
 *  Nunca expõe detalhes internos em caso de erro.
 * ============================================================
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Inicia sessão e garante método correto
iniciarSessaoSegura();
exigirMetodo('POST');

// ── Rate Limiting por sessão ─────────────────────────────────
// Bloqueia por 30 segundos após 5 tentativas falhas consecutivas.
if (!isset($_SESSION['_login_tentativas'])) {
    $_SESSION['_login_tentativas'] = 0;
    $_SESSION['_login_bloqueio']   = 0;
}

if ($_SESSION['_login_tentativas'] >= 5) {
    $segundosRestantes = $_SESSION['_login_bloqueio'] - time();
    if ($segundosRestantes > 0) {
        jsonResponse([
            'success' => false,
            'error'   => "Muitas tentativas. Aguarde {$segundosRestantes}s.",
        ], 429);
    }
    // Bloqueio expirado — reseta contadores
    $_SESSION['_login_tentativas'] = 0;
    $_SESSION['_login_bloqueio']   = 0;
}

// ── Valida CSRF ──────────────────────────────────────────────
exigirCsrf();

// ── Lê e valida inputs ───────────────────────────────────────
$email = sanitizarString($_POST['email'] ?? '', 191);
$senha = $_POST['senha'] ?? ''; // Não sanitiza senha — apenas valida tamanho

if (empty($email) || empty($senha)) {
    jsonResponse(['success' => false, 'error' => 'Preencha todos os campos.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'E-mail inválido.'], 400);
}

// ── Tenta autenticar ─────────────────────────────────────────
$admin = logarAdmin($email, $senha);

if (!$admin) {
    // Incrementa tentativas falhas
    $_SESSION['_login_tentativas']++;
    if ($_SESSION['_login_tentativas'] >= 5) {
        $_SESSION['_login_bloqueio'] = time() + 30; // Bloqueia por 30s
    }

    // Mensagem genérica — não revela se email existe ou não
    jsonResponse(['success' => false, 'error' => 'E-mail ou senha incorretos.'], 401);
}

// ── Login bem-sucedido ───────────────────────────────────────
criarSessaoAdmin($admin);

// Reseta contadores de tentativa
$_SESSION['_login_tentativas'] = 0;
$_SESSION['_login_bloqueio']   = 0;

jsonResponse([
    'success' => true,
    'nome'    => $admin['nome'],
]);
