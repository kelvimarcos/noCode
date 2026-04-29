<?php
/**
 * ============================================================
 *  DON SPIN — Proteção CSRF
 *  Tokens CSRF impedem que sites externos façam requisições
 *  autenticadas em nome do usuário (Cross-Site Request Forgery).
 * ============================================================
 */

require_once __DIR__ . '/auth.php'; // Garante que sessão está iniciada

/**
 * Gera um token CSRF e armazena na sessão.
 * O mesmo token é válido para toda a sessão até ser substituído.
 *
 * @return string Token CSRF em hexadecimal (64 chars)
 */
function gerarTokenCsrf(): string
{
    iniciarSessaoSegura();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 256 bits de entropia
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida o token CSRF recebido contra o armazenado na sessão.
 * Usa hash_equals() para comparação timing-safe (resistente a timing attacks).
 *
 * @param string $tokenRecebido Token vindo do formulário/header
 * @return bool true se válido, false se inválido
 */
function validarTokenCsrf(string $tokenRecebido): bool
{
    iniciarSessaoSegura();

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    // hash_equals garante que a comparação leve sempre o mesmo tempo,
    // impedindo que um atacante descubra o token por timing.
    return hash_equals($_SESSION['csrf_token'], $tokenRecebido);
}

/**
 * Retorna o token CSRF atual (ou gera um novo se não existir).
 * Convenência para usar em templates HTML.
 */
function tokenCsrf(): string
{
    return gerarTokenCsrf();
}

/**
 * Valida CSRF e encerra com erro 403 se inválido.
 * Usar no início de endpoints que modificam dados.
 */
function exigirCsrf(): void
{
    // Aceita token no header (para fetch API) ou no corpo do formulário
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $_POST['csrf_token']
          ?? '';

    if (!validarTokenCsrf($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Requisição inválida (CSRF).']);
        exit;
    }
}
