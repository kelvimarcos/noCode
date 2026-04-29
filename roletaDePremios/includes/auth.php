<?php
/**
 * ============================================================
 *  DON SPIN — Autenticação de Admin
 *  Gerencia sessões seguras com PHP.
 *  Nunca usa localStorage ou sessionStorage para autenticação.
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Inicia a sessão PHP de forma segura.
 * Configura cookies HttpOnly, SameSite e Secure (quando em HTTPS).
 */
function iniciarSessaoSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Sessão já iniciada
    }

    // Configura opções de sessão antes de session_start()
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only em produção
        'httponly' => true,   // Bloqueia acesso via JavaScript (impede XSS roubar sessão)
        'samesite' => 'Lax',  // Proteção CSRF básica via cookie
    ]);

    // Nome personalizado evita fingerprinting do framework
    session_name('ds_admin');
    session_start();

    // Regenera ID de sessão periodicament para mitigar session fixation
    if (!isset($_SESSION['_criado_em'])) {
        $_SESSION['_criado_em'] = time();
        session_regenerate_id(true);
    } elseif (time() - $_SESSION['_criado_em'] > 900) {
        // Regenera a cada 15 minutos
        $_SESSION['_criado_em'] = time();
        session_regenerate_id(true);
    }
}

/**
 * Verifica se há um admin autenticado na sessão.
 */
function estaAutenticado(): bool
{
    iniciarSessaoSegura();
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Exige autenticação. Redireciona para login se não estiver autenticado.
 * Usar no topo de qualquer página/endpoint admin.
 */
function exigirAuth(): void
{
    if (!estaAutenticado()) {
        // Se foi uma requisição AJAX/API, retorna JSON
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        ) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
            exit;
        }
        // Página normal → redireciona para login
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Tenta autenticar um admin com email e senha.
 * Usa password_verify() — nunca compara senhas em texto puro.
 *
 * @return array|false Dados do admin se sucesso, false se falhar
 */
function logarAdmin(string $email, string $senha): array|false
{
    $pdo = getPDO();

    // Busca admin pelo email com prepared statement
    $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash, ativo FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    // Se não encontrou ou está inativo, nega acesso
    if (!$admin || !$admin['ativo']) {
        // Simula tempo de verificação para mitigar timing attacks
        password_verify('dummy', '$2y$10$dummyhashfortimingprotection0000000000000000000000000');
        return false;
    }

    // Verifica senha com hash seguro
    if (!password_verify($senha, $admin['senha_hash'])) {
        return false;
    }

    // Atualiza último login
    $pdo->prepare('UPDATE admins SET ultimo_login = NOW() WHERE id = ?')->execute([$admin['id']]);

    return $admin;
}

/**
 * Cria sessão autenticada após login bem-sucedido.
 */
function criarSessaoAdmin(array $admin): void
{
    iniciarSessaoSegura();
    session_regenerate_id(true); // Previne session fixation no login

    $_SESSION['admin_id']    = $admin['id'];
    $_SESSION['admin_nome']  = $admin['nome'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['_criado_em']  = time();
}

/**
 * Destrói a sessão admin completamente.
 * Limpa todos os dados e invalida o cookie.
 */
function deslogarAdmin(): void
{
    iniciarSessaoSegura();

    // Limpa dados da sessão
    $_SESSION = [];

    // Apaga o cookie de sessão
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Cria um novo admin no banco de dados.
 * Usa password_hash com BCRYPT (compatível com PHP 7.4+).
 *
 * @throws \RuntimeException Se email já existir
 */
function criarAdmin(string $nome, string $email, string $senha): int
{
    $pdo = getPDO();

    // Verifica se email já existe
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new \RuntimeException('E-mail já cadastrado.');
    }

    // Hash seguro da senha — BCRYPT é suficiente e suportado por todos os hosts
    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare('INSERT INTO admins (nome, email, senha_hash) VALUES (?, ?, ?)');
    $stmt->execute([trim($nome), trim(strtolower($email)), $hash]);

    return (int) $pdo->lastInsertId();
}

/**
 * Conta quantos admins existem no banco.
 * Usado para controlar se o registro público está liberado.
 */
function contarAdmins(): int
{
    $pdo  = getPDO();
    $stmt = $pdo->query('SELECT COUNT(*) FROM admins WHERE ativo = 1');
    return (int) $stmt->fetchColumn();
}
