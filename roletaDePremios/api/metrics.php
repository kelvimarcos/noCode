<?php
/**
 * ============================================================
 *  DON SPIN — API: Métricas
 *  GET    /api/metrics.php     → lê métricas (exige auth)
 *  DELETE /api/metrics.php     → reseta métricas (exige auth + CSRF)
 * ============================================================
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';

iniciarSessaoSegura();
exigirAuth(); // Métricas são sempre protegidas

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = getPDO();

// ────────────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $metricas = $pdo->query(
        'SELECT visitas, giros, ganhos, copias FROM metricas WHERE id = 1 LIMIT 1'
    )->fetch();

    if (!$metricas) {
        $metricas = ['visitas' => 0, 'giros' => 0, 'ganhos' => 0, 'copias' => 0];
    }

    // Calcula taxa de conversão
    $giros  = (int) $metricas['giros'];
    $ganhos = (int) $metricas['ganhos'];
    $taxa   = $giros > 0 ? round(($ganhos / $giros) * 100, 1) : 0;

    // Últimos 10 giros para detalhamento no painel
    $ultimosGiros = $pdo->query(
        'SELECT premio_texto, premio_tipo, ganhou, girado_em
         FROM giros
         ORDER BY girado_em DESC
         LIMIT 10'
    )->fetchAll();

    jsonResponse([
        'success'   => true,
        'visitas'   => (int) $metricas['visitas'],
        'giros'     => $giros,
        'ganhos'    => $ganhos,
        'copias'    => (int) $metricas['copias'],
        'taxa'      => $taxa,
        'ultimos'   => $ultimosGiros,
    ]);
}

// ────────────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    exigirCsrf();

    $pdo->exec('UPDATE metricas SET visitas = 0, giros = 0, ganhos = 0, copias = 0 WHERE id = 1');

    // Reseta também o spin_control para que todos possam girar novamente
    // (opcional — comente a linha abaixo se não quiser isso)
    // $pdo->exec('TRUNCATE TABLE spin_control');

    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'error' => 'Método não permitido.'], 405);
