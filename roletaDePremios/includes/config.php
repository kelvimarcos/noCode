<?php
/**
 * ============================================================
 *  DON SPIN — Configuração Central
 *  ⚠️  NUNCA versione este arquivo com credenciais reais.
 *       Adicione ao .gitignore: config.php (se contiver senhas)
 *       ou use config.local.php (ver exemplo: config.local.example.php)
 * ============================================================
 */

// ── BANCO DE DADOS ──────────────────────────────────────────
// Preencha com os dados do seu banco MySQL criado no cPanel.
// ⚠️  Troque pela sua senha real — NUNCA use a padrão em produção.
define('DB_HOST', 'localhost');
define('DB_NAME', 'hgma8621_roleta');
define('DB_USER', 'hgma8621_roleta_user');
define('DB_PASS', getenv('DB_PASS') ?: '');  // Use variável de ambiente ou config.local.php

// ── SEGURANÇA DA SESSÃO ──────────────────────────────────────
// Gere uma chave real com: php -r "echo bin2hex(random_bytes(32));"
// Coloque o valor gerado aqui (ou em config.local.php).
define('SESSION_SECRET', getenv('SESSION_SECRET') ?: 'TROQUE_ESTA_CHAVE_AGORA');

// Tempo de vida da sessão admin em segundos (padrão: 8 horas)
define('SESSION_LIFETIME', 60 * 60 * 8);

// ── REGISTRO DE ADMINS ───────────────────────────────────────
// ⚠️  Mantenha FALSE em produção após criar o primeiro admin.
// true  → permite /setup.php (APENAS para setup inicial)
// false → bloqueia criação de novos admins pela interface
define('REGISTRATION_OPEN', false);

// ── CONTROLE DE GIROS ────────────────────────────────────────
// Número de dias que o cookie de controle de giros dura.
define('COOKIE_SPIN_DAYS', 30);

// Se true, também usa IP (hash) como fator de limite.
define('USE_IP_CONTROL', false);

// ── UPLOAD DE IMAGENS ────────────────────────────────────────
// Tamanho máximo de upload em bytes (padrão: 2 MB)
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);

// Tipos MIME permitidos para upload
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);

// ── AMBIENTE ─────────────────────────────────────────────────
// 'production'  → oculta erros PHP (use em produção)
// 'development' → exibe erros (use localmente com XAMPP)
define('APP_ENV', 'production');

// ── CONFIGURAR PHP BASEADO NO AMBIENTE ───────────────────────
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// ── BASE URL ─────────────────────────────────────────────────
// URL raiz do projeto. Ajuste conforme seu ambiente.
// Ex: 'https://seusite.com/roleta' ou '' se estiver na raiz.
define('BASE_URL', '/roleta');

// ── Carrega configuração local (senhas, chaves) se existir ───
// Crie um arquivo config.local.php com DB_PASS, SESSION_SECRET, etc.
// Esse arquivo NÃO deve ser versionado no Git.
$_localConfig = __DIR__ . '/../config.local.php';
if (file_exists($_localConfig)) {
    require_once $_localConfig;
}
