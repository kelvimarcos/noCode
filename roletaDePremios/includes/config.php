<?php
/**
 * ============================================================
 *  DON SPIN — Configuração Central
 *  Edite APENAS este arquivo para ajustar o ambiente.
 *  NÃO versione este arquivo com as credenciais reais.
 * ============================================================
 */

// ── BANCO DE DADOS ──────────────────────────────────────────
// Preencha com os dados do seu banco MySQL criado no cPanel.
define('DB_HOST', 'localhost');
define('DB_NAME', 'hgma8621_roleta');
define('DB_USER', 'hgma8621_roleta_user');
define('DB_PASS', 'Paiemae1206');

// ── SEGURANÇA DA SESSÃO ──────────────────────────────────────
// Chave secreta única — gere com: php -r "echo bin2hex(random_bytes(32));"
define('SESSION_SECRET',  'TROQUE_ESTA_CHAVE_POR_UMA_ALEATORIA_DE_64_CHARS');

// Tempo de vida da sessão admin em segundos (padrão: 8 horas)
define('SESSION_LIFETIME', 60 * 60 * 8);

// ── REGISTRO DE ADMINS ───────────────────────────────────────
// true  → permite registro público via /setup.php (APENAS para setup inicial)
// false → bloqueia criação de novos admins pela interface
// IMPORTANTE: Após criar o primeiro admin, defina como false ou delete setup.php
define('REGISTRATION_OPEN', true);

// ── CONTROLE DE GIROS ────────────────────────────────────────
// Número de dias que o cookie de controle de giros dura.
// Após este período, o usuário pode girar novamente.
define('COOKIE_SPIN_DAYS', 30);

// Se true, também usa IP (hash) como fator de limite (mais rígido, mas menos
// confiável atrás de NAT compartilhado). Recomendado: false para público geral.
define('USE_IP_CONTROL', false);

// ── UPLOAD DE IMAGENS ────────────────────────────────────────
// Tamanho máximo de upload em bytes (padrão: 2 MB)
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);

// Tipos MIME permitidos para upload
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);

// ── AMBIENTE ─────────────────────────────────────────────────
// 'production' → oculta erros PHP (use em produção)
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
// URL raiz do projeto. Ajuste se não estiver em /roleta/.
// Ex: 'https://seusite.com/roleta' ou 'https://seusite.com' se estiver na raiz.
define('BASE_URL', '/roleta');
