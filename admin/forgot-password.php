<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

sessionStart();
if (isLoggedIn()) redirect('index.php');

$sent  = false;
$error = '';
$token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        $admin = dbRow("SELECT * FROM admins WHERE username = ? AND active = 1", [$email]);
        if ($admin) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            dbExec("UPDATE admins SET reset_token = ?, reset_expires = ? WHERE id = ?",
                [$token, $expires, $admin['id']]);

            $resetUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                      . '://' . $_SERVER['HTTP_HOST']
                      . dirname($_SERVER['SCRIPT_NAME'])
                      . '/reset-password.php?token=' . $token;

            $html = mailTemplate(
                'Redefinição de senha',
                "<p>Olá, <strong>" . htmlspecialchars($admin['name']) . "</strong>!</p>"
                . "<p>Recebemos uma solicitação para redefinir a senha da sua conta de administrador.</p>"
                . "<p>Clique no botão abaixo para criar uma nova senha (link válido por <strong>1 hora</strong>).</p>"
                . mailBtnHtml($resetUrl, 'Redefinir minha senha →')
                . "<p style='font-size:12px;color:#94a3b8'>Se você não solicitou isso, ignore este e-mail. Sua senha permanece a mesma.</p>"
            );
            sendMail($email, 'Redefinição de senha · Admin · PageQuiz', $html, $admin['name']);
        }
        // Sempre exibe a mesma mensagem (não revela se e-mail existe)
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<title>Recuperar senha · Admin · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box}
body{min-height:100vh;background:#0b1e35;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;font-family:'DM Sans',sans-serif}
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
.btn-login{width:100%;padding:13px;background:var(--pacific);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px;transition:background .2s,transform .15s;letter-spacing:.3px}
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
      <i class="fa-solid fa-key" aria-hidden="true"></i>
      Recuperar senha de administrador
    </div>

    <?php if ($sent): ?>
      <div class="alert-ok">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>Se esse e-mail estiver cadastrado, as instruções foram enviadas.</span>
      </div>
      <?php if (APP_DEBUG && $token): ?>
      <div style="background:#fffbea;border:1px solid #f6e05e;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:12px;color:#744210">
        <strong>[DEBUG]</strong> Link local (visível apenas com APP_DEBUG=true):<br/>
        <a href="reset-password.php?token=<?= htmlspecialchars($token) ?>" style="color:var(--pacific);word-break:break-all">
          reset-password.php?token=<?= htmlspecialchars($token) ?>
        </a>
      </div>
      <?php endif; ?>
      <div class="card-links"><a href="login.php">← Voltar ao login</a></div>
    <?php else: ?>
      <?php if ($error): ?>
      <div class="alert-err"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>
      <p style="font-size:13px;color:var(--gray-500);margin-bottom:20px;line-height:1.6">
        Informe o e-mail da sua conta de administrador. Enviaremos um link para criar uma nova senha.
      </p>
      <form method="post" autocomplete="on">
        <div class="form-group">
          <label class="form-label" for="email">E-mail</label>
          <input class="form-control" id="email" type="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="admin@suaempresa.com.br" required autocomplete="email"/>
        </div>
        <button type="submit" class="btn-login">
          <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar link de recuperação
        </button>
      </form>
      <div class="card-links" style="margin-top:20px"><a href="login.php">← Voltar ao login</a></div>
    <?php endif; ?>
  </div>
  <div class="login-footer">
    <a href="../index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao Quiz</a>
  </div>
</div>
</body>
</html>
