<?php
/**
 * ============================================================
 *  DON SPIN — API: Configurações da Roleta
 *  GET  /api/config.php        → lê config (público, sem auth)
 *  POST /api/config.php        → salva config (exige auth + CSRF)
 * ============================================================
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';

iniciarSessaoSegura();

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = getPDO();

// ────────────────────────────────────────────────────────────
if ($metodo === 'GET') {
    // Leitura pública — necessário para carregar a roleta no frontend
    // Não retorna campos sensíveis (senhas, tokens etc.)
    $stmt = $pdo->query('SELECT chave, valor FROM configuracoes');
    $rows = $stmt->fetchAll();

    $config = [];
    foreach ($rows as $row) {
        $config[$row['chave']] = $row['valor'];
    }

    // Converte tipos
    $config['spinLimit'] = (int)   ($config['spinLimit'] ?? 1);
    $config['locked']    = (int)   ($config['locked']    ?? 0) === 1;

    // Registra visita nas métricas (apenas no GET público)
    // Só conta se não for admin autenticado
    if (!estaAutenticado()) {
        $pdo->exec('UPDATE metricas SET visitas = visitas + 1 WHERE id = 1');
    }

    jsonResponse(['success' => true, 'config' => $config]);
}

// ────────────────────────────────────────────────────────────
if ($metodo === 'POST') {
    // Escrita — exige admin autenticado
    exigirAuth();
    exigirCsrf();

    // Aceita tanto form-data quanto JSON
    $data = !empty($_POST) ? $_POST : lerBodyJson();

    // Campos permitidos para atualização (whitelist)
    $camposPermitidos = [
        'title', 'message', 'winTitle', 'winMessage',
        'loseTitle', 'loseMessage', 'popupBtnText',
        'spinLimit', 'locked',
        'background', 'cardColor', 'cardBorderColor',
        'textColor', 'accentColor', 'pointerColor',
        'loginBgColor', 'loginCardColor',
        'logo', 'loginLogo',
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO configuracoes (chave, valor)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );

    foreach ($camposPermitidos as $campo) {
        if (!array_key_exists($campo, $data)) {
            continue;
        }

        $valor = $data[$campo];

        // Sanitização por tipo de campo
        if (in_array($campo, ['logo', 'loginLogo'], true)) {
            // Aceita URL ou path de imagem já salva
            $valor = sanitizarUrl($valor) ?: sanitizarString($valor, 500);
        } elseif (in_array($campo, ['spinLimit'], true)) {
            $valor = max(1, min(999, (int) $valor));
        } elseif (in_array($campo, ['locked'], true)) {
            $valor = ((bool) $valor || $valor === '1' || $valor === 'true') ? '1' : '0';
        } elseif (in_array($campo, ['background', 'cardColor', 'cardBorderColor', 'textColor', 'accentColor', 'pointerColor', 'loginBgColor', 'loginCardColor'], true)) {
            // Valida cor CSS (hex ou rgba)
            $valor = sanitizarString($valor, 50);
        } else {
            $valor = sanitizarString($valor, 500);
        }

        $stmt->execute([$campo, $valor]);
    }

    jsonResponse(['success' => true]);
}

// Método não suportado
jsonResponse(['success' => false, 'error' => 'Método não permitido.'], 405);
