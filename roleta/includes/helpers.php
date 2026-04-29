<?php
/**
 * ============================================================
 *  DON SPIN — Helpers / Utilitários
 *  Funções auxiliares usadas por toda a aplicação.
 * ============================================================
 */

require_once __DIR__ . '/config.php';

/**
 * Envia uma resposta JSON padronizada e encerra o script.
 *
 * @param array $data   Dados a serializar
 * @param int   $status Código HTTP (200, 400, 401, 403, 500...)
 */
function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    // Evita que proxies/caches guardem respostas de API
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sanitiza uma string de entrada:
 * - Remove espaços extras
 * - Converte caracteres especiais HTML (previne XSS no output)
 */
function sanitizarString(string $val, int $maxLen = 255): string
{
    return htmlspecialchars(mb_substr(trim($val), 0, $maxLen), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitiza uma URL, retornando string vazia se inválida.
 */
function sanitizarUrl(string $url): string
{
    $url = trim($url);
    if (empty($url)) {
        return '';
    }
    // Valida que é uma URL http/https real
    $filtrada = filter_var($url, FILTER_VALIDATE_URL);
    if ($filtrada === false) {
        return '';
    }
    // Só aceita http e https (bloqueia javascript: e data:)
    $scheme = parse_url($filtrada, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    return $filtrada;
}

/**
 * Retorna o identificador único do usuário público.
 * Combina um cookie persistente (gerado no primeiro acesso) com
 * hash do IP (opcional) para controle de limite de giros.
 *
 * Nunca armazena o IP diretamente — apenas o SHA-256 para privacidade.
 */
function getIdentificadorUsuario(): string
{
    $cookieName = 'ds_uid';

    // Se já tem cookie, usa ele
    if (!empty($_COOKIE[$cookieName])) {
        $uid = $_COOKIE[$cookieName];
        // Valida formato (apenas hex 32 chars)
        if (preg_match('/^[a-f0-9]{32}$/', $uid)) {
            return $uid;
        }
    }

    // Gera novo identificador aleatório
    $uid = bin2hex(random_bytes(16)); // 128 bits — colisão improvável

    // Define cookie com longa duração
    $dias = defined('COOKIE_SPIN_DAYS') ? (int) COOKIE_SPIN_DAYS : 30;
    setcookie($cookieName, $uid, [
        'expires'  => time() + ($dias * 86400),
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $uid;
}

/**
 * Retorna hash SHA-256 do IP do cliente (para uso em logs).
 * Nunca armazena o IP diretamente — apenas o hash.
 */
function getIpHash(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
       ?? $_SERVER['HTTP_CLIENT_IP']
       ?? $_SERVER['REMOTE_ADDR']
       ?? '0.0.0.0';

    // Pega apenas o primeiro IP se houver lista (X-Forwarded-For)
    $ip = trim(explode(',', $ip)[0]);

    return hash('sha256', $ip . SESSION_SECRET); // Salted hash
}

/**
 * Lê o body raw da requisição como JSON e retorna array.
 * Usado pelos endpoints que recebem application/json.
 */
function lerBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Garante que o método HTTP da requisição é o esperado.
 * Retorna 405 Method Not Allowed caso contrário.
 */
function exigirMetodo(string ...$metodos): void
{
    $atual = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($atual, array_map('strtoupper', $metodos), true)) {
        jsonResponse(['success' => false, 'error' => 'Método não permitido.'], 405);
    }
}

/**
 * Define headers de segurança recomendados.
 * Chamar no início de páginas HTML (não em APIs JSON).
 */
function headersSeguranca(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
