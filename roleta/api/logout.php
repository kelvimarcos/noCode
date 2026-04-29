<?php
/**
 * ============================================================
 *  DON SPIN — API: Logout Admin
 *  POST /api/logout.php
 *
 *  Destrói a sessão PHP completamente e apaga o cookie.
 * ============================================================
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSessaoSegura();
exigirMetodo('POST');

// Destrói sessão — não exige CSRF aqui pois é logout
// (o pior que pode acontecer é fazer o usuário sair, o que não é prejuízo)
deslogarAdmin();

jsonResponse(['success' => true]);
