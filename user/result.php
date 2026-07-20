<?php
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';

userSessionStart();
if (!isUserLoggedIn()) {
    header('Location: login.php?redirect=dashboard.php');
    exit;
}

$user   = currentUser();
$tenant = resolveTenant();
$cid    = $tenant ? (int)$tenant['id'] : 0;
$uid    = (int)$user['id'];
$pid    = (int)($_GET['id'] ?? 0);

if (!$pid) { header('Location: dashboard.php'); exit; }

// Carrega participação — valida que pertence ao usuário logado (user_id OU email)
$cWhere = $cid ? 'AND q.company_id = ?' : '';
$cParam = $cid ? [$pid, $uid, $user['email'], $cid] : [$pid, $uid, $user['email']];

$p = dbRow("
    SELECT p.*, q.title AS quiz_title, q.pass_percentage AS q_pass_pct,
           q.id AS quiz_id, q.has_certificate
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE p.id = ?
      AND (p.user_id = ? OR (p.user_id IS NULL AND p.email != '' AND p.email = ?))
      $cWhere
      AND p.completed_at IS NOT NULL
", $cParam);

if (!$p) { header('Location: dashboard.php'); exit; }

// Respostas detalhadas
$answers = dbRows("
    SELECT a.*, q.question_text, q.category,
           q.option_a, q.option_b, q.option_c, q.option_d,
           q.correct_answer, q.explanation
    FROM answers a
    JOIN questions q ON q.id = a.question_id
    WHERE a.participant_id = ?
    ORDER BY a.id ASC
", [$pid]);

$orgName = $tenant ? htmlspecialchars($tenant['name']) : 'PageQuiz';
$ltrs    = ['A','B','C','D'];
$scoreColor = $p['passed'] ? '#00875a' : '#c53030';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Meu Resultado · <?= $orgName ?></title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body { background: #eef4f7; font-family: 'DM Sans', sans-serif; margin: 0; }

.dash-nav {
    background: #05111f;
    border-bottom: 2px solid var(--yellow);
    padding: 0 24px;
    display: flex; align-items: center; justify-content: space-between;
    height: 56px; position: sticky; top: 0; z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
}
.dash-nav-logo { display:flex;align-items:center;gap:10px;text-decoration:none; }
.dash-nav-logo img { height: 28px; }
.dash-nav-brand { color:#fff;font-size:16px;font-weight:700; }
.dash-nav-right { display:flex;align-items:center;gap:6px; }
.dash-nav-right a {
    color:rgba(255,255,255,.75); font-size:13px; text-decoration:none;
    padding:7px 12px; border-radius:8px; transition:.2s;
    display:flex; align-items:center; gap:6px;
}
.dash-nav-right a:hover { color:var(--yellow); background:rgba(255,183,3,.15); }

.wrap { max-width: 860px; margin: 0 auto; padding: 32px 20px; }

/* Breadcrumb */
.breadcrumb { display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:24px; flex-wrap:wrap; }
.breadcrumb a { color:var(--pacific); text-decoration:none; font-weight:600; }
.breadcrumb a:hover { text-decoration:underline; }
.breadcrumb span { color:var(--gray-300); }

/* Score hero */
.score-hero {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2edf2;
    padding: 28px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(2,48,71,.04);
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 28px;
    align-items: center;
}
.score-circle {
    width: 100px; height: 100px;
    border-radius: 50%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif;
    font-size: 28px; font-weight: 800; color: #fff;
    flex-shrink: 0;
}
.score-circle.pass { background: linear-gradient(135deg, var(--pacific), #0A7B9A); }
.score-circle.fail { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.score-circle small { font-size: 11px; font-weight: 600; opacity: .8; margin-top: 2px; }

.score-meta-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 14px; margin-top: 16px;
}
.score-meta-item { text-align: center; }
.score-meta-val { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; color: var(--prussian); }
.score-meta-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--gray-400); margin-top: 2px; }

/* Info card */
.info-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2edf2;
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(2,48,71,.04);
    display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
}
.info-card-item { font-size: 13px; color: var(--gray-600); display:flex; align-items:center; gap:6px; }
.info-card-item i { color: var(--pacific); }
.info-card-item strong { color: var(--prussian); }

/* Badge inline */
.badge-pass { background:#e6fffa;color:#00875a;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.4px; }
.badge-fail { background:#fff5f5;color:#c53030;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.4px; }

/* Respostas */
.answers-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2edf2;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(2,48,71,.04);
    margin-bottom: 20px;
}
.answers-hd {
    padding: 16px 24px;
    border-bottom: 1px solid #eef2f5;
    display: flex; align-items: center; gap: 8px;
    font-size: 15px; font-weight: 700; color: var(--prussian);
}
.answers-hd i { color: var(--pacific); }

.q-item {
    padding: 18px 24px;
    border-bottom: 1px solid #f0f4f7;
}
.q-item:last-child { border-bottom: none; }
.q-num-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
}
.q-num { font-size: 11px; font-weight: 700; color: var(--gray-400); text-transform: uppercase; letter-spacing: .6px; }
.q-cat { font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 20px; }
.q-status-ok   { font-size: 11px; font-weight: 700; color: #00875a; background: #e6fffa; padding: 2px 8px; border-radius: 20px; display:inline-flex;align-items:center;gap:4px; }
.q-status-err  { font-size: 11px; font-weight: 700; color: #c53030; background: #fff5f5; padding: 2px 8px; border-radius: 20px; display:inline-flex;align-items:center;gap:4px; }
.q-status-time { font-size: 11px; font-weight: 700; color: #7a5800; background: #fef3c7; padding: 2px 8px; border-radius: 20px; display:inline-flex;align-items:center;gap:4px; }
.q-time { margin-left:auto; font-size:11px; color:var(--gray-400); }
.q-text { font-size: 14px; font-weight: 600; color: var(--prussian); line-height: 1.5; margin-bottom: 12px; }
.q-opts { display: flex; flex-direction: column; gap: 6px; }
.q-opt {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 9px 12px; border-radius: 9px; font-size: 13px;
    border: 1.5px solid #e2edf2; background: var(--gray-50);
}
.q-opt.correct { background: #e6fffa; border-color: #00875a; }
.q-opt.wrong   { background: #fff5f5; border-color: #c53030; }
.q-opt-letter {
    font-weight: 700; min-width: 22px; font-size: 12px;
    color: var(--gray-400);
}
.q-opt.correct .q-opt-letter { color: #00875a; }
.q-opt.wrong   .q-opt-letter { color: #c53030; }
.q-opt-tag { margin-left: auto; font-size: 11px; font-weight: 700; white-space: nowrap; }
.q-opt.correct .q-opt-tag { color: #00875a; }
.q-opt.wrong   .q-opt-tag { color: #c53030; }
.q-explanation {
    background: #f0f9ff; border-left: 3px solid var(--pacific);
    border-radius: 6px; padding: 10px 12px; margin-top: 10px;
    font-size: 12.5px; color: #0369a1; line-height: 1.5;
}

/* Actions bar */
.actions-bar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom: 24px; }

@media (max-width: 600px) {
    .score-hero { grid-template-columns: 1fr; text-align: center; }
    .score-circle { margin: 0 auto; }
    .wrap { padding: 20px 16px; }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="dash-nav">
    <a class="dash-nav-logo" href="../index.php">
        <?php if ($tenant && !empty($tenant['logo_path']) && file_exists(__DIR__.'/../'.$tenant['logo_path'])): ?>
        <img src="../<?= htmlspecialchars($tenant['logo_path']) ?>" alt="<?= $orgName ?>"/>
        <?php else: ?>
        <img src="../assets/logo-white.svg" alt="PageQuiz"/>
        <?php endif; ?>
        <span class="dash-nav-brand"><?= $orgName ?></span>
    </a>
    <div class="dash-nav-right">
        <a href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Meu Painel</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</nav>

<div class="wrap">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="dashboard.php"><i class="fa-solid fa-house"></i> Meu Painel</a>
        <span>/</span>
        <span style="color:var(--gray-600)"><?= htmlspecialchars(mb_strimwidth($p['quiz_title'], 0, 50, '…')) ?></span>
    </div>

    <!-- Score hero -->
    <div class="score-hero">
        <div class="score-circle <?= $p['passed'] ? 'pass' : 'fail' ?>">
            <?= number_format($p['percentage'], 0) ?>%
            <small><?= $p['passed'] ? 'Aprovado' : 'Reprovado' ?></small>
        </div>
        <div>
            <h1 style="font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--prussian);margin:0 0 6px">
                <?= htmlspecialchars($p['quiz_title']) ?>
            </h1>
            <?php if ($p['passed']): ?>
            <span class="badge-pass"><i class="fa-solid fa-circle-check"></i> Aprovado</span>
            <?php else: ?>
            <span class="badge-fail"><i class="fa-solid fa-circle-xmark"></i> Reprovado — mínimo <?= $p['q_pass_pct'] ?>%</span>
            <?php endif; ?>
            <div class="score-meta-grid">
                <div class="score-meta-item">
                    <div class="score-meta-val" style="color:#00875a"><?= $p['score'] ?></div>
                    <div class="score-meta-lbl">Acertos</div>
                </div>
                <div class="score-meta-item">
                    <div class="score-meta-val" style="color:#c53030"><?= $p['total_questions'] - $p['score'] ?></div>
                    <div class="score-meta-lbl">Erros</div>
                </div>
                <div class="score-meta-item">
                    <div class="score-meta-val"><?= $p['total_questions'] ?></div>
                    <div class="score-meta-lbl">Questões</div>
                </div>
                <div class="score-meta-item">
                    <div class="score-meta-val"><?= number_format($p['avg_time'], 1) ?>s</div>
                    <div class="score-meta-lbl">Tempo médio</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="info-card">
        <div class="info-card-item">
            <i class="fa-solid fa-calendar-day"></i>
            <strong><?= date('d/m/Y H:i', strtotime($p['completed_at'])) ?></strong>
        </div>
        <?php if ($p['sector']): ?>
        <div class="info-card-item">
            <i class="fa-solid fa-sitemap"></i>
            <strong><?= htmlspecialchars($p['sector']) ?></strong>
        </div>
        <?php endif; ?>
        <div class="info-card-item">
            <i class="fa-solid fa-bullseye"></i>
            Nota mínima: <strong><?= $p['q_pass_pct'] ?>%</strong>
        </div>
    </div>

    <!-- Ações -->
    <div class="actions-bar">
        <?php if ($p['passed'] && $p['verify_code'] && $p['has_certificate']): ?>
        <a href="certificate.php?id=<?= $pid ?>" class="btn btn-primary">
            <i class="fa-solid fa-award"></i> Ver Certificado
        </a>
        <?php endif; ?>
        <a href="../quiz.php?id=<?= $p['quiz_id'] ?>" class="btn btn-outline">
            <i class="fa-solid fa-rotate-right"></i> Refazer Quiz
        </a>
        <a href="dashboard.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Voltar ao Painel
        </a>
    </div>

    <!-- Respostas detalhadas -->
    <?php if (!empty($answers)): ?>
    <div class="answers-card">
        <div class="answers-hd">
            <i class="fa-solid fa-list-check"></i>
            Respostas detalhadas
            <span style="margin-left:auto;font-size:12px;font-weight:600;color:var(--gray-400)"><?= count($answers) ?> questões</span>
        </div>
        <?php foreach ($answers as $i => $a): ?>
        <div class="q-item">
            <div class="q-num-row">
                <span class="q-num">Q<?= $i + 1 ?></span>
                <?php if ($a['category']): ?>
                <span class="q-cat"><?= htmlspecialchars($a['category']) ?></span>
                <?php endif; ?>
                <?php if ($a['selected_answer'] == -1): ?>
                    <span class="q-status-time"><i class="fa-solid fa-clock"></i> Tempo esgotado</span>
                <?php elseif ($a['is_correct']): ?>
                    <span class="q-status-ok"><i class="fa-solid fa-check"></i> Correto</span>
                <?php else: ?>
                    <span class="q-status-err"><i class="fa-solid fa-xmark"></i> Incorreto</span>
                <?php endif; ?>
                <span class="q-time"><i class="fa-regular fa-clock"></i> <?= $a['time_taken'] ?>s</span>
            </div>
            <div class="q-text"><?= htmlspecialchars($a['question_text']) ?></div>
            <div class="q-opts">
                <?php foreach ($ltrs as $oi => $ltr):
                    $opt = $a['option_'.(strtolower($ltr))] ?? '';
                    if (!trim($opt)) continue;
                    $isCorrect  = $oi == (int)$a['correct_answer'];
                    $isSelected = $oi == (int)$a['selected_answer'];
                    $cls = $isCorrect ? 'correct' : ($isSelected && !$isCorrect ? 'wrong' : '');
                ?>
                <div class="q-opt <?= $cls ?>">
                    <span class="q-opt-letter"><?= $ltr ?>)</span>
                    <span><?= htmlspecialchars($opt) ?></span>
                    <?php if ($isCorrect && $isSelected): ?>
                        <span class="q-opt-tag">✓ Sua resposta</span>
                    <?php elseif ($isCorrect): ?>
                        <span class="q-opt-tag">✓ Correta</span>
                    <?php elseif ($isSelected): ?>
                        <span class="q-opt-tag">← Sua resposta</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($a['explanation']): ?>
            <div class="q-explanation">
                <i class="fa-solid fa-circle-info"></i>
                <?= htmlspecialchars($a['explanation']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:14px">
        <i class="fa-solid fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4"></i>
        Detalhes das respostas não disponíveis para esta participação.
    </div>
    <?php endif; ?>

</div>
</body>
</html>
