<?php
// Usage: adminHead('Page Title');
function adminHead(string $title, string $activeNav = ''): void {
    $nav = [
        'index.php'    => ['fa-table-columns', 'Dashboard',  'index.php'],
        'quizzes.php'  => ['fa-list-check',    'Quizzes',    'quizzes.php'],
        'sectors.php'  => ['fa-sitemap',       'Setores',    'sectors.php'],
        'results.php'  => ['fa-chart-pie',     'Resultados', 'results.php'],
        'live.php'     => ['fa-tower-broadcast','Ao Vivo',    'live.php'],
        'settings.php' => ['fa-sliders',       'Config',     'settings.php'],
        'manual.php'   => ['fa-circle-info',   'Manual',     'manual.php'],
    ];
    $flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#0d1b35"/>
<title><?= htmlspecialchars($title) ?> · Admin · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body { background: var(--gray-100); min-height:100vh; }
.admin-wrap { max-width:1100px; margin:0 auto; padding:28px 20px; animation: adminFadeIn .4s ease both; }
.card { margin-bottom:24px; }
@keyframes adminFadeIn { from { opacity:0; transform: translateY(12px); } to { opacity:1; transform:none; } }

/* Topbar */
.topbar { background: var(--prussian); }
.topbar-logo img { mix-blend-mode: screen; background: transparent; }
.topbar-nav a.active { background: rgba(255,255,255,.15); color: #fff; border-bottom: 2px solid var(--yellow); }
.topbar-nav a:hover  { background: rgba(255,255,255,.08); }
.topbar-nav a i      { width: 16px; text-align: center; }

/* Row actions (tabela de quizzes) */
.row-actions { display: flex; align-items: center; gap: 2px; justify-content: flex-end; }
.row-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    color: var(--gray-400);
    text-decoration: none;
    font-size: 14px;
    transition: var(--transition);
}
.row-action:hover          { color: var(--pacific);  background: var(--sky-light); }
.row-action:focus-visible  { outline: 2px solid var(--pacific); outline-offset: 2px; }
.row-action--danger:hover  { color: var(--orange);  background: rgba(251,133,0,.1); }
.row-action--success:hover { color: var(--green);   background: rgba(0,184,148,.1); }
.row-action--delete        { color: var(--gray-300); }
.row-action--delete:hover  { color: var(--red);     background: rgba(214,48,49,.1); }

/* Micro-modal de confirmação */
.confirm-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(2,48,71,.6);
    z-index: 500;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.confirm-backdrop.open { display: flex; }
.confirm-modal {
    background: #fff;
    border-radius: var(--radius);
    padding: 28px 32px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(2,48,71,.25);
    animation: confirmIn .2s ease;
}
@keyframes confirmIn { from { opacity:0; transform: scale(.96) translateY(8px); } to { opacity:1; transform: none; } }
.confirm-modal h3 {
    font-family: var(--font-heading);
    font-size: var(--text-lg);
    color: var(--prussian);
    margin-bottom: 10px;
}
.confirm-modal p {
    font-size: var(--text-sm);
    color: var(--gray-500);
    line-height: 1.6;
    margin-bottom: 24px;
}
.confirm-actions { display: flex; gap: 10px; justify-content: flex-end; }
.confirm-cancel {
    padding: 10px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: none;
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
}
.confirm-cancel:hover { background: var(--gray-200); }
.confirm-ok {
    padding: 10px 20px;
    background: var(--orange);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
}
.confirm-ok:hover { background: #d97300; }
.confirm-ok.pacific { background: var(--pacific); }
.confirm-ok.pacific:hover { background: var(--blue-dark); }
</style>
</head>
<body>
<nav class="topbar">
    <a class="topbar-logo" href="index.php">
        <img src="../assets/logo-white.svg" alt="PageUp"/>
        <div>
            <span class="topbar-logo-text">PageQuiz</span>
            <span class="topbar-logo-sub">Área Administrativa</span>
        </div>
    </a>
    <div class="topbar-nav">
        <?php foreach ($nav as $key => [$icon, $label, $href]): ?>
        <a href="<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>">
            <i class="fa-solid <?= $icon ?>"></i>
            <span><?= $label ?></span>
        </a>
        <?php endforeach; ?>
        <span class="topbar-user"><i class="fa-solid fa-user-circle" style="margin-right:4px"></i><?= htmlspecialchars(adminName()) ?></span>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> <span>Sair</span></a>
    </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="admin-wrap" style="padding-bottom:0">
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?> shadow-sm" id="global-alert">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <span><?= htmlspecialchars($flash['msg']) ?></span>
    </div>
</div>
<?php if ($flash['type'] === 'success'): ?>
<script>
setTimeout(() => {
    const a = document.getElementById('global-alert');
    if(a) { a.style.transition='opacity .5s'; a.style.opacity='0'; setTimeout(()=>a.remove(), 500); }
}, 4000);
</script>
<?php endif; ?>
<?php endif; ?>
<?php } ?>
<?php function adminFoot(): void { ?>

<!-- Micro-modal de confirmação -->
<div class="confirm-backdrop" id="confirm-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="confirm-modal">
        <h3 id="confirm-title">Confirmar ação</h3>
        <p id="confirm-msg">Tem certeza?</p>
        <div class="confirm-actions">
            <button class="confirm-cancel" id="confirm-cancel">Cancelar</button>
            <button class="confirm-ok" id="confirm-ok">Confirmar</button>
        </div>
    </div>
</div>

<footer style="text-align:center; padding: 16px 20px; margin-top: 40px; color: var(--gray-400); font-size: 11px; display:flex; flex-direction:column; align-items:center; gap:4px; opacity: 0.7;">
    <div>PageUp Sistemas &nbsp;|&nbsp; <?= date('Y') ?></div>
    <div style="font-weight: 400;">Desenvolvido por <span style="color:var(--gray-500)">PageUp Sistemas</span> — <span style="color:var(--gray-500)">Oézios Normando</span></div>
    <div style="margin-top: 4px;">
        <a href="https://wa.me/5569993882222" target="_blank" style="color: #cbd5e0; font-size: 18px; text-decoration: none;" title="Fale conosco no WhatsApp">
            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
        </a>
    </div>
</footer>

<script>
(function() {
    let _resolve = null;

    const backdrop = document.getElementById('confirm-backdrop');
    const msgEl    = document.getElementById('confirm-msg');
    const okBtn    = document.getElementById('confirm-ok');
    const cancelBtn = document.getElementById('confirm-cancel');

    if (!backdrop) return;

    function closeModal(result) {
        backdrop.classList.remove('open');
        if (_resolve) { _resolve(result); _resolve = null; }
    }

    okBtn.addEventListener('click',     () => closeModal(true));
    cancelBtn.addEventListener('click', () => closeModal(false));
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(false); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal(false);
    });

    window.confirmAction = function(msg, danger = true) {
        return new Promise(resolve => {
            _resolve = resolve;
            msgEl.textContent = msg;
            okBtn.className = 'confirm-ok' + (danger ? '' : ' pacific');
            backdrop.classList.add('open');
            okBtn.focus();
        });
    };

    /* Intercept all inline onclick="return confirmAction(...)" on links */
    document.addEventListener('click', async (e) => {
        const link = e.target.closest('a[onclick]');
        if (!link) return;
        const onclickAttr = link.getAttribute('onclick') || '';
        const match = onclickAttr.match(/return confirmAction\((['"])(.*?)\1/);
        if (!match) return;
        e.preventDefault();
        const confirmed = await window.confirmAction(match[2]);
        if (confirmed) window.location.href = link.href;
    });
})();
</script>
</body>
</html>
<?php } ?>
