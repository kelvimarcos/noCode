<?php
/**
 * ============================================================
 *  DON SPIN — API: Registro de Admin
 *  POST /api/register.php
 *  Body: { nome, email, senha, senha2, csrf_token }
 *
 *  Disponível apenas quando REGISTRATION_OPEN = true
 *  ou quando não há nenhum admin no banco (bootstrap inicial).
 * ============================================================
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

iniciarSessaoSegura();
exigirMetodo('POST');
exigirCsrf();

// ── Verifica se registro está permitido ──────────────────────
$qtdAdmins = contarAdmins();
if (!REGISTRATION_OPEN && $qtdAdmins > 0) {
    jsonResponse(['success' => false, 'error' => 'Registro de novos admins está desativado.'], 403);
}

// ── Lê e valida inputs ───────────────────────────────────────
$nome   = sanitizarString($_POST['nome'] ?? '', 100);
$email  = strtolower(trim($_POST['email'] ?? ''));
$senha  = $_POST['senha'] ?? '';
$senha2 = $_POST['senha2'] ?? '';

if (empty($nome) || empty($email) || empty($senha) || empty($senha2)) {
    jsonResponse(['success' => false, 'error' => 'Preencha todos os campos.'], 400);
}

if (mb_strlen($nome) < 2) {
    jsonResponse(['success' => false, 'error' => 'Nome muito curto.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'E-mail inválido.'], 400);
}

if (mb_strlen($senha) < 8) {
    jsonResponse(['success' => false, 'error' => 'Senha deve ter pelo menos 8 caracteres.'], 400);
}

if (!preg_match('/[A-Z]/', $senha) || !preg_match('/[0-9]/', $senha)) {
    jsonResponse(['success' => false, 'error' => 'Senha deve conter pelo menos uma letra maiúscula e um número.'], 400);
}

if ($senha !== $senha2) {
    jsonResponse(['success' => false, 'error' => 'As senhas não conferem.'], 400);
}

// ── Cria admin no banco ──────────────────────────────────────
try {
    $id = criarAdmin($nome, $email, $senha);
    jsonResponse(['success' => true, 'id' => $id]);
} catch (\RuntimeException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 409);
} catch (\Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Erro ao criar conta.'], 500);
}
