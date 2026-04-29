<?php
/**
 * ============================================================
 *  DON SPIN — Logout
 *  Destrói sessão PHP e redireciona para login.
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

iniciarSessaoSegura();
deslogarAdmin();

header('Location: ' . BASE_URL . '/login.php');
exit;
