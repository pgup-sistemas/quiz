<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

sessionStart();
if (isLoggedIn()) redirect('index.php');

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$admin = null;
$done  = false;
$error = '';

if ($token) {
    $admin = dbRow(
        "SELECT * FROM admins WHERE reset_token = ? AND reset_expires > datetime('now','localtime')",
        [$token]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token && $admin) {
    $pass    = $_POST['password']  ?? '';
    $confirm = $_POST['password2'] ?? '';

    if (strlen($pass) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($pass !== $confirm) {
        $error = 'As senhas não conferem.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        dbExec("UPDATE admins SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
            [$hash, $admin['id']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<title>Nova senha · Admin · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box}
body{min-height:100vh;background:#eef4f7;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;font-family:'DM Sans',sans-serif}
.login-box{width:100%;max-width:420px}
.login-card{background:#fff;border-radius:24px;padding:40px 36px 32px;box-shadow:0 2px 4px rgba(2,48,71,.04),0 8px 20px rgba(2,48,71,.08),0 24px 56px rgba(2,48,71,.12)}
.brand-block{text-align:center;margin-bottom:28px;padding-bottom:24px;border-bottom:1px solid #e8eef2}
.brand-icon{display:inline-block;margin-bottom:14px;filter:drop-shadow(0 4px 12px rgba(2,48,71,.18))}
.brand-name{font-size:22px;font-weight:700;color:var(--prussian);line-height:1;margin-bottom:5px}
.brand-name span{color:var(--pacific)}
.brand-sub{font-size:12px;color:var(--gray-400);letter-spacing:.5px}
.form-title{font-size:15px;font-weight:700;color:var(--gray-700);margin-bottom:20px;display:flex;align-items:center;gap:8px}
.form-title i{color:var(--pacific);font-size:15px}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--gray-500);margin-bottom:6px}
.form-control{width:100%;padding:12px 14px;border:1.5px solid #dce8ef;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--prussian);outline:none;transition:.2s;background:#fff}
.form-control:focus{border-color:var(--pacific);box-shadow:0 0 0 3px rgba(33,158,188,.10)}
.form-control::placeholder{color:var(--gray-300)}
.btn-login{width:100%;padding:13px;background:var(--pacific);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px;transition:background .2s,transform .15s;letter-spacing:.3px;text-align:center;text-decoration:none;display:block}
.btn-login:hover{background:var(--prussian)}
.btn-login:active{transform:scale(.98)}
.alert-ok{display:flex;align-items:flex-start;gap:10px;background:#f0fff4;border:1px solid #9ae6b4;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#276749}
.alert-ok i{flex-shrink:0;margin-top:1px}
.alert-err{display:flex;align-items:flex-start;gap:10px;background:#fff5f5;border:1px solid #fed7d7;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:#c53030}
.alert-err i{flex-shrink:0}
.card-links{text-align:center;margin-top:20px;font-size:13px;color:var(--gray-500)}
.card-links a{color:var(--pacific);font-weight:600;text-decoration:none}
.card-links a:hover{color:var(--prussian)}
.login-footer{text-align:center;margin-top:22px}
.login-footer a{color:var(--pacific);font-size:13px;font-weight:500;text-decoration:none;opacity:.75;transition:opacity .2s}
.login-footer a:hover{opacity:1}
</style>
</head>
<body>
<div class="login-box">
  <div class="login-card">
    <div class="brand-block">
      <div class="brand-icon">
        <img src="../assets/logo-icon.svg" width="72" height="72" alt="PageQuiz"/>
      </div>
      <div class="brand-name">Page<span>Quiz</span></div>
      <div class="brand-sub">by PageUp Sistemas</div>
    </div>

    <div class="form-title">
      <i class="fa-solid fa-lock" aria-hidden="true"></i>
      Nova senha de administrador
    </div>

    <?php if ($done): ?>
      <div class="alert-ok">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>Senha alterada com sucesso!</span>
      </div>
      <a href="login.php" class="btn-login">Entrar agora <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    <?php elseif (!$admin): ?>
      <div class="alert-err">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>Link inválido ou expirado. <a href="forgot-password.php" style="color:var(--pacific)">Solicitar novo link</a></span>
      </div>
    <?php else: ?>
      <?php if ($error): ?>
      <div class="alert-err"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>
      <p style="font-size:13px;color:var(--gray-500);margin-bottom:20px">
        Redefinindo senha para <strong><?= htmlspecialchars($admin['username']) ?></strong>
      </p>
      <form method="post" autocomplete="off">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>
        <div class="form-group">
          <label class="form-label" for="password">Nova senha</label>
          <input class="form-control" id="password" type="password" name="password"
                 placeholder="Mínimo 6 caracteres" required autocomplete="new-password"/>
        </div>
        <div class="form-group">
          <label class="form-label" for="password2">Confirmar nova senha</label>
          <input class="form-control" id="password2" type="password" name="password2"
                 placeholder="Repita a nova senha" required autocomplete="new-password"/>
        </div>
        <button type="submit" class="btn-login">
          <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Salvar nova senha
        </button>
      </form>
    <?php endif; ?>
  </div>
  <div class="login-footer">
    <a href="../index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao Quiz</a>
  </div>
</div>
</body>
</html>
