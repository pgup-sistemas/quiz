<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/../includes/tenant.php';

userSessionStart();
if (!isUserLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('certificate.php?id=' . ($_GET['id'] ?? '')));
    exit;
}

$user   = currentUser();
$cid    = _userCompanyId();
$pid    = (int)($_GET['id'] ?? 0);

if (!$pid) { header('Location: dashboard.php'); exit; }

// Load participant record with tenant guard
$p = dbRow("
    SELECT p.*, q.title AS quiz_title, q.has_certificate, q.company_id
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE p.id = ? AND p.completed_at IS NOT NULL AND p.passed = 1 AND q.has_certificate = 1
", [$pid]);

if (!$p) { header('Location: dashboard.php'); exit; }

// Tenant isolation: user's company must match quiz's company
if ($cid && (int)$p['company_id'] !== $cid) { header('Location: dashboard.php'); exit; }

// Owner check: priority is user_id, fallback to email (name match removed — too easy to bypass)
$ownerOk = ((int)($p['user_id'] ?? 0) && (int)$p['user_id'] === (int)$user['id'])
         || ($user['email'] && $p['email'] && $p['email'] === $user['email']);
if (!$ownerOk) { header('Location: dashboard.php'); exit; }

$base   = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'quiz.pageup.net.br');
$verUrl = $base.'/verify.php?code='.urlencode($p['verify_code'] ?? '');
$date   = $p['completed_at'] ? date('d/m/Y', strtotime($p['completed_at'])) : date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Certificado · <?= htmlspecialchars($p['quiz_title']) ?> · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body { background: #0b1e35; min-height: 100vh; }

.cert-page-nav {
    background: #05111f;
    border-bottom: 2px solid var(--yellow);
    padding: 14px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.cert-page-nav a {
    color: rgba(255,255,255,.7);
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color .2s;
}
.cert-page-nav a:hover { color: var(--yellow); }
.cert-page-nav .spacer { flex: 1; }

.cert-outer {
    max-width: 860px;
    margin: 32px auto;
    padding: 0 16px 40px;
}

.cert-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.btn-print {
    padding: 12px 24px;
    background: var(--prussian);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: background .2s;
}
.btn-print:hover { background: #012235; }

.btn-share {
    padding: 12px 24px;
    background: #25D366;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: background .2s;
}
.btn-share:hover { background: #1da750; }

.btn-back {
    padding: 12px 24px;
    background: var(--gray-200);
    color: var(--gray-700);
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: background .2s;
}
.btn-back:hover { background: var(--gray-300); }

@media print {
    @page { size: A4 landscape; margin: 12mm 2mm 12mm 22mm; }
    html, body { width: auto; height: auto; margin: 0; padding: 0; background: #fff; }
    .cert-page-nav, .cert-actions, .cert-outer > *:not(.cert-wrap) { display: none !important; }
    .cert-outer { margin: 0; padding: 0; max-width: none; width: auto; height: auto; }
    .cert-wrap { max-width: none; width: 100%; margin: 0; }
    .cert { width: 100%; height: 186mm; min-height: unset; padding: 14mm 20mm; box-shadow: none; border: none; border-radius: 0; margin: 0; box-sizing: border-box; }
}
</style>
</head>
<body>

<nav class="cert-page-nav">
    <a href="dashboard.php">
        <i class="fa-solid fa-arrow-left"></i> Meu Painel
    </a>
    <div class="spacer"></div>
    <span style="color:rgba(255,255,255,.5);font-size:13px">
        <i class="fa-solid fa-certificate" style="color:var(--yellow)"></i>
        Certificado de Conclusão
    </span>
</nav>

<div class="cert-outer">

    <div class="cert-actions">
        <button class="btn-print" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Imprimir / Salvar PDF
        </button>
        <a id="btn-wa" class="btn-share" href="#" target="_blank">
            <i class="fa-brands fa-whatsapp"></i> Compartilhar
        </a>
        <a href="dashboard.php" class="btn-back">
            <i class="fa-solid fa-gauge"></i> Voltar ao Painel
        </a>
    </div>

    <div class="cert-wrap">
        <div class="cert" id="cert-print">
            <div class="cert-topbar"></div>
            <div style="display:flex;flex-direction:column;align-items:center;width:100%">
                <img src="../assets/logo.svg" class="cert-logo" alt="PageUp Sistemas"/>
                <h1>Certificado de Conclusão</h1>
                <h2><?= htmlspecialchars($p['quiz_title']) ?></h2>
            </div>

            <div style="flex-grow:1;width:100%;display:flex;flex-direction:column;justify-content:center">
                <div class="cert-label">Certificamos que</div>
                <div class="cert-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="cert-sector"><?= htmlspecialchars($p['sector']) ?></div>
                <div class="cert-divider"></div>
                <div class="cert-label">concluiu com</div>
                <div class="cert-score"><?= number_format($p['percentage'], 0) ?>%</div>
                <div class="cert-score-lbl">de aproveitamento</div>
                <div style="margin-top:8px">
                    <span style="font-size:9px;color:var(--gray-400);text-transform:uppercase;letter-spacing:1px">ID Verificação:</span>
                    <strong style="font-size:10px;color:var(--prussian)"><?= htmlspecialchars($p['verify_code'] ?? 'N/A') ?></strong>
                </div>
                <div>
                    <div class="cert-badge">APROVADO <i class="fa-solid fa-check"></i></div>
                </div>
            </div>

            <div style="width:100%">
                <div class="cert-details">
                    <div>Data de conclusão: <strong><?= $date ?></strong></div>
                    <div>E-mail: <strong><?= htmlspecialchars($p['email'] ?: 'Não informado') ?></strong></div>
                    <div>Acertos: <strong><?= (int)$p['score'] ?> de <?= (int)$p['total_questions'] ?> questões</strong></div>
                    <div>Tempo médio: <strong><?= number_format($p['avg_time'], 0) ?>s por questão</strong></div>
                </div>
                <div style="margin-top:15px;display:flex;align-items:flex-end;justify-content:space-between">
                    <div style="text-align:left">
                        <div class="cert-footer">
                            PageUp Sistemas · Plataforma de Treinamento<br/>
                            Válido como evidência de treinamento e capacitação profissional.
                        </div>
                    </div>
                    <?php if ($p['verify_code']): ?>
                    <div style="padding:4px;background:#fff;border:1px solid var(--gray-200);border-radius:6px">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&color=023047&data=<?= urlencode($verUrl) ?>"
                             width="70" height="70" style="display:block" alt="QR Code de verificação"/>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
const verUrl  = <?= json_encode($verUrl) ?>;
const quizTitle = <?= json_encode($p['quiz_title']) ?>;
const pct     = <?= (int)$p['percentage'] ?>;
const verCode = <?= json_encode($p['verify_code'] ?? '') ?>;

const msg = `*Passei no Treinamento!*\n\n✅ Quiz: ${quizTitle}\nAproveitamento: *${pct}%*\nVerificação: ${verCode}\n\nValide meu certificado: ${verUrl}`;
document.getElementById('btn-wa').href = `https://wa.me/?text=${encodeURIComponent(msg)}`;
</script>
</body>
</html>
