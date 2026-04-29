<?php
/**
 * ============================================================
 *  DON SPIN — API: Prêmios (Fatias da Roleta)
 *  GET    /api/prizes.php          → lista prêmios (público)
 *  POST   /api/prizes.php          → cria ou atualiza prêmio (auth + CSRF)
 *  DELETE /api/prizes.php?id=N     → remove prêmio (auth + CSRF)
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
    // Público — necessário para renderizar as fatias na roleta pública
    $stmt = $pdo->query(
        'SELECT id, texto, tipo, valor, url, probabilidade, cor, ordem
         FROM premios
         WHERE ativo = 1
         ORDER BY ordem ASC, id ASC'
    );
    $premios = $stmt->fetchAll();

    // Converte tipos numéricos
    foreach ($premios as &$p) {
        $p['id']           = (int)   $p['id'];
        $p['probabilidade'] = (float) $p['probabilidade'];
        $p['ordem']        = (int)   $p['ordem'];
    }
    unset($p);

    jsonResponse(['success' => true, 'premios' => $premios]);
}

// ────────────────────────────────────────────────────────────
if ($metodo === 'POST') {
    exigirAuth();
    exigirCsrf();

    $data = !empty($_POST) ? $_POST : lerBodyJson();

    // Tipos válidos de fatia
    $tiposValidos = ['cupom', 'premio', 'sem-premio'];
    $tipo = in_array($data['tipo'] ?? '', $tiposValidos, true) ? $data['tipo'] : 'premio';

    $texto        = sanitizarString($data['texto'] ?? 'Novo Prêmio', 100);
    $valor        = $tipo === 'sem-premio' ? '' : sanitizarString($data['valor'] ?? '', 100);
    $url          = $tipo === 'sem-premio' ? '' : sanitizarUrl($data['url'] ?? '');
    $probabilidade = max(0.01, min(100.0, (float) ($data['probabilidade'] ?? 10)));
    $cor          = sanitizarString($data['cor'] ?? '#6366F1', 20);
    $ordem        = (int) ($data['ordem'] ?? 0);
    $id           = isset($data['id']) ? (int) $data['id'] : null;

    if (empty($texto)) {
        jsonResponse(['success' => false, 'error' => 'Texto da fatia é obrigatório.'], 400);
    }

    if ($id) {
        // Atualiza prêmio existente
        $stmt = $pdo->prepare(
            'UPDATE premios
             SET texto = ?, tipo = ?, valor = ?, url = ?, probabilidade = ?, cor = ?, ordem = ?
             WHERE id = ?'
        );
        $stmt->execute([$texto, $tipo, $valor, $url, $probabilidade, $cor, $ordem, $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['success' => false, 'error' => 'Prêmio não encontrado.'], 404);
        }
        jsonResponse(['success' => true, 'id' => $id]);
    } else {
        // Cria novo prêmio
        $stmt = $pdo->prepare(
            'INSERT INTO premios (texto, tipo, valor, url, probabilidade, cor, ordem)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$texto, $tipo, $valor, $url, $probabilidade, $cor, $ordem]);
        jsonResponse(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
    }
}

// ────────────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    exigirAuth();

    // Para DELETE, o token CSRF vem no header
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCsrf($token)) {
        jsonResponse(['success' => false, 'error' => 'Requisição inválida (CSRF).'], 403);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'error' => 'ID inválido.'], 400);
    }

    // Verifica mínimo de 2 prêmios
    $total = (int) $pdo->query('SELECT COUNT(*) FROM premios WHERE ativo = 1')->fetchColumn();
    if ($total <= 2) {
        jsonResponse(['success' => false, 'error' => 'A roleta precisa ter pelo menos 2 fatias.'], 400);
    }

    // Soft delete (mantém histórico em giros)
    $stmt = $pdo->prepare('UPDATE premios SET ativo = 0 WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'error' => 'Prêmio não encontrado.'], 404);
    }

    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'error' => 'Método não permitido.'], 405);
