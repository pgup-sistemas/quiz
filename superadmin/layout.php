<?php
function superadminHead(string $title, string $active = ''): void {
    $nav = [
        'index.php'     => ['fa-table-columns',    'Dashboard',  'index.php'],
        'companies.php' => ['fa-building',          'Empresas',   'companies.php'],
        'payments.php'  => ['fa-money-bill-wave',   'Pagamentos', 'payments.php'],
        'settings.php'  => ['fa-sliders',           'Config',     'settings.php'],
        'users.php'     => ['fa-users',              'Colaboradores','users.php'],
        'admins.php'    => ['fa-shield-halved',      'S-Admins',   'admins.php'],
        'audit.php'     => ['fa-scroll',             'Auditoria',  'audit.php'],
    ];
    require_once __DIR__ . '/../includes/superadmin-auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title><?= htmlspecialchars($title) ?> · Super Admin · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body { background: #0b1e35; min-height: 100vh; color: var(--gray-700); }
.sa-wrap { max-width: 1200px; margin: 0 auto; padding: 28px 20px; animation: saFadeIn .35s ease both; }
.card { margin-bottom: 24px; background: #fff; }
@keyframes saFadeIn { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform:none; } }
.topbar { background: #05111f; border-bottom: 2px solid var(--yellow); }
.topbar-logo img { mix-blend-mode: screen; }
.topbar-logo-sub { color: var(--yellow) !important; }
.topbar-nav a.active { background: rgba(255,183,3,.15); color: var(--yellow); border-bottom: 2px solid var(--yellow); }
.topbar-nav a:hover  { background: rgba(255,255,255,.07); }
.topbar-nav a i      { width: 16px; text-align: center; }

.badge-plan { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-pro  { background:#fef3c7; color:#92400e; }
.badge-free { background:#e0f2fe; color:#0369a1; }
.badge-pending { background:#fef9c3; color:#854d0e; }
.badge-suspended { background:#fee2e2; color:#991b1b; }
.badge-active { background:#dcfce7; color:#166534; }

.stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
.stat-card { background:#fff; border-radius:var(--radius); padding:20px 18px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.stat-card .sc-label { font-size:12px; color:var(--gray-500); margin-bottom:6px; }
.stat-card .sc-val   { font-size:28px; font-weight:700; color:var(--prussian); font-family:var(--font-heading); }
.stat-card .sc-sub   { font-size:11px; color:var(--gray-400); margin-top:4px; }

.tbl { width:100%; border-collapse:collapse; }
.tbl th { text-align:left; font-size:11px; font-weight:700; color:var(--gray-500); text-transform:uppercase; padding:10px 12px; border-bottom:2px solid var(--gray-200); background:var(--gray-50); }
.tbl td { padding:11px 12px; border-bottom:1px solid var(--gray-100); font-size:14px; vertical-align:middle; }
.tbl tr:hover td { background:var(--gray-50); }
.tbl .actions { display:flex; gap:4px; align-items:center; }
.tbl .btn-xs { padding:4px 10px; font-size:12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px; transition:var(--transition); }
.tbl .btn-xs.primary { background:var(--pacific); color:#fff; }
.tbl .btn-xs.primary:hover { background:var(--blue-dark); }
.tbl .btn-xs.warning { background:var(--orange); color:#fff; }
.tbl .btn-xs.warning:hover { background:#c2680f; }
.tbl .btn-xs.danger  { background:#fee2e2; color:#991b1b; }
.tbl .btn-xs.danger:hover  { background:#fca5a5; }
.tbl .btn-xs.ghost   { background:var(--gray-100); color:var(--gray-600); }
.tbl .btn-xs.ghost:hover   { background:var(--gray-200); }
.tbl .btn-xs.success { background:#dcfce7; color:#166534; }
.tbl .btn-xs.success:hover { background:#bbf7d0; }

.page-header { margin-bottom:24px; display:flex; align-items:center; gap:16px; justify-content:space-between; flex-wrap:wrap; }
.page-header h1 { font-family:var(--font-heading); font-size:22px; color:#fff; margin:0; }
.page-header .sub { font-size:13px; color:var(--gray-400); margin-top:2px; }
</style>
</head>
<body>
<nav class="topbar">
    <a class="topbar-logo" href="index.php">
        <img src="../assets/logo-white.svg" alt="PageUp" style="height:32px"/>
        <div>
            <span class="topbar-logo-text" style="color:#fff">PageQuiz</span>
            <span class="topbar-logo-sub" style="font-size:10px;letter-spacing:.05em">SUPER ADMIN</span>
        </div>
    </a>
    <div class="topbar-nav">
        <?php foreach ($nav as $key => [$icon, $label, $href]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>">
            <i class="fa-solid <?= $icon ?>"></i>
            <span><?= $label ?></span>
        </a>
        <?php endforeach; ?>
        <span class="topbar-user" style="color:var(--gray-300)">
            <i class="fa-solid fa-shield-halved" style="color:var(--yellow);margin-right:4px"></i>
            <?= htmlspecialchars(superAdminName()) ?>
        </span>
        <a href="logout.php" style="color:var(--gray-400)"><i class="fa-solid fa-right-from-bracket"></i> <span>Sair</span></a>
    </div>
</nav>
<?php } ?>

<?php function superadminFoot(): void { ?>
<footer style="text-align:center;padding:16px 20px;margin-top:40px;color:#4a5568;font-size:11px;">
    PageUp Sistemas &nbsp;|&nbsp; <?= date('Y') ?> &nbsp;·&nbsp; Portal Super Admin
</footer>
</body>
</html>
<?php } ?>
