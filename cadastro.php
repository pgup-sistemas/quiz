<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tenant.php';

$errors   = [];
$success  = false;
$pending  = false;
$tempInfo = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']      ?? '');
    $email   = trim($_POST['email']     ?? '');
    $pass    = $_POST['password']       ?? '';
    $pass2   = $_POST['password_confirm'] ?? '';
    $cnpj    = trim($_POST['cnpj']      ?? '');
    $plan    = in_array($_POST['plan'] ?? '', ['free','pro']) ? $_POST['plan'] : 'free';

    if (!$name)              $errors[] = 'O nome da empresa é obrigatório.';
    if (!$email)             $errors[] = 'O e-mail é obrigatório.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if (strlen($pass) < 8)  $errors[] = 'A senha deve ter ao menos 8 caracteres.';
    if ($pass !== $pass2)   $errors[] = 'As senhas não coincidem.';

    // E-mail único entre admins
    if ($email && dbRow("SELECT id FROM admins WHERE username=?", [$email])) {
        $errors[] = 'Este e-mail já está cadastrado. <a href="admin/login.php" style="color:inherit;font-weight:700">Fazer login →</a>';
    }

    // CNPJ único se preenchido
    if ($cnpj && dbRow("SELECT id FROM companies WHERE cnpj=?", [$cnpj])) {
        $errors[] = 'Este CNPJ/CPF já está vinculado a outra empresa.';
    }

    if (empty($errors)) {
        $slug   = slugUnico($name);
        $status = ($plan === 'pro') ? 'pending_payment' : 'active';
        // Pro solicitado fica com plano free até ativação manual
        $dbPlan = 'free';

        dbExec(
            "INSERT INTO companies (name, slug, email, cnpj, plan, status) VALUES (?,?,?,?,?,?)",
            [$name, $slug, $email, $cnpj ?: null, $dbPlan, $status]
        );
        $companyId = (int)dbLastId();

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        dbExec(
            "INSERT INTO admins (company_id, username, password_hash, name, first_login) VALUES (?,?,?,?,1)",
            [$companyId, $email, $hash, $name]
        );

        if ($plan === 'free') {
            // Login automático e redireciona para onboarding
            $_SESSION['pageup_admin'] = [
                'id'         => (int)dbLastId(),
                'name'       => $name,
                'username'   => $email,
                'company_id' => $companyId,
            ];
            $_SESSION['tenant_company_id'] = $companyId;
            $_SESSION['tenant_company']    = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);
            header('Location: admin/onboarding.php');
            exit;
        } else {
            $pending  = true;
            $tempInfo = ['name' => $name, 'slug' => $slug, 'email' => $email];
        }
    }
}

$freeLimit = (int)(dbRow("SELECT value FROM system_settings WHERE key='free_quiz_limit'")['value'] ?? 12);
$supportEmail = dbRow("SELECT value FROM system_settings WHERE key='support_email'")['value'] ?? 'contato@pageup.net.br';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Criar conta · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg"/>
<link rel="stylesheet" href="assets/style.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
html,body { min-height:100vh; background:#f0f4f8; margin:0; font-family:var(--font-body,'DM Sans',sans-serif); }
.reg-outer { min-height:100vh; display:flex; align-items:flex-start; justify-content:center; padding:40px 20px; }
.reg-box   { background:#fff; border-radius:16px; padding:40px 36px; width:100%; max-width:520px; box-shadow:0 8px 32px rgba(2,48,71,.1); }
.reg-logo  { display:flex; align-items:center; gap:10px; margin-bottom:28px; text-decoration:none; }
.reg-logo-text { font-family:var(--font-heading,'Syne',sans-serif); font-size:22px; font-weight:800; color:var(--prussian,#023047); }
h2 { font-family:var(--font-heading,'Syne',sans-serif); font-size:20px; color:var(--prussian,#023047); margin:0 0 6px; }
.sub-text { font-size:13px; color:var(--gray-500,#6b7280); margin:0 0 24px; }
.fg { margin-bottom:16px; }
.fg label { display:block; font-size:13px; font-weight:600; color:var(--gray-700,#374151); margin-bottom:6px; }
.fg input { width:100%; box-sizing:border-box; padding:10px 14px; border:1.5px solid var(--gray-200,#e5e7eb); border-radius:8px; font-size:14px; font-family:inherit; transition:.2s; }
.fg input:focus { outline:none; border-color:var(--pacific,#219EBC); box-shadow:0 0 0 3px rgba(33,158,188,.15); }
.plan-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
.plan-card { border:2px solid var(--gray-200,#e5e7eb); border-radius:12px; padding:18px 16px; cursor:pointer; transition:.2s; position:relative; }
.plan-card:has(input:checked) { border-color:var(--pacific,#219EBC); background:#f0f9ff; }
.plan-card input[type=radio] { position:absolute; opacity:0; }
.plan-card .plan-name { font-weight:700; font-size:15px; color:var(--prussian,#023047); margin-bottom:6px; }
.plan-card .plan-price { font-size:12px; color:var(--gray-500,#6b7280); margin-bottom:10px; }
.plan-card ul { margin:0; padding:0 0 0 16px; font-size:12px; color:var(--gray-600,#4b5563); line-height:1.7; }
.plan-card .plan-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; margin-bottom:8px; }
.plan-card.free-card .plan-badge { background:#e0f2fe; color:#0369a1; }
.plan-card.pro-card .plan-badge  { background:#fef3c7; color:#92400e; }
.plan-card.pro-card { border-color:#fbbf24; }
.plan-card.pro-card:has(input:checked) { background:#fffbeb; border-color:#f59e0b; }
.btn-reg { width:100%; padding:13px; background:var(--prussian,#023047); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; transition:.2s; margin-top:8px; }
.btn-reg:hover { background:#012336; }
.alert-err { background:#fee2e2; color:#991b1b; border-radius:8px; padding:12px 16px; font-size:13px; margin-bottom:16px; }
.alert-err li { margin-bottom:4px; }
.divider { border:none; border-top:1px solid var(--gray-100,#f3f4f6); margin:20px 0; }
.login-link { text-align:center; font-size:13px; color:var(--gray-500,#6b7280); }
.login-link a { color:var(--pacific,#219EBC); font-weight:600; text-decoration:none; }
.pending-box { text-align:center; padding:16px 0; }
.pending-box .big-icon { font-size:56px; color:var(--yellow,#FFB703); margin-bottom:16px; }
.pending-box h2 { margin-bottom:8px; }
.pending-box p  { color:var(--gray-500,#6b7280); font-size:14px; line-height:1.6; margin-bottom:20px; }
</style>
</head>
<body>
<div class="reg-outer">
<div class="reg-box">
    <a class="reg-logo" href="index.php">
        <img src="assets/logo-color.svg" alt="PageUp" style="height:36px" onerror="this.style.display='none'"/>
        <span class="reg-logo-text">PageQuiz</span>
    </a>

    <?php if ($pending): ?>
    <div class="pending-box">
        <div class="big-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <h2>Pro solicitado!</h2>
        <p>Sua empresa <strong><?= htmlspecialchars($tempInfo['name']) ?></strong> foi cadastrada com sucesso.<br>
           Entraremos em contato pelo e-mail <strong><?= htmlspecialchars($tempInfo['email']) ?></strong> para ativar o plano Pro.<br><br>
           Enquanto isso, você pode começar a usar no plano Free.
        </p>
        <a href="admin/login.php" class="btn-reg" style="display:inline-block;text-decoration:none;text-align:center">
            <i class="fa-solid fa-right-to-bracket"></i> Acessar meu painel (plano Free)
        </a>
        <div style="margin-top:16px;font-size:13px;color:var(--gray-500)">
            Dúvidas? Entre em contato: <a href="mailto:<?= htmlspecialchars($supportEmail) ?>"><?= htmlspecialchars($supportEmail) ?></a>
        </div>
    </div>
    <?php else: ?>

    <h2>Criar conta gratuita</h2>
    <p class="sub-text">Configure sua empresa e comece em minutos.</p>

    <?php if ($errors): ?>
    <div class="alert-err"><ul>
        <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
    </ul></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="fg">
            <label>Nome da empresa *</label>
            <input type="text" name="name" required autofocus maxlength="120"
                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Ex.: Clínica São João"/>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="fg">
                <label>E-mail do responsável *</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="voce@empresa.com"/>
            </div>
            <div class="fg">
                <label>CNPJ/CPF <small style="font-weight:400;color:var(--gray-400)">(opcional)</small></label>
                <input type="text" name="cnpj" maxlength="18"
                       value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>" placeholder="00.000.000/0001-00"/>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="fg">
                <label>Senha *</label>
                <input type="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres"/>
            </div>
            <div class="fg">
                <label>Confirmar senha *</label>
                <input type="password" name="password_confirm" required placeholder="Repita a senha"/>
            </div>
        </div>

        <hr class="divider"/>
        <div style="margin-bottom:12px;font-size:13px;font-weight:600;color:var(--gray-700)">Escolha seu plano:</div>

        <div class="plan-grid">
            <label class="plan-card free-card">
                <input type="radio" name="plan" value="free" <?= ($_POST['plan'] ?? 'free')==='free'?'checked':'' ?>/>
                <div class="plan-badge">Free</div>
                <div class="plan-name">Gratuito</div>
                <div class="plan-price">Sem custo · sempre</div>
                <ul>
                    <li>Até <?= $freeLimit ?> quizzes ativos</li>
                    <li>Usuários ilimitados</li>
                    <li>Certificado padrão</li>
                    <li>Subdomínio próprio</li>
                </ul>
            </label>
            <label class="plan-card pro-card">
                <input type="radio" name="plan" value="pro" <?= ($_POST['plan'] ?? '')==='pro'?'checked':'' ?>/>
                <div class="plan-badge"><i class="fa-solid fa-star"></i> Pro</div>
                <div class="plan-name">Profissional</div>
                <div class="plan-price">Ativação manual pela PageUp</div>
                <ul>
                    <li>Quizzes ilimitados</li>
                    <li>Usuários ilimitados</li>
                    <li>Certificado personalizado</li>
                    <li>Logo e cor da empresa</li>
                    <li>Subdomínio próprio</li>
                </ul>
            </label>
        </div>

        <button type="submit" class="btn-reg">
            <i class="fa-solid fa-rocket"></i> Criar minha conta
        </button>
    </form>

    <hr class="divider"/>
    <div class="login-link">
        Já tem conta? <a href="admin/login.php">Fazer login →</a>
    </div>

    <?php endif; ?>
</div>
</div>
</body>
</html>
