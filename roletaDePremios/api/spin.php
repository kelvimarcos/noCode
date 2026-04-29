<?php
/**
 * ============================================================
 *  DON SPIN — API: Girar a Roleta (SORTEIO NO SERVIDOR)
 *  POST /api/spin.php
 *  Header: X-CSRF-Token: <token>
 *
 *  O prêmio é SEMPRE sorteado aqui, nunca no frontend.
 *  O frontend apenas anima até o índice retornado pelo servidor.
 *
 *  Fluxo:
 *   1. Verifica se roleta está bloqueada
 *   2. Obtém identificador do usuário (cookie)
 *   3. Verifica limite de giros no banco
 *   4. Sorteia prêmio com mt_rand() + probabilidades
 *   5. Registra giro, atualiza métricas e spin_control
 *   6. Retorna índice da fatia vencedora (NÃO o prêmio completo ainda — só após confirmar)
 * ============================================================
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';

// Inicia sessão para verificar CSRF (usuário público pode ter sessão de cookie)
iniciarSessaoSegura();
exigirMetodo('POST');

$pdo = getPDO();

// ── 1. Verifica se roleta está bloqueada ─────────────────────
$locked = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'locked' LIMIT 1")->fetchColumn();
if ((int) $locked === 1) {
    jsonResponse(['success' => false, 'error' => 'A roleta está bloqueada pelo administrador.'], 403);
}

// ── 2. Valida CSRF ───────────────────────────────────────────
// Para usuário público, o token é gerado na página public.php e enviado via header
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!validarTokenCsrf($token)) {
    jsonResponse(['success' => false, 'error' => 'Requisição inválida.'], 403);
}

// ── 3. Obtém identificador e verifica limite de giros ────────
$identificador = getIdentificadorUsuario();
$spinLimit = (int) ($pdo->query("SELECT valor FROM configuracoes WHERE chave = 'spinLimit' LIMIT 1")->fetchColumn() ?: 1);

// Busca quantos giros este usuário já fez
$stmtCtrl = $pdo->prepare('SELECT total_giros FROM spin_control WHERE identificador = ? LIMIT 1');
$stmtCtrl->execute([$identificador]);
$spinCtrl = $stmtCtrl->fetch();
$totalGirosUsuario = $spinCtrl ? (int) $spinCtrl['total_giros'] : 0;

if ($totalGirosUsuario >= $spinLimit) {
    jsonResponse([
        'success' => false,
        'error'   => 'Você já utilizou todos os seus giros disponíveis.',
        'limit_reached' => true,
    ], 403);
}

// ── 4. Carrega prêmios ativos ────────────────────────────────
$premios = $pdo->query(
    'SELECT id, texto, tipo, valor, url, probabilidade, cor, ordem
     FROM premios
     WHERE ativo = 1
     ORDER BY ordem ASC, id ASC'
)->fetchAll();

if (empty($premios)) {
    jsonResponse(['success' => false, 'error' => 'Nenhum prêmio cadastrado.'], 500);
}

// ── 5. Sorteio server-side com mt_rand() ─────────────────────
// Calcula probabilidade acumulada
$totalPeso = array_sum(array_column($premios, 'probabilidade'));
if ($totalPeso <= 0) {
    // Fallback: probabilidade igual para todos
    $totalPeso = count($premios);
    foreach ($premios as &$p) {
        $p['probabilidade'] = 1;
    }
    unset($p);
}

// mt_rand é adequado para sorteios de prêmios — não é criptográfico,
// mas é suficiente para esse caso de uso (não envolve segurança crítica).
// O importante é que o cálculo está NO SERVIDOR, não no frontend.
$rand = mt_rand(0, (int) ($totalPeso * 10000)) / 10000;
$acumulado = 0;
$vencedor = $premios[count($premios) - 1]; // Fallback: último prêmio
$indiceVencedor = count($premios) - 1;

foreach ($premios as $idx => $premio) {
    $acumulado += (float) $premio['probabilidade'];
    if ($rand <= $acumulado) {
        $vencedor = $premio;
        $indiceVencedor = $idx;
        break;
    }
}

$ganhou = ($vencedor['tipo'] !== 'sem-premio') ? 1 : 0;
$ipHash = getIpHash();

// ── 6. Registra giro no banco (transação para consistência) ──
try {
    $pdo->beginTransaction();

    // Log do giro
    $stmtGiro = $pdo->prepare(
        'INSERT INTO giros (premio_id, premio_texto, premio_tipo, premio_valor, identificador, ip_hash, ganhou)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtGiro->execute([
        $vencedor['id'],
        $vencedor['texto'],
        $vencedor['tipo'],
        $vencedor['valor'] ?? '',
        $identificador,
        $ipHash,
        $ganhou,
    ]);

    // Atualiza métricas globais
    $pdo->prepare(
        'UPDATE metricas SET giros = giros + 1, ganhos = ganhos + ? WHERE id = 1'
    )->execute([$ganhou]);

    // Atualiza controle de giros do usuário (upsert)
    $pdo->prepare(
        'INSERT INTO spin_control (identificador, total_giros)
         VALUES (?, 1)
         ON DUPLICATE KEY UPDATE total_giros = total_giros + 1, ultimo_em = NOW()'
    )->execute([$identificador]);

    $pdo->commit();
} catch (\Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'error' => 'Erro ao registrar giro.'], 500);
}

// ── 7. Retorna resultado ao frontend ─────────────────────────
// O frontend só recebe o ÍNDICE da fatia vencedora + dados para exibir o modal.
// Nunca retorna probabilidades ou lógica de sorteio.
jsonResponse([
    'success'        => true,
    'winner_index'   => $indiceVencedor,  // Índice na lista de prêmios (para animação)
    'tipo'           => $vencedor['tipo'],
    'texto'          => $vencedor['texto'],
    'valor'          => $ganhou ? ($vencedor['valor'] ?? '') : '',
    'url'            => $ganhou ? sanitizarUrl($vencedor['url'] ?? '') : '',
    'ganhou'         => (bool) $ganhou,
    'giros_restantes'=> max(0, $spinLimit - ($totalGirosUsuario + 1)),
]);
