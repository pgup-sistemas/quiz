<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';

if (isSuperAdminLoggedIn()) {
    header('Location: index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (superAdminLogin($user, $pass)) {
        header('Location: index.php'); exit;
    }
    $error = 'Credenciais inválidas.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Super Admin · Login · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
html,body { height:100%; margin:0; background:#05111f; font-family:var(--font-body,'DM Sans',sans-serif); }
.login-outer { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
.login-box { background:#fff; border-radius:var(--radius,12px); padding:40px 36px; width:100%; max-width:380px; box-shadow:0 20px 60px rgba(0,0,0,.4); }
.login-logo { display:flex; align-items:center; gap:10px; margin-bottom:28px; }
.login-logo img { height:36px; }
.login-logo-text { font-family:var(--font-heading,'Syne',sans-serif); font-size:20px; font-weight:800; color:var(--prussian,#023047); }
.login-logo-sub { font-size:10px; font-weight:700; color:var(--yellow,#FFB703); text-transform:uppercase; letter-spacing:.08em; display:block; }
.login-box h2 { font-family:var(--font-heading,'Syne',sans-serif); font-size:18px; color:var(--prussian,#023047); margin:0 0 6px; }
.login-box p  { font-size:13px; color:var(--gray-500,#6b7280); margin:0 0 24px; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-size:13px; font-weight:600; color:var(--gray-700,#374151); margin-bottom:6px; }
.form-group input { width:100%; box-sizing:border-box; padding:10px 14px; border:1.5px solid var(--gray-200,#e5e7eb); border-radius:8px; font-size:14px; font-family:inherit; transition:.2s; }
.form-group input:focus { outline:none; border-color:var(--pacific,#219EBC); box-shadow:0 0 0 3px rgba(33,158,188,.15); }
.btn-login { width:100%; padding:12px; background:var(--prussian,#023047); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; transition:.2s; margin-top:8px; }
.btn-login:hover { background:#012336; }
.alert-error { background:#fee2e2; color:#991b1b; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:16px; }
</style>
</head>
<body>
<div class="login-outer">
    <div class="login-box">
        <div class="login-logo">
            <img src="../assets/logo-color.svg" alt="PageUp" onerror="this.style.display='none'"/>
            <div>
                <span class="login-logo-text">PageQuiz</span>
                <span class="login-logo-sub"><i class="fa-solid fa-shield-halved"></i> Super Admin</span>
            </div>
        </div>
        <h2>Acesso restrito</h2>
        <p>Portal exclusivo PageUp Sistemas.</p>

        <?php if ($error): ?>
        <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="seu@email.com"/>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required placeholder="••••••••"/>
            </div>
            <button type="submit" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i> Entrar
            </button>
        </form>
    </div>
</div>
</body>
</html>
