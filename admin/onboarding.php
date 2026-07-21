<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
sessionStart();
requireLogin();

$companyId = adminCompanyId();
$company   = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);
$step      = (int)($_GET['step'] ?? 1);
$msg       = '';

// Se admin já fez onboarding, redireciona
$admin = dbRow("SELECT * FROM admins WHERE id=?", [adminId()]);
if ($admin && !$admin['first_login']) {
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1') {
        $displayName = trim($_POST['display_name'] ?? '');
        if ($displayName) {
            dbExec("UPDATE companies SET name=?, updated_at=NOW() WHERE id=?",
                   [$displayName, $companyId]);
        }
        header('Location: onboarding.php?step=2'); exit;

    } elseif ($action === 'step2') {
        if ($company['plan'] === 'pro') {
            // Upload de logo
            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','svg','webp'])) {
                    $dir = __DIR__ . "/../uploads/companies/$companyId";
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $dest = "$dir/logo.$ext";
                    move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
                    dbExec("UPDATE companies SET logo_path=?, updated_at=NOW() WHERE id=?",
                           ["uploads/companies/$companyId/logo.$ext", $companyId]);
                }
            }
            // Cor primária
            $color = trim($_POST['primary_color'] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                dbExec("UPDATE companies SET primary_color=?, updated_at=NOW() WHERE id=?",
                       [$color, $companyId]);
            }
        }
        header('Location: onboarding.php?step=3'); exit;

    } elseif ($action === 'finish') {
        dbExec("UPDATE admins SET first_login=0 WHERE id=?", [adminId()]);
        header('Location: index.php'); exit;
    }
}

// Recarregar empresa após possíveis updates
$company = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);
$isPro   = $company['plan'] === 'pro';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Configurar empresa · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
html,body { min-height:100vh; background:#0b1e35; margin:0; font-family:var(--font-body,'DM Sans',sans-serif); display:flex; align-items:center; justify-content:center; }
.ob-box  { background:#fff; border-radius:16px; padding:40px 36px; width:100%; max-width:500px; box-shadow:0 8px 32px rgba(2,48,71,.1); margin:40px 20px; }
.steps   { display:flex; gap:8px; margin-bottom:32px; }
.step-dot{ flex:1; height:4px; border-radius:2px; background:var(--gray-200,#e5e7eb); }
.step-dot.done { background:var(--pacific,#219EBC); }
h2 { font-family:var(--font-heading,'Syne',sans-serif); font-size:20px; color:var(--prussian,#023047); margin:0 0 8px; }
.sub { font-size:13px; color:var(--gray-500,#6b7280); margin:0 0 28px; }
.fg { margin-bottom:16px; }
.fg label { display:block; font-size:13px; font-weight:600; color:var(--gray-700,#374151); margin-bottom:6px; }
.fg input[type=text],.fg input[type=email] { width:100%; box-sizing:border-box; padding:10px 14px; border:1.5px solid var(--gray-200,#e5e7eb); border-radius:8px; font-size:14px; font-family:inherit; }
.btn-ob { width:100%; padding:13px; background:var(--prussian,#023047); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:700; font-family:inherit; cursor:pointer; margin-top:8px; }
.skip   { text-align:center; margin-top:12px; font-size:13px; }
.skip a { color:var(--gray-400,#9ca3af); text-decoration:none; }
.upgrade-box { background:#fffbeb; border:2px solid #fbbf24; border-radius:12px; padding:20px; text-align:center; margin-bottom:20px; }
.upgrade-box .icon { font-size:40px; margin-bottom:12px; }
.upgrade-box h3 { font-size:16px; color:var(--prussian,#023047); margin:0 0 8px; font-family:var(--font-heading,'Syne',sans-serif); }
.upgrade-box p  { font-size:13px; color:var(--gray-600,#4b5563); margin:0 0 16px; }
.btn-upgrade { display:inline-block; padding:10px 20px; background:#f59e0b; color:#fff; border-radius:8px; font-weight:700; text-decoration:none; font-size:13px; }
</style>
</head>
<body>
<div class="ob-box">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px">
        <img src="../assets/logo-color.svg" style="height:32px" alt="PageUp" onerror="this.style.display='none'"/>
        <span style="font-family:var(--font-heading,'Syne',sans-serif);font-size:18px;font-weight:800;color:var(--prussian,#023047)">PageQuiz</span>
    </div>

    <!-- Barra de progresso -->
    <div class="steps">
        <div class="step-dot <?= $step >= 1 ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step >= 2 ? 'done' : '' ?>"></div>
        <div class="step-dot <?= $step >= 3 ? 'done' : '' ?>"></div>
    </div>

    <?php if ($step === 1): ?>
    <h2><i class="fa-solid fa-hand-wave" style="color:var(--pacific,#219EBC)"></i> Bem-vindo!</h2>
    <p class="sub">Vamos configurar sua empresa em 3 passos rápidos.</p>
    <form method="POST">
        <input type="hidden" name="action" value="step1"/>
        <div class="fg">
            <label>Nome de exibição da empresa</label>
            <input type="text" name="display_name" maxlength="120"
                   value="<?= htmlspecialchars($company['name']) ?>" placeholder="Como seu nome deve aparecer para os usuários"/>
        </div>
        <button type="submit" class="btn-ob"><i class="fa-solid fa-arrow-right"></i> Próximo</button>
    </form>

    <?php elseif ($step === 2): ?>
    <h2>Identidade visual</h2>
    <p class="sub">
        <?= $isPro ? 'Configure o logo e a cor da sua empresa.' : 'Este recurso está disponível no plano Pro.' ?>
    </p>

    <?php if (!$isPro): ?>
    <div class="upgrade-box">
        <div class="icon"><i class="fa-solid fa-palette" style="font-size:40px;color:#f59e0b"></i></div>
        <h3>Logo e cores personalizadas</h3>
        <p>Com o plano Pro, seus usuários verão o logo e as cores da sua empresa em todas as páginas do quiz.</p>
        <a href="upgrade.php" class="btn-upgrade"><i class="fa-solid fa-star"></i> Fazer Upgrade para Pro</a>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="step2"/>
        <button type="submit" class="btn-ob" style="background:var(--gray-400,#9ca3af)">
            <i class="fa-solid fa-arrow-right"></i> Continuar com o Free
        </button>
    </form>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="step2"/>
        <div class="fg">
            <label>Logo da empresa <small style="font-weight:400;color:var(--gray-400)">(JPG, PNG, SVG, WebP)</small></label>
            <input type="file" name="logo" accept="image/jpeg,image/png,image/svg+xml,image/webp"
                   style="width:100%;padding:10px;border:1.5px dashed var(--gray-200);border-radius:8px;font-size:13px"/>
            <?php if ($company['logo_path']): ?>
            <div style="margin-top:8px;font-size:12px;color:var(--gray-400)">
                Logo atual: <?= htmlspecialchars(basename($company['logo_path'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="fg">
            <label>Cor primária</label>
            <div style="display:flex;gap:10px;align-items:center">
                <input type="color" name="primary_color" value="<?= htmlspecialchars($company['primary_color']) ?>"
                       style="width:50px;height:40px;padding:2px;border:1.5px solid var(--gray-200);border-radius:8px;cursor:pointer"/>
                <input type="text" id="color-text" value="<?= htmlspecialchars($company['primary_color']) ?>"
                       maxlength="7" style="width:100px;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"
                       onchange="document.querySelector('[name=primary_color]').value=this.value"/>
            </div>
        </div>
        <button type="submit" class="btn-ob"><i class="fa-solid fa-arrow-right"></i> Próximo</button>
    </form>
    <?php endif; ?>

    <?php elseif ($step === 3): ?>
    <h2>Tudo pronto!</h2>
    <p class="sub">Sua empresa está configurada. Agora você pode criar seus primeiros quizzes.</p>
    <div style="background:var(--gray-50,#f9fafb);border-radius:12px;padding:20px;margin-bottom:24px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
            <div style="width:48px;height:48px;background:var(--pacific,#219EBC);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-family:var(--font-heading,'Syne',sans-serif);font-weight:800">
                <?= mb_strtoupper(mb_substr($company['name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight:700;color:var(--prussian,#023047)"><?= htmlspecialchars($company['name']) ?></div>
                <div style="font-size:12px;color:var(--gray-400)">slug: <?= htmlspecialchars($company['slug']) ?></div>
            </div>
        </div>
        <ul style="margin:0;padding-left:20px;font-size:13px;color:var(--gray-600,#4b5563);line-height:1.8">
            <li>Plano: <strong><?= $isPro ? 'Pro' : 'Free' ?></strong></li>
            <?php if (!$isPro): ?>
            <li>Quizzes disponíveis: até <strong><?= dbRow("SELECT value FROM system_settings WHERE `key`='free_quiz_limit'")['value'] ?? 12 ?></strong></li>
            <?php else: ?>
            <li>Quizzes: <strong>ilimitados</strong></li>
            <?php endif; ?>
        </ul>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="finish"/>
        <button type="submit" class="btn-ob">
            <i class="fa-solid fa-gauge"></i> Ir para o painel
        </button>
    </form>
    <?php endif; ?>

    <?php if ($step < 3): ?>
    <div class="skip">
        <a href="onboarding.php?step=3" onclick="document.querySelector('[name=action]').value='finish';document.querySelector('form').submit();return false">
            Pular configuração por agora
        </a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
