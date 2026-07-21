<?php
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/seo.php';

userSessionStart();
if (!isUserLoggedIn()) {
    header('Location: login.php?redirect=dashboard.php');
    exit;
}

$user   = currentUser();
$tenant = resolveTenant(); // mantém a sessão do tenant consistente

// company_id gravado no login é a fonte de verdade para usuários autenticados.
// Não confiamos em ?c= nem no subdomínio para determinar a empresa do colaborador.
$cid = (int)($user['company_id'] ?? 0);

// Validação defensiva: se o tenant da URL for de empresa diferente, ignora o tenant.
if ($tenant && $cid > 0 && (int)$tenant['id'] !== $cid) {
    $tenant = null;
}

// ── Quizzes disponíveis (filtrado por visibility + quiz_assignments) ──────────
$userSector = $user['sector'] ?? '';

if ($cid) {
    // Resolve sector_id do setor do usuário nesta empresa
    $userSectorRow = $userSector
        ? dbRow("SELECT id FROM sectors WHERE name = ? AND company_id = ?", [$userSector, $cid])
        : null;
    $userSectorId = $userSectorRow ? (int)$userSectorRow['id'] : 0;

    $available = dbRows("
        SELECT DISTINCT q.id, q.title, q.description, q.sector, q.time_per_question,
               q.pass_percentage, q.has_certificate, q.visibility,
               (SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id) AS question_count
        FROM quizzes q
        WHERE q.active = 1 AND q.company_id = ?
          AND (q.expires_at IS NULL OR q.expires_at = '' OR q.expires_at >= date('now','localtime'))
          AND (
              q.visibility = 'all'
              OR (q.visibility = 'sector' AND EXISTS (
                  SELECT 1 FROM quiz_assignments qa WHERE qa.quiz_id = q.id AND qa.sector_id = ?
              ))
          )
        ORDER BY q.created_at DESC
    ", [$cid, $userSectorId]);
} else {
    // Colaborador sem company_id não deve ver quizzes de outras empresas.
    $available = [];
}

// ── Histórico de participações ─────────────────────────────────────────────────
// Critério primário: user_id (vinculado ao criar sessão quando logado)
// Fallback:         email igual ao da conta (retrocompat com registros antigos)
$uid        = (int)$user['id'];
$histPage   = max(1, (int)($_GET['hp'] ?? 1));
$histPerPg  = 10;
$histOffset = ($histPage - 1) * $histPerPg;

if ($cid) {
    $histTotal = (int)(dbRow("
        SELECT COUNT(*) AS c FROM participants p
        JOIN quizzes q ON q.id = p.quiz_id
        WHERE (p.user_id = ? OR (p.user_id IS NULL AND p.email != '' AND p.email = ?))
          AND q.company_id = ? AND p.completed_at IS NOT NULL
    ", [$uid, $user['email'], $cid])['c'] ?? 0);

    $history = dbRows("
        SELECT p.*, q.title AS quiz_title, q.pass_percentage AS pass_pct, q.has_certificate
        FROM participants p
        JOIN quizzes q ON q.id = p.quiz_id
        WHERE (p.user_id = ? OR (p.user_id IS NULL AND p.email != '' AND p.email = ?))
          AND q.company_id = ?
          AND p.completed_at IS NOT NULL
        ORDER BY p.completed_at DESC
        LIMIT $histPerPg OFFSET $histOffset
    ", [$uid, $user['email'], $cid]);
} else {
    $histTotal = 0;
    $history   = [];
}
$histPages = (int)ceil($histTotal / $histPerPg);

// Stats sobre TODOS os resultados (não só a página atual)
$histStats = $cid ? dbRow("
    SELECT COUNT(*) AS total_done, SUM(p.passed) AS total_passed,
           AVG(p.percentage) AS avg_pct
    FROM participants p JOIN quizzes q ON q.id=p.quiz_id
    WHERE (p.user_id=? OR (p.user_id IS NULL AND p.email!='' AND p.email=?))
      AND q.company_id=? AND p.completed_at IS NOT NULL
", [$uid, $user['email'], $cid]) : null;

$totalDone   = (int)($histStats['total_done']   ?? 0);
$totalPassed = (int)($histStats['total_passed'] ?? 0);
$avgPct      = $histStats ? round((float)($histStats['avg_pct'] ?? 0)) : 0;

// IDs de quizzes já feitos pelo usuário (para badge "Feito") — busca todos, não só a página atual
$doneIds = $cid ? array_column(dbRows(
    "SELECT DISTINCT p.quiz_id FROM participants p JOIN quizzes q ON q.id=p.quiz_id
     WHERE (p.user_id=? OR (p.user_id IS NULL AND p.email!='' AND p.email=?))
       AND q.company_id=? AND p.completed_at IS NOT NULL",
    [$uid, $user['email'], $cid]
), 'quiz_id') : [];

// ── Profile update ────────────────────────────────────────────────────────────
$profileMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name   = trim($_POST['name']   ?? '');
    $sector = trim($_POST['sector'] ?? '');
    if ($name) {
        userUpdateProfile($user['id'], $name, $sector);
        $profileMsg = 'Perfil atualizado com sucesso!';
        $user = currentUser();
    }
}

// ── Password change ───────────────────────────────────────────────────────────
$passMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $cur  = $_POST['current_pass'] ?? '';
    $new  = $_POST['new_pass']     ?? '';
    $conf = $_POST['conf_pass']    ?? '';
    if (!$cur || !$new) {
        $passMsg = 'err:Preencha todos os campos.';
    } elseif (strlen($new) < 6) {
        $passMsg = 'err:Senha deve ter mínimo 6 caracteres.';
    } elseif ($new !== $conf) {
        $passMsg = 'err:As senhas não conferem.';
    } elseif (userChangePassword($user['id'], $cur, $new)) {
        $passMsg = 'ok:Senha alterada com sucesso!';
    } else {
        $passMsg = 'err:Senha atual incorreta.';
    }
}

$sectors = $cid
    ? dbRows("SELECT name FROM sectors WHERE company_id = ? ORDER BY name ASC", [$cid])
    : dbRows("SELECT name FROM sectors ORDER BY name ASC");

// Nome da empresa: usa o tenant se disponível, senão busca pelo company_id do usuário
if ($tenant) {
    $orgName = htmlspecialchars($tenant['name']);
} elseif ($cid) {
    $orgRow  = dbRow("SELECT name FROM companies WHERE id = ?", [$cid]);
    $orgName = $orgRow ? htmlspecialchars($orgRow['name']) : 'PageQuiz';
} else {
    $orgName = 'PageQuiz';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Meu Painel · <?= $orgName ?></title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="manifest" href="/manifest.json"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<?php
$_coRow = $cid ? dbRow("SELECT primary_color, logo_path FROM companies WHERE id = ?", [$cid]) : null;
if ($_coRow && !empty($_coRow['primary_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $_coRow['primary_color'])):
    $_brandColor = $_coRow['primary_color'];
?>
<style>:root{--pacific:<?= $_brandColor ?>;--blue:<?= $_brandColor ?>;}</style>
<?php endif; ?>
<style>
body { background: #0b1e35; font-family: 'DM Sans', sans-serif; margin: 0; }

/* ── Navbar — tonalidade alinhada ao template Super Admin ── */
.dash-nav {
    background: #05111f;
    border-bottom: 2px solid var(--yellow);
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 56px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
}
.dash-nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.dash-nav-logo img { height: 28px; }
.dash-nav-brand { color: #fff; font-size: 16px; font-weight: 700; }
.dash-nav-brand span { color: var(--yellow); }
.dash-nav-right { display: flex; align-items: center; gap: 6px; }
.dash-nav-right a {
    color: rgba(255,255,255,.75);
    font-size: 13px;
    text-decoration: none;
    padding: 7px 12px;
    border-radius: 8px;
    transition: .2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.dash-nav-right a:hover { color: var(--yellow); background: rgba(255,183,3,.15); }
.dash-nav-right .btn-out { border: 1px solid rgba(255,255,255,.2); }

/* ── Layout ── */
.wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px; }

/* ── Header (texto solto sobre o fundo escuro) ── */
.dash-header { margin-bottom: 28px; }
.dash-greeting { font-size: 22px; font-weight: 800; color: #fff; font-family: 'Syne', sans-serif; }
.dash-greeting span { color: var(--yellow); }
.dash-sub { font-size: 13px; color: rgba(255,255,255,.6); margin-top: 6px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.dash-sub-item { display: flex; align-items: center; gap: 5px; }

/* ── Stats ── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}
.stat-box {
    background: #fff;
    border-radius: 14px;
    padding: 20px 16px;
    text-align: center;
    border: 1px solid #e2edf2;
    box-shadow: 0 2px 8px rgba(2,48,71,.04);
}
.stat-box .val {
    font-size: 30px;
    font-weight: 800;
    color: var(--prussian);
    font-family: 'Syne', sans-serif;
    line-height: 1;
}
.stat-box .lbl {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--gray-400);
    margin-top: 6px;
}
.stat-box.s-pass .val { color: #00875a; }
.stat-box.s-avg  .val { color: var(--pacific); }

/* ── Cards ── */
.card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2edf2;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(2,48,71,.04);
}
.card-hd {
    padding: 16px 24px;
    border-bottom: 1px solid #eef2f5;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.card-hd h2 {
    font-size: 15px;
    font-weight: 700;
    color: var(--prussian);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.card-hd h2 i { color: var(--pacific); }
.card-body { padding: 20px 24px; }

/* ── Quiz grid ── */
.quiz-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    padding: 20px 24px;
}
.quiz-card {
    border: 1.5px solid #e2edf2;
    border-radius: 14px;
    padding: 18px;
    transition: border-color .2s, box-shadow .2s, transform .15s;
    cursor: pointer;
    text-decoration: none;
    display: block;
    position: relative;
    background: #fff;
}
.quiz-card:hover {
    border-color: var(--pacific);
    box-shadow: 0 4px 16px rgba(33,158,188,.15);
    transform: translateY(-2px);
}
.quiz-card-sector {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .7px;
    color: var(--pacific);
    margin-bottom: 8px;
}
.quiz-card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--prussian);
    margin-bottom: 10px;
    line-height: 1.4;
}
.quiz-card-desc {
    font-size: 12px;
    color: var(--gray-500);
    margin-bottom: 14px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.quiz-card-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 11px;
    color: var(--gray-400);
    font-weight: 600;
}
.quiz-card-meta i { color: var(--pacific); font-size: 11px; }
.quiz-badge-done {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #e6fffa;
    color: #00875a;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.quiz-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    margin-top: 14px;
    padding: 9px;
    background: var(--pacific);
    color: #fff;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background .2s;
}
.quiz-btn:hover { background: var(--prussian); color: #fff; }
.quiz-btn.done { background: #f0f4f7; color: var(--pacific); }
.quiz-btn.done:hover { background: var(--pacific); color: #fff; }

/* ── Histórico ── */
.history-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 8px;
    border-bottom: 1px solid #f0f4f7;
    gap: 12px;
    border-radius: 8px;
    margin: 0 -8px;
    transition: background .15s;
    cursor: pointer;
}
.history-row:hover { background: #f0f7fa; }
.history-row:last-child { border-bottom: none; }
.history-title { font-size: 13px; font-weight: 700; color: var(--prussian); margin-bottom: 3px; }
.history-meta { font-size: 11px; color: var(--gray-400); }
.badge-pass { background: #e6fffa; color: #00875a; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
.badge-fail { background: #fff5f5; color: #c53030; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
.badge-pct { font-size: 16px; font-weight: 800; color: var(--prussian); margin-right: 4px; }
.cert-link { color: var(--pacific); font-size: 13px; text-decoration: none; }
.cert-link:hover { color: var(--prussian); }

/* ── Forms ── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--gray-500); margin-bottom: 6px; }
.form-control { width: 100%; padding: 11px 14px; border: 1.5px solid #dce8ef; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--prussian); outline: none; transition: .2s; background: #fff; box-sizing: border-box; }
.form-control:focus { border-color: var(--pacific); box-shadow: 0 0 0 3px rgba(33,158,188,.10); }
.btn-save { padding: 11px 24px; background: var(--pacific); color: #fff; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 700; cursor: pointer; transition: .2s; }
.btn-save:hover { background: var(--prussian); }
.msg-ok  { font-size: 13px; color: #276749; background: #f0fff4; border: 1px solid #9ae6b4; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.msg-err { font-size: 13px; color: #c53030; background: #fff5f5; border: 1px solid #fed7d7; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.empty-state { text-align: center; padding: 40px 24px; color: var(--gray-400); font-size: 14px; line-height: 1.7; }
.empty-state i { font-size: 40px; display: block; margin-bottom: 12px; color: var(--gray-200); }

/* ── Nav avatar ── */
.nav-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--pacific); color: #fff;
    font-size: 12px; font-weight: 800; font-family: 'Syne', sans-serif;
    border: 2px solid rgba(255,255,255,.3);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: border-color .2s, transform .15s;
    flex-shrink: 0;
}
.nav-avatar:hover { border-color: #fff; transform: scale(1.08); }

/* ── Modal ── */
.modal-backdrop {
    display: none; position: fixed; inset: 0;
    background: rgba(2,48,71,.45); backdrop-filter: blur(3px);
    z-index: 1000; align-items: flex-start; justify-content: flex-end;
    padding: 64px 16px 0;
}
.modal-backdrop.open { display: flex; }
.modal-box {
    background: #fff; border-radius: 16px;
    box-shadow: 0 12px 48px rgba(2,48,71,.22);
    width: 100%; max-width: 420px;
    animation: modalSlide .2s ease both;
}
@keyframes modalSlide {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: none; }
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #eef2f5; padding: 6px 16px 0;
}
.modal-tabs { display: flex; gap: 2px; }
.modal-tab {
    padding: 11px 14px; background: none; border: none;
    border-bottom: 2px solid transparent; font-family: 'DM Sans', sans-serif;
    font-size: 13px; font-weight: 600; color: var(--gray-500);
    cursor: pointer; transition: .2s; display: flex; align-items: center; gap: 6px;
    margin-bottom: -1px;
}
.modal-tab:hover { color: var(--prussian); }
.modal-tab.active { color: var(--prussian); border-bottom-color: var(--pacific); }
.modal-close {
    background: none; border: none; color: var(--gray-400);
    font-size: 16px; cursor: pointer; padding: 8px; border-radius: 8px;
    transition: .2s; flex-shrink: 0;
}
.modal-close:hover { background: var(--gray-100); color: var(--prussian); }
.modal-pane { display: none; padding: 24px; }
.modal-pane.active { display: block; }
.modal-field { margin-bottom: 16px; }

/* ── Responsive ── */
@media (max-width: 640px) {
    .stats-row { grid-template-columns: 1fr 1fr; }
    .form-grid  { grid-template-columns: 1fr; }
    .quiz-grid  { grid-template-columns: 1fr; padding: 16px; }
    .wrap { padding: 20px 16px; }
    .modal-backdrop { padding: 56px 8px 0; }
    .modal-box { max-width: 100%; }
}
@media (max-width: 420px) {
    .stats-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="dash-nav">
    <a class="dash-nav-logo" href="../index.php">
        <?php
        $_logoPath = ($tenant['logo_path'] ?? '') ?: ($_coRow['logo_path'] ?? '');
        if ($_logoPath && file_exists(__DIR__.'/../'.$_logoPath)): ?>
        <img src="../<?= htmlspecialchars($_logoPath) ?>" alt="<?= $orgName ?>" height="28" style="max-height:28px;max-width:120px;object-fit:contain"/>
        <?php else: ?>
        <img src="../assets/logo-white.svg" alt="PageQuiz" height="28"/>
        <?php endif; ?>
        <span class="dash-nav-brand"><?= $tenant ? $orgName : 'Page<span>Quiz</span>' ?></span>
    </a>
    <div class="dash-nav-right">
        <a href="../index.php">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            <span>Início</span>
        </a>
        <button class="nav-avatar" id="btn-open-profile" title="Meu Perfil" aria-label="Abrir perfil">
            <?= strtoupper(mb_substr($user['name'], 0, 2)) ?>
        </button>
        <a href="logout.php" class="btn-out">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>Sair</span>
        </a>
    </div>
</nav>

<!-- Modal Perfil -->
<div class="modal-backdrop" id="modal-profile" role="dialog" aria-modal="true" aria-label="Perfil">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-tabs">
                <button class="modal-tab active" data-tab="tab-perfil">
                    <i class="fa-solid fa-user-pen" aria-hidden="true"></i> Meu Perfil
                </button>
                <button class="modal-tab" data-tab="tab-senha">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Alterar Senha
                </button>
            </div>
            <button class="modal-close" id="btn-close-profile" aria-label="Fechar">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Tab: Perfil -->
        <div class="modal-pane active" id="tab-perfil">
            <?php if ($profileMsg): ?>
            <div class="msg-ok">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <?= htmlspecialchars($profileMsg) ?>
            </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="update_profile" value="1"/>
                <div class="modal-field">
                    <label class="form-label">Nome</label>
                    <input class="form-control" type="text" name="name"
                           value="<?= htmlspecialchars($user['name']) ?>" required/>
                </div>
                <div class="modal-field">
                    <label class="form-label">Setor</label>
                    <select class="form-control" name="sector">
                        <option value="">— Selecione —</option>
                        <?php foreach ($sectors as $s): ?>
                        <option value="<?= htmlspecialchars($s['name']) ?>"
                            <?= $user['sector'] === $s['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar
                </button>
            </form>
        </div>

        <!-- Tab: Senha -->
        <div class="modal-pane" id="tab-senha">
            <?php if ($passMsg): ?>
            <?php [$pType, $pText] = explode(':', $passMsg, 2); ?>
            <div class="msg-<?= $pType ?>">
                <i class="fa-solid fa-<?= $pType === 'ok' ? 'circle-check' : 'circle-exclamation' ?>" aria-hidden="true"></i>
                <?= htmlspecialchars($pText) ?>
            </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="change_pass" value="1"/>
                <div class="modal-field">
                    <label class="form-label">Senha atual</label>
                    <input class="form-control" type="password" name="current_pass"
                           placeholder="••••••••" required autocomplete="current-password"/>
                </div>
                <div class="modal-field">
                    <label class="form-label">Nova senha</label>
                    <input class="form-control" type="password" name="new_pass"
                           placeholder="Mín. 6 caracteres" required autocomplete="new-password"/>
                </div>
                <div class="modal-field">
                    <label class="form-label">Confirmar nova senha</label>
                    <input class="form-control" type="password" name="conf_pass"
                           placeholder="Repita a nova senha" required autocomplete="new-password"/>
                </div>
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Alterar senha
                </button>
            </form>
        </div>
    </div>
</div>

<div class="wrap">

    <!-- Header -->
    <div class="dash-header">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:var(--yellow);margin-bottom:6px;display:flex;align-items:center;gap:6px">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            <?= $orgName ?>
        </div>
        <div class="dash-greeting">
            Bem-vindo(a), <span><?= htmlspecialchars($user['name']) ?></span>!
        </div>
        <div class="dash-sub">
            <?php if ($user['sector']): ?>
            <span class="dash-sub-item">
                <i class="fa-solid fa-sitemap" aria-hidden="true"></i>
                <?= htmlspecialchars($user['sector']) ?>
            </span>
            <span style="color:rgba(255,255,255,.2)">·</span>
            <?php endif; ?>
            <span class="dash-sub-item">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <?= htmlspecialchars($user['email']) ?>
            </span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="val"><?= count($available) ?></div>
            <div class="lbl">Quizzes disponíveis</div>
        </div>
        <div class="stat-box s-pass">
            <div class="val"><?= $totalDone ?></div>
            <div class="lbl">Realizados</div>
        </div>
        <div class="stat-box s-avg">
            <div class="val"><?= $avgPct ?>%</div>
            <div class="lbl">Média geral</div>
        </div>
    </div>

    <!-- Quizzes disponíveis -->
    <div class="card">
        <div class="card-hd">
            <h2>
                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                Quizzes disponíveis
            </h2>
            <span style="font-size:12px;color:var(--gray-400);font-weight:600">
                <?= count($available) ?> quiz<?= count($available) !== 1 ? 'zes' : '' ?>
            </span>
        </div>

        <?php if (empty($available)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clipboard-question" aria-hidden="true"></i>
            Nenhum quiz disponível no momento.<br/>
            <span style="font-size:13px">Aguarde novos treinamentos serem publicados.</span>
        </div>
        <?php else: ?>
        <div class="quiz-grid">
            <?php foreach ($available as $q):
                $isDone = in_array($q['id'], $doneIds);
            ?>
            <div class="quiz-card" onclick="window.location='../quiz.php?id=<?= $q['id'] ?>'">
                <?php if ($isDone): ?>
                <span class="quiz-badge-done">
                    <i class="fa-solid fa-check" aria-hidden="true"></i> Feito
                </span>
                <?php endif; ?>

                <div class="quiz-card-sector">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <?= htmlspecialchars($q['sector'] ?: 'Geral') ?>
                </div>
                <div class="quiz-card-title"><?= htmlspecialchars($q['title']) ?></div>
                <?php if ($q['description']): ?>
                <div class="quiz-card-desc"><?= htmlspecialchars($q['description']) ?></div>
                <?php endif; ?>
                <div class="quiz-card-meta">
                    <span><i class="fa-solid fa-circle-question" aria-hidden="true"></i> <?= $q['question_count'] ?> perguntas</span>
                    <span><i class="fa-solid fa-clock" aria-hidden="true"></i> <?= $q['time_per_question'] ?>s/questão</span>
                    <?php if ($q['has_certificate']): ?>
                    <span><i class="fa-solid fa-award" aria-hidden="true"></i> Certificado</span>
                    <?php endif; ?>
                </div>
                <a href="../quiz.php?id=<?= $q['id'] ?>" class="quiz-btn <?= $isDone ? 'done' : '' ?>">
                    <i class="fa-solid fa-<?= $isDone ? 'rotate-right' : 'play' ?>" aria-hidden="true"></i>
                    <?= $isDone ? 'Refazer quiz' : 'Iniciar quiz' ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Histórico -->
    <div class="card">
        <div class="card-hd">
            <h2>
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                Histórico de participações
            </h2>
            <span style="font-size:12px;color:var(--gray-400);font-weight:600">
                <?= $totalDone ?> concluído<?= $totalDone !== 1 ? 's' : '' ?> · <?= $totalPassed ?> aprovação<?= $totalPassed !== 1 ? 'ões' : '' ?>
            </span>
        </div>
        <div class="card-body" style="padding: 0 24px;">
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                Você ainda não concluiu nenhum quiz.<br/>
                <a href="../index.php" style="color:var(--pacific);font-weight:600;text-decoration:none">Ver quizzes disponíveis →</a>
            </div>
            <?php else: ?>
            <?php foreach ($history as $h): ?>
            <a href="result.php?id=<?= (int)$h['id'] ?>" class="history-row" style="text-decoration:none;color:inherit;display:flex">
                <div style="flex:1;min-width:0">
                    <div class="history-title"><?= htmlspecialchars($h['quiz_title']) ?></div>
                    <div class="history-meta">
                        <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                        <?= date('d/m/Y H:i', strtotime($h['completed_at'])) ?>
                        &nbsp;·&nbsp;
                        <?= $h['score'] ?>/<?= $h['total_questions'] ?> acertos
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                    <span class="badge-pct"><?= $h['percentage'] ?>%</span>
                    <?php if ($h['passed']): ?>
                        <span class="badge-pass">
                            <i class="fa-solid fa-check" aria-hidden="true"></i> Aprovado
                        </span>
                        <?php if ($h['verify_code'] && $h['has_certificate']): ?>
                        <span class="cert-link" title="Ver certificado">
                            <i class="fa-solid fa-award" aria-hidden="true"></i> Certificado
                        </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge-fail">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Reprovado
                        </span>
                    <?php endif; ?>
                    <span style="color:var(--pacific);font-size:13px" title="Ver detalhes">
                        <i class="fa-solid fa-chevron-right"></i>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>

            <?php if ($histPages > 1): ?>
            <div style="padding:16px 0;display:flex;align-items:center;justify-content:center;gap:6px">
                <?php if ($histPage > 1): ?>
                <a href="?hp=<?= $histPage - 1 ?>"
                   style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:var(--gray-100);color:var(--gray-600)">
                    ← Anterior
                </a>
                <?php endif; ?>
                <span style="font-size:13px;color:var(--gray-500)">
                    Página <?= $histPage ?> de <?= $histPages ?>
                </span>
                <?php if ($histPage < $histPages): ?>
                <a href="?hp=<?= $histPage + 1 ?>"
                   style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:var(--pacific);color:#fff">
                    Próxima →
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

</div><!-- .wrap -->

<script>
const modal    = document.getElementById('modal-profile');
const btnOpen  = document.getElementById('btn-open-profile');
const btnClose = document.getElementById('btn-close-profile');
const tabs     = document.querySelectorAll('.modal-tab');
const panes    = document.querySelectorAll('.modal-pane');

function openModal(tabId) {
    modal.classList.add('open');
    if (tabId) switchTab(tabId);
    document.addEventListener('keydown', onEsc);
}

function closeModal() {
    modal.classList.remove('open');
    document.removeEventListener('keydown', onEsc);
}

function switchTab(tabId) {
    tabs.forEach(t  => t.classList.toggle('active', t.dataset.tab === tabId));
    panes.forEach(p => p.classList.toggle('active', p.id === tabId));
}

function onEsc(e) { if (e.key === 'Escape') closeModal(); }

btnOpen.addEventListener('click', () => openModal());
btnClose.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
tabs.forEach(t => t.addEventListener('click', () => switchTab(t.dataset.tab)));

<?php if ($profileMsg): ?>
window.addEventListener('DOMContentLoaded', () => openModal('tab-perfil'));
<?php elseif ($passMsg): ?>
window.addEventListener('DOMContentLoaded', () => openModal('tab-senha'));
<?php endif; ?>
</script>
</body>
</html>
