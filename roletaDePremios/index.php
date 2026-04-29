<?php
/**
 * ============================================================
 *  DON SPIN — Roteador Principal
 *  Redireciona para a página correta baseado na autenticação
 *  e no parâmetro ?view=public
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

iniciarSessaoSegura();

// Roleta pública — acessível sem autenticação
if (isset($_GET['view']) && $_GET['view'] === 'public') {
    require __DIR__ . '/public.php';
    exit;
}

// Admin autenticado → painel admin
if (estaAutenticado()) {
    require __DIR__ . '/admin.php';
    exit;
}

// Não autenticado → tela de login
require __DIR__ . '/login.php';
