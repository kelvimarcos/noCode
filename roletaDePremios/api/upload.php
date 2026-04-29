<?php
/**
 * ============================================================
 *  DON SPIN — API: Upload de Imagens
 *  POST /api/upload.php
 *  Multipart: file=<arquivo>  campo=<logo|loginLogo>
 *
 *  Valida MIME real (não apenas extensão), limita tamanho,
 *  renomeia com hash aleatório e salva em assets/img/uploads/.
 * ============================================================
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';

iniciarSessaoSegura();
exigirAuth();
exigirCsrf();
exigirMetodo('POST');

if (empty($_FILES['file'])) {
    jsonResponse(['success' => false, 'error' => 'Nenhum arquivo enviado.'], 400);
}

$arquivo = $_FILES['file'];
$campo   = in_array($_POST['campo'] ?? '', ['logo', 'loginLogo'], true) ? $_POST['campo'] : 'logo';

// Verifica erro de upload do PHP
if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'error' => 'Erro no upload do arquivo.'], 400);
}

// Verifica tamanho
if ($arquivo['size'] > MAX_UPLOAD_SIZE) {
    $max = round(MAX_UPLOAD_SIZE / 1048576, 1);
    jsonResponse(['success' => false, 'error' => "Arquivo muito grande. Máximo: {$max} MB."], 400);
}

// Verifica MIME type real (não confia na extensão ou no MIME do cliente)
$mimeReal = mime_content_type($arquivo['tmp_name']);
if (!in_array($mimeReal, ALLOWED_MIME_TYPES, true)) {
    jsonResponse(['success' => false, 'error' => 'Tipo de arquivo não permitido.'], 400);
}

// Determina extensão segura baseada no MIME real
$extensoes = [
    'image/jpeg'  => 'jpg',
    'image/png'   => 'png',
    'image/gif'   => 'gif',
    'image/webp'  => 'webp',
    'image/svg+xml' => 'svg',
];
$ext = $extensoes[$mimeReal] ?? 'jpg';

// Nome aleatório — nunca usa o nome original do arquivo
$nomeArquivo = bin2hex(random_bytes(16)) . '.' . $ext;

// Pasta de destino
$pastaDestino = __DIR__ . '/../assets/img/uploads/';
if (!is_dir($pastaDestino)) {
    mkdir($pastaDestino, 0755, true);
}

$caminhoDestino = $pastaDestino . $nomeArquivo;

// Move do diretório temporário para o destino
if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
    jsonResponse(['success' => false, 'error' => 'Erro ao salvar arquivo.'], 500);
}

// Salva o caminho no banco
$urlRelativa = BASE_URL . '/assets/img/uploads/' . $nomeArquivo;
$pdo = getPDO();
$pdo->prepare(
    'INSERT INTO configuracoes (chave, valor)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
)->execute([$campo, $urlRelativa]);

jsonResponse(['success' => true, 'url' => $urlRelativa]);
