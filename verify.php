<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/seo.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$tenant = resolveTenant();

$code = isset($_GET['code']) ? strtoupper(trim($_GET['code'])) : '';
$participant = null;
$quiz = null;

if ($code) {
    if ($tenant) {
        $participant = dbRow(
            "SELECT p.* FROM participants p
             JOIN quizzes q ON q.id = p.quiz_id
             WHERE p.verify_code = ? AND q.company_id = ?",
            [$code, (int)$tenant['id']]
        );
    } else {
        $participant = dbRow("SELECT * FROM participants WHERE verify_code = ?", [$code]);
    }
    if ($participant) {
        $quiz = dbRow("SELECT * FROM quizzes WHERE id = ?", [$participant['quiz_id']]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<?php
$_seoBase    = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'quiz.pageup.net.br');
$_seoOrgName = $tenant ? htmlspecialchars($tenant['name']) : 'PageQuiz';
if ($participant && $quiz) {
    $_seoTitle = 'Certificado de '.htmlspecialchars($participant['name']).' — '.htmlspecialchars($quiz['title']);
    $_seoDesc  = 'Verifique a autenticidade do certificado emitido para '.htmlspecialchars($participant['name']).' no quiz '.htmlspecialchars($quiz['title']).'. Emitido por '.$_seoOrgName.'.';
    $_seoJsonLd = seoJsonLdCertificate($participant, $quiz, $_seoBase.'/verify.php?code='.urlencode($code));
} else {
    $_seoTitle  = 'Verificar Certificado · '.$_seoOrgName;
    $_seoDesc   = 'Verifique a autenticidade de um certificado emitido pela plataforma '.$_seoOrgName.'. Informe o código do certificado para confirmar.';
    $_seoJsonLd = null;
}
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#023047">
    <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($_seoDesc),0,160)) ?>"/>
    <title><?= htmlspecialchars($_seoTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg"/>
    <link rel="apple-touch-icon" href="assets/logo-icon.svg"/>
    <link rel="manifest" href="/manifest.json"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?= seoHead([
        'title'      => $_seoTitle,
        'description'=> $_seoDesc,
        'canonical'  => $_seoBase.'/verify.php'.($code ? '?code='.urlencode($code) : ''),
        'image'      => $_seoBase.'/assets/og-image.jpg',
        'site_name'  => $_seoOrgName,
        'robots'     => $participant ? 'noindex,follow' : 'index,follow',
        'jsonld'     => $_seoJsonLd,
    ]) ?>
    <style>
        body {
            background: var(--gray-50);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .verify-header {
            background: var(--prussian);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 16px rgba(2,48,71,.3);
        }
        .verify-header img { height: 40px; mix-blend-mode: screen; background: transparent; }
        .verify-header-text { color: #fff; }
        .verify-header-text strong { font-family: var(--font-heading); font-size: 16px; font-weight: 700; display: block; }
        .verify-header-text span  { font-size: var(--text-xs); color: rgba(142,202,230,.7); letter-spacing: .5px; }

        .verify-main {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 48px 20px;
        }

        .verify-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 36px;
            width: 100%;
            max-width: 480px;
            border: 1px solid var(--gray-200);
        }

        .verify-card h1 {
            font-family: var(--font-heading);
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--prussian);
            margin-bottom: 6px;
            text-wrap: balance;
        }
        .verify-card .subtitle {
            font-size: var(--text-sm);
            color: var(--gray-500);
            margin-bottom: 28px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
        }
        .search-input {
            flex-grow: 1;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 16px;
            text-transform: uppercase;
            text-align: center;
            font-weight: 700;
            letter-spacing: 2px;
            outline: none;
            color: var(--prussian);
            transition: var(--transition);
        }
        .search-input:focus { border-color: var(--pacific); box-shadow: 0 0 0 3px rgba(33,158,188,.12); }
        .search-input::placeholder { text-transform: none; font-weight: 400; letter-spacing: 0; color: var(--gray-400); }

        .btn-verify {
            background: var(--pacific);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 14px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .btn-verify:hover { background: var(--blue-dark); transform: translateY(-1px); }

        .result-box {
            margin-top: 28px;
            padding-top: 28px;
            border-top: 1px solid var(--gray-100);
            animation: verifyFadeIn .35s ease;
        }
        @keyframes verifyFadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 800;
            font-size: var(--text-xs);
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-badge.ok  { background: #e6fffa; color: #38a169; border: 1px solid #b2f5ea; }
        .status-badge.err { background: #fff5f5; color: var(--red); border: 1px solid #fed7d7; }

        .info-row { margin-bottom: 18px; }
        .info-lbl { font-size: var(--text-xs); text-transform: uppercase; color: var(--gray-500); font-weight: 700; letter-spacing: .5px; margin-bottom: 3px; }
        .info-val { font-size: var(--text-lg); font-weight: 700; color: var(--prussian); }
        .info-val.highlight { color: var(--pacific); }

        .info-row-split { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 18px; }
        .info-row-split .info-row { margin-bottom: 0; flex: 1; }
        .info-row-split .info-row:last-child { text-align: right; }

        .verify-footer {
            background: var(--prussian);
            padding: 20px 32px;
            text-align: center;
        }
        .verify-footer p { font-size: var(--text-xs); color: rgba(142,202,230,.5); margin: 0; }
    </style>
</head>
<body>

<header class="verify-header">
    <img src="assets/logo-white.svg" alt="PageUp"/>
    <div class="verify-header-text">
        <strong>PageQuiz</strong>
        <span>Verificação de Certificado</span>
    </div>
</header>

<main class="verify-main">
    <div class="verify-card">
        <h1>Verificar Certificado</h1>
        <p class="subtitle">Valide a autenticidade de um certificado emitido pela plataforma.</p>

        <form action="verify.php" method="get" class="search-form">
            <input
                type="text"
                name="code"
                class="search-input"
                placeholder="Código de verificação"
                value="<?= htmlspecialchars($code) ?>"
                maxlength="8"
                autocomplete="off"
                aria-label="Código de verificação do certificado"
                required>
            <button type="submit" class="btn-verify">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Validar
            </button>
        </form>

        <?php if ($code && $participant): ?>
            <div class="result-box">
                <div class="status-badge ok">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Certificado Autêntico
                </div>

                <div class="info-row">
                    <div class="info-lbl">Colaborador</div>
                    <div class="info-val"><?= htmlspecialchars($participant['name']) ?></div>
                </div>

                <div class="info-row">
                    <div class="info-lbl">Setor</div>
                    <div class="info-val"><?= htmlspecialchars($participant['sector'] ?: '—') ?></div>
                </div>

                <div class="info-row">
                    <div class="info-lbl">Treinamento</div>
                    <div class="info-val"><?= htmlspecialchars($quiz['title']) ?></div>
                </div>

                <div class="info-row-split">
                    <div class="info-row">
                        <div class="info-lbl">Aproveitamento</div>
                        <div class="info-val highlight"><?= $participant['percentage'] ?>%</div>
                    </div>
                    <div class="info-row">
                        <div class="info-lbl">Data de Conclusão</div>
                        <div class="info-val"><?= date('d/m/Y', strtotime($participant['completed_at'])) ?></div>
                    </div>
                </div>
            </div>
        <?php elseif ($code): ?>
            <div class="result-box">
                <div class="status-badge err">
                    <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                    Não Encontrado
                </div>
                <p style="font-size:var(--text-sm);color:var(--gray-500)">
                    O código <strong><?= htmlspecialchars($code) ?></strong> não corresponde a nenhum certificado válido em nossa base de dados.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer class="verify-footer">
    <p>PageUp Sistemas &nbsp;·&nbsp; <?= date('Y') ?></p>
</footer>

</body>
</html>
