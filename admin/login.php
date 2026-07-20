<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

sessionStart();
if (isLoggedIn()) redirect('index.php');

$error = '';
if (!empty($_GET['suspended'])) {
    $error = 'Sua empresa foi suspensa. Entre em contato com o suporte para mais informações.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (adminLogin($user, $pass)) {
        $companyStatus = dbRow("SELECT status FROM companies WHERE id=?", [adminCompanyId()])['status'] ?? null;
        if ($companyStatus === 'suspended') {
            $_SESSION = [];
            session_destroy();
            $error = 'Sua empresa foi suspensa. Entre em contato com o suporte para mais informações.';
        } else {
            // Novos admins de tenant (first_login=1) vão para onboarding
            $adminRow = dbRow("SELECT first_login FROM admins WHERE id=?", [adminId()]);
            if (!empty($adminRow['first_login'])) {
                redirect('onboarding.php');
            }
            redirect('index.php');
        }
    } else {
        $error = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<title>Admin · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
* { box-sizing: border-box; }

body {
    min-height: 100vh;
    background: #0b1e35;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
    font-family: 'DM Sans', sans-serif;
}

.login-box {
    width: 100%;
    max-width: 420px;
}

.login-card {
    background: #fff;
    border-radius: 24px;
    padding: 40px 36px 32px;
    box-shadow:
        0 2px 4px rgba(2,48,71,.04),
        0 8px 20px rgba(2,48,71,.08),
        0 24px 56px rgba(2,48,71,.12);
}

/* ── Cabeçalho da marca dentro do card ── */
.brand-block {
    text-align: center;
    margin-bottom: 28px;
    padding-bottom: 24px;
    border-bottom: 1px solid #e8eef2;
}

.brand-icon {
    display: inline-block;
    margin-bottom: 14px;
    filter: drop-shadow(0 4px 12px rgba(2,48,71,.18));
}

.brand-name {
    font-size: 22px;
    font-weight: 700;
    color: var(--prussian);
    line-height: 1;
    margin-bottom: 5px;
}

.brand-name span { color: var(--pacific); }

.brand-sub {
    font-size: 12px;
    color: var(--gray-400);
    letter-spacing: .5px;
}

/* ── Título do form ── */
.form-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--gray-700);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-title i { color: var(--pacific); font-size: 15px; }

/* ── Campos ── */
.login-card .form-group { margin-bottom: 16px; }

.login-card .form-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--gray-500);
    margin-bottom: 6px;
}

.login-card .form-control {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #dce8ef;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    color: var(--prussian);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    background: #fff;
}

.login-card .form-control:focus {
    border-color: var(--pacific);
    box-shadow: 0 0 0 3px rgba(33,158,188,.10);
}

.login-card .form-control::placeholder { color: var(--gray-300); }

/* ── Botão ── */
.btn-login {
    width: 100%;
    padding: 13px;
    background: var(--pacific);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 6px;
    transition: background .2s, transform .15s;
    letter-spacing: .3px;
}

.btn-login:hover  { background: var(--prussian); }
.btn-login:active { transform: scale(.98); }

/* ── Erro ── */
.login-error-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff5f5;
    border: 1px solid #fed7d7;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 18px;
    font-size: 13px;
    color: #c53030;
}

.login-error-box i { flex-shrink: 0; }

/* ── Footer ── */
.login-footer {
    text-align: center;
    margin-top: 22px;
}

.login-footer a {
    color: var(--pacific);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    opacity: .75;
    transition: opacity .2s;
}

.login-footer a:hover { opacity: 1; }

/* ── Rodapé do card (dev info) ── */
.card-foot {
    text-align: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e8eef2;
    font-size: 11px;
    color: var(--gray-300);
    line-height: 1.7;
}
</style>
</head>
<body>

<div class="login-box">
    <div class="login-card">

        <!-- Logo dentro do card -->
        <div class="brand-block">
            <div class="brand-icon">
                <img src="../assets/logo-icon.svg" width="72" height="72" alt="PageQuiz"/>
            </div>
            <div class="brand-name">Page<span>Quiz</span></div>
            <div class="brand-sub">by PageUp Sistemas</div>
        </div>

        <!-- Formulário -->
        <div class="form-title">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Área administrativa — gestores
        </div>
        <div style="background:#f0f7fa;border:1px solid #bde3ef;border-radius:10px;padding:10px 14px;margin-bottom:18px;font-size:12.5px;color:#2c6e84;line-height:1.55">
            <i class="fa-solid fa-circle-info" style="margin-right:5px"></i>
            Este portal é para <strong>gestores e administradores de empresa</strong>.<br/>
            Se você é colaborador ou participante, <a href="../user/login.php" style="color:var(--prussian);font-weight:700;text-decoration:underline">acesse o portal do colaborador</a>.
        </div>

        <?php if ($error): ?>
        <div class="login-error-box">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <div class="form-group">
                <label class="form-label" for="inp-user">Usuário</label>
                <input class="form-control" id="inp-user" type="text" name="username"
                       value="<?= e($_POST['username'] ?? '') ?>"
                       placeholder="admin" required autocomplete="username"/>
            </div>
            <div class="form-group">
                <label class="form-label" for="inp-pass">Senha</label>
                <input class="form-control" id="inp-pass" type="password" name="password"
                       placeholder="••••••••" required autocomplete="current-password"/>
                <div style="text-align:right;margin-top:6px;font-size:13px">
                  <a href="forgot-password.php" style="color:var(--pacific);text-decoration:none">Esqueci minha senha</a>
                </div>
            </div>
            <button type="submit" class="btn-login">
                Entrar <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        <div class="card-foot">
            PageQuiz · PageUp Sistemas &nbsp;·&nbsp; <?= date('Y') ?><br/>
            Desenvolvido por <strong style="color:var(--gray-400)">Oézios Normando</strong>
        </div>

    </div>

    <div class="login-footer">
        <a href="../index.php">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Voltar ao Quiz
        </a>
    </div>
</div>

</body>
</html>
