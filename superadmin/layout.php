<?php
function superadminHead(string $title, string $active = ''): void {
    $nav = [
        'index.php'     => ['fa-table-columns',    'Dashboard',  'index.php'],
        'companies.php' => ['fa-building',          'Empresas',   'companies.php'],
        'payments.php'  => ['fa-money-bill-wave',   'Pagamentos', 'payments.php'],
        'settings.php'  => ['fa-sliders',           'Config',     'settings.php'],
        'users.php'     => ['fa-users',              'Colaboradores','users.php'],
        'admins.php'    => ['fa-shield-halved',      'S-Admins',    'admins.php'],
        'analytics.php' => ['fa-chart-line',         'Analytics',   'analytics.php'],
        'audit.php'     => ['fa-scroll',             'Auditoria',   'audit.php'],
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

/* ── Padrao dark do Super Admin ──────────────────────────────────────────
   Redefine as variaveis globais (definidas em assets/style.css) so dentro
   do .sa-wrap, para que TODO estilo inline ja escrito com var(--gray-XXX)/
   var(--prussian) nas paginas herde automaticamente as cores do tema
   escuro, sem precisar editar cada tela individualmente. */
.sa-wrap {
    max-width: 1200px; margin: 0 auto; padding: 28px 20px; animation: saFadeIn .35s ease both;
    --gray-50:  #16304d;
    --gray-100: #1c3a5c;
    --gray-200: #2d4a6a;
    --gray-300: #45607d;
    --gray-400: #7e93a8;
    --gray-500: #9fb2c4;
    --gray-600: #c3d2df;
    --gray-700: #e2e8f0;
    --gray-800: #f8fafc;
    --prussian: #f1f5f9;
}
@keyframes saFadeIn { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform:none; } }
.topbar { background: #05111f; border-bottom: 2px solid var(--yellow); }
.topbar-logo img { mix-blend-mode: screen; }
.topbar-logo-sub { color: var(--yellow) !important; }
.topbar-nav a.active { background: rgba(255,183,3,.15); color: var(--yellow); border-bottom: 2px solid var(--yellow); }
.topbar-nav a:hover  { background: rgba(255,255,255,.07); }
.topbar-nav a i      { width: 16px; text-align: center; }

/* Cards e paineis */
.card, .section-card { margin-bottom: 24px; background: #16304d; border: 1px solid #23415f; box-shadow: 0 1px 4px rgba(0,0,0,.25) !important; }
.section-card-head { border-bottom: 1px solid #23415f !important; }
.card:hover, .section-card:hover { border-color: #2d4a6a; }

.badge-plan { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-pro  { background:rgba(251,191,36,.15); color:#fcd34d; }
.badge-free { background:rgba(33,158,188,.18); color:#7dd3fc; }
.badge-pending { background:rgba(251,191,36,.15); color:#fcd34d; }
.badge-suspended { background:rgba(239,68,68,.15); color:#fca5a5; }
.badge-active { background:rgba(34,197,94,.15); color:#86efac; }

.stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
.stat-card { background:#16304d; border:1px solid #23415f; border-radius:var(--radius); padding:20px 18px; box-shadow:0 1px 4px rgba(0,0,0,.25); }
.stat-card .sc-label { font-size:12px; color:var(--gray-500); margin-bottom:6px; }
.stat-card .sc-val   { font-size:28px; font-weight:700; color:#fff; font-family:var(--font-heading); }
.stat-card .sc-sub   { font-size:11px; color:var(--gray-400); margin-top:4px; }

.tbl { width:100%; border-collapse:collapse; }
.tbl th { text-align:left; font-size:11px; font-weight:700; color:var(--gray-500); text-transform:uppercase; padding:10px 12px; border-bottom:2px solid var(--gray-200); background:#122841; }
.tbl td { padding:11px 12px; border-bottom:1px solid var(--gray-100); font-size:14px; vertical-align:middle; color:var(--gray-700); }
.tbl tr:hover td { background:rgba(255,255,255,.03); }
.tbl .actions { display:flex; gap:4px; align-items:center; }
.tbl .btn-xs { padding:4px 10px; font-size:12px; border:none; border-radius:6px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px; transition:var(--transition); }
.tbl .btn-xs.primary { background:var(--pacific); color:#fff; }
.tbl .btn-xs.primary:hover { background:var(--blue-dark); }
.tbl .btn-xs.warning { background:var(--orange); color:#fff; }
.tbl .btn-xs.warning:hover { background:#c2680f; }
.tbl .btn-xs.danger  { background:rgba(239,68,68,.15); color:#fca5a5; }
.tbl .btn-xs.danger:hover  { background:rgba(239,68,68,.28); }
.tbl .btn-xs.ghost   { background:var(--gray-100); color:var(--gray-600); }
.tbl .btn-xs.ghost:hover   { background:var(--gray-200); }
.tbl .btn-xs.success { background:rgba(34,197,94,.15); color:#86efac; }
.tbl .btn-xs.success:hover { background:rgba(34,197,94,.28); }

.page-header { margin-bottom:24px; display:flex; align-items:center; gap:16px; justify-content:space-between; flex-wrap:wrap; }
.page-header h1 { font-family:var(--font-heading); font-size:22px; color:#fff; margin:0; }
.page-header .sub { font-size:13px; color:var(--gray-400); margin-top:2px; }

/* Alertas globais (assets/style.css) reescritos para o tema escuro do Super Admin */
.sa-wrap .alert         { background: rgba(255,255,255,.05); }
.sa-wrap .alert-success { background: rgba(34,197,94,.12);  border-color: #22c55e; color: #86efac; }
.sa-wrap .alert-error   { background: rgba(239,68,68,.12);  border-color: #ef4444; color: #fca5a5; }
.sa-wrap .alert-info    { background: rgba(33,158,188,.15); border-color: var(--pacific); color: #7dd3fc; }
.sa-wrap .alert-warning { background: rgba(251,191,36,.12); border-color: #fbbf24; color: #fcd34d; }

/* Formularios: inputs/selects claros nao combinam com fundo escuro */
.sa-wrap input[type="text"], .sa-wrap input[type="email"], .sa-wrap input[type="password"],
.sa-wrap input[type="date"], .sa-wrap input[type="number"], .sa-wrap select, .sa-wrap textarea {
    background: #0f1f35 !important;
    color: var(--gray-700) !important;
    border-color: var(--gray-200) !important;
}
.sa-wrap input::placeholder, .sa-wrap textarea::placeholder { color: var(--gray-400); }
.sa-wrap input:focus, .sa-wrap select:focus, .sa-wrap textarea:focus { border-color: var(--pacific) !important; }
.sa-wrap code { background: rgba(255,255,255,.08) !important; color: var(--gray-700); }
.sa-wrap .btn[style*="background:var(--gray-100)"], .sa-wrap .btn[style*="background: var(--gray-100)"] { color: var(--gray-700) !important; }
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
