<?php
/**
 * ============================================================
 *  DON SPIN — Setup Inicial
 *  Cria o primeiro admin do sistema.
 *  ATENÇÃO: Delete ou proteja este arquivo após o setup!
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

iniciarSessaoSegura();
headersSeguranca();

// Se já existe pelo menos um admin e REGISTRATION_OPEN = false, bloqueia
if (!REGISTRATION_OPEN && contarAdmins() > 0) {
    die('<h1>Setup desativado</h1><p>Já existe um admin. Delete ou desative este arquivo.</p>');
}

$erro    = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
        $erro = 'Erro de segurança. Recarregue a página.';
    } else {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $senha  = $_POST['senha'] ?? '';
        $senha2 = $_POST['senha2'] ?? '';

        if (empty($nome) || empty($email) || empty($senha)) {
            $erro = 'Preencha todos os campos.';
        } elseif (mb_strlen($senha) < 8) {
            $erro = 'Senha deve ter pelo menos 8 caracteres.';
        } elseif ($senha !== $senha2) {
            $erro = 'As senhas não conferem.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail inválido.';
        } else {
            try {
                criarAdmin($nome, $email, $senha);
                $sucesso = 'Admin criado com sucesso! Faça login e delete o arquivo setup.php.';
            } catch (\RuntimeException $e) {
                $erro = $e->getMessage();
            }
        }
    }
}

$token = tokenCsrf();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Don Spin – Setup Inicial</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .setup-warning {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.8rem;
            color: #fca5a5;
            margin-bottom: 20px;
        }
    </style>
</head>
<body style="background:#0B0914;color:#F8F9FA;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;">
<div class="card" style="width:100%;max-width:480px;padding:40px;">
    <h1 style="font-size:1.5rem;font-weight:900;margin-bottom:8px;">Don Spin – Setup Inicial</h1>
    <p style="font-size:0.85rem;color:rgba(248,249,250,0.5);margin-bottom:24px;">Crie o primeiro administrador do sistema.</p>

    <div class="setup-warning">
        ⚠️ <strong>Importante:</strong> Delete ou proteja este arquivo (<code>setup.php</code>) após criar o admin!
    </div>

    <?php if ($erro): ?>
        <p style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#fca5a5;margin-bottom:16px;"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <p style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#86efac;margin-bottom:16px;"><?= htmlspecialchars($sucesso) ?></p>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary" style="width:100%;text-align:center;">Ir para Login</a>
    <?php else: ?>
    <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">

        <div>
            <label class="field-label">Nome</label>
            <input type="text" name="nome" class="admin-input" placeholder="Seu nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
        </div>
        <div>
            <label class="field-label">E-mail</label>
            <input type="email" name="email" class="admin-input" placeholder="admin@seusite.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div>
            <label class="field-label">Senha (mín. 8 chars, 1 maiúscula, 1 número)</label>
            <input type="password" name="senha" class="admin-input" placeholder="••••••••" required>
        </div>
        <div>
            <label class="field-label">Confirmar Senha</label>
            <input type="password" name="senha2" class="admin-input" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:14px;margin-top:8px;font-size:0.9rem;letter-spacing:0.08em;text-transform:uppercase;">Criar Admin</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
