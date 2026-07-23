<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/user-auth.php';
require_once __DIR__ . '/includes/seo.php';

userSessionStart();
$tenant = resolveTenant();
$currentUser = currentUser();

if ($tenant) {
    $cid      = (int)$tenant['id'];
    $perPage  = 6;
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $perPage;

    $totalQuizzes = (int)(dbRow("
        SELECT COUNT(*) AS c FROM quizzes
        WHERE active = 1 AND company_id = ?
          AND (expires_at  IS NULL OR expires_at  >= NOW())
          AND (visible_from IS NULL OR visible_from <= NOW())
    ", [$cid])['c'] ?? 0);

    $totalPages = max(1, (int)ceil($totalQuizzes / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $quizzes = dbRows("
        SELECT q.*, COUNT(DISTINCT p.id) AS participant_count, COUNT(DISTINCT qs.id) AS question_count
        FROM quizzes q
        LEFT JOIN participants p  ON p.quiz_id = q.id
        LEFT JOIN questions   qs  ON qs.quiz_id = q.id
        WHERE q.active = 1
          AND q.company_id = ?
          AND (q.expires_at   IS NULL OR q.expires_at   >= NOW())
          AND (q.visible_from IS NULL OR q.visible_from <= NOW())
        GROUP BY q.id
        ORDER BY q.created_at DESC
        LIMIT ? OFFSET ?
    ", [$cid, $perPage, $offset]);

    $stats = [
        'quizzes'  => dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE active=1 AND company_id=?", [$cid])['c'],
        'done'     => dbRow("SELECT COUNT(*) AS c FROM participants WHERE completed_at IS NOT NULL AND company_id=?", [$cid])['c'],
        'sectors'  => dbRow("SELECT COUNT(*) AS c FROM sectors WHERE company_id=?", [$cid])['c'],
        'pass_rate'=> dbRow("SELECT ROUND(AVG(passed)*100) AS r FROM participants WHERE completed_at IS NOT NULL AND company_id=?", [$cid])['r'] ?? 0,
    ];

    $companyName   = htmlspecialchars($tenant['name']);
    $primaryColor  = preg_match('/^#[0-9a-fA-F]{6}$/', $tenant['primary_color'] ?? '')
                        ? $tenant['primary_color'] : '#219EBC';
    $hasCustomBrand = planLimits($tenant['plan'])['custom_brand'];
    $logoPath = ($hasCustomBrand && !empty($tenant['logo_path']) && file_exists(__DIR__ . '/' . $tenant['logo_path']))
                    ? $tenant['logo_path'] : null;
} else {
    $quizzes    = [];
    $totalPages = 1;
    $page       = 1;

    $stats = [
        'quizzes'  => dbRow("SELECT COUNT(*) AS c FROM quizzes q JOIN companies c ON c.id=q.company_id AND c.status='active' WHERE q.active=1")['c'],
        'done'     => dbRow("SELECT COUNT(*) AS c FROM participants p JOIN companies c ON c.id=p.company_id AND c.status='active' WHERE p.completed_at IS NOT NULL")['c'],
        'sectors'  => dbRow("SELECT COUNT(*) AS c FROM sectors s JOIN companies c ON c.id=s.company_id AND c.status='active'")['c'],
        'pass_rate'=> dbRow("SELECT ROUND(AVG(p.passed)*100) AS r FROM participants p JOIN companies c ON c.id=p.company_id AND c.status='active' WHERE p.completed_at IS NOT NULL")['r'] ?? 0,
    ];

    $companyName   = 'PageQuiz';
    $primaryColor  = '#219EBC';
    $hasCustomBrand = false;
    $logoPath = null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<?php
$_seoBase  = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'quiz.pageup.net.br');
$_seoTitle = $tenant ? htmlspecialchars($tenant['name']).' · Treinamento e Avaliação' : 'PageQuiz · Plataforma de Treinamento e Avaliação';
$_seoDesc  = $tenant
    ? htmlspecialchars($tenant['name']).' — Realize treinamentos online, avalie sua equipe e emita certificados verificáveis. '.(int)$stats['quizzes'].' quiz(es) disponíveis.'
    : 'PageQuiz — Plataforma profissional de treinamento corporativo via quizzes. Avalie, capacite e certifique sua equipe com certificados verificáveis.';
$_seoJsonLd = seoJsonLdOrganization(
    $tenant ? htmlspecialchars($tenant['name']) : 'PageQuiz',
    $_seoBase . '/',
    $_seoBase . '/assets/logo-icon.svg',
    $_seoDesc
);
?>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="<?= htmlspecialchars($primaryColor) ?>"/>
<meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($_seoDesc),0,160)) ?>"/>
<meta name="keywords" content="quiz, treinamento corporativo, avaliação, certificado, e-learning, capacitação<?= $tenant ? ', '.htmlspecialchars($tenant['name']) : ', PageQuiz, PageUp Sistemas' ?>"/>
<title><?= htmlspecialchars($_seoTitle) ?></title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg"/>
<link rel="icon" type="image/x-icon" href="/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png"/>
<link rel="manifest" href="/manifest.json"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<?= seoHead([
    'title'      => $_seoTitle,
    'description'=> $_seoDesc,
    'canonical'  => $_seoBase . '/',
    'image'      => $_seoBase . '/assets/og-image.jpg',
    'site_name'  => $tenant ? htmlspecialchars($tenant['name']) : 'PageQuiz',
    'jsonld'     => $_seoJsonLd,
]) ?>
<?php if ($tenant && $primaryColor !== '#219EBC'): ?>
<style>:root{--pacific:<?= htmlspecialchars($primaryColor) ?>;}</style>
<?php endif; ?>
<style>
/* ── Reset & Base ── */
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;color:#1e293b;background:#fff;overflow-x:hidden}

/* ── Navbar ── */
.lp-nav{position:sticky;top:0;z-index:200;background:#fff;border-bottom:1px solid #e2ecf1;height:64px;display:flex;align-items:center;padding:0 32px;gap:24px;transition:box-shadow .3s}
.lp-nav.scrolled{box-shadow:0 2px 20px rgba(2,48,71,.08)}
.lp-nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0}
.lp-nav-logo img{height:36px}
.lp-nav-links{display:flex;align-items:center;gap:4px;margin-left:8px}
.lp-nav-links a{padding:8px 14px;border-radius:8px;font-size:14px;font-weight:500;color:#475569;text-decoration:none;transition:.15s}
.lp-nav-links a:hover{background:#f1f7fa;color:var(--prussian)}
.lp-nav-spacer{flex:1}
.lp-nav-auth{display:flex;align-items:center;gap:8px}
.btn-ghost{padding:9px 18px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;background:transparent;border:1.5px solid #dce8ef;color:#334155;cursor:pointer;text-decoration:none;transition:.15s;display:inline-flex;align-items:center;gap:7px}
.btn-ghost:hover{border-color:var(--pacific);color:var(--pacific)}
.btn-cta{padding:9px 20px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;background:var(--pacific);color:#fff;cursor:pointer;text-decoration:none;border:none;transition:.15s;display:inline-flex;align-items:center;gap:7px}
.btn-cta:hover{background:var(--prussian)}
.user-chip{display:flex;align-items:center;gap:8px;padding:6px 14px 6px 6px;border-radius:30px;background:#f0f7fa;border:1px solid #dce8ef;text-decoration:none;color:var(--prussian);font-size:13px;font-weight:600;transition:.15s}
.user-chip:hover{border-color:var(--pacific)}
.user-avatar{width:30px;height:30px;background:var(--pacific);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0}

/* ── Hero ── */
.lp-hero{background:linear-gradient(135deg,#012840 0%,#023047 40%,#034a6b 70%,#023047 100%);padding:96px 32px 120px;text-align:center;position:relative;overflow:hidden}
.hero-glow{position:absolute;border-radius:50%;pointer-events:none;will-change:opacity}
.hero-glow-1{width:480px;height:480px;background:radial-gradient(circle,rgba(33,158,188,.22) 0%,transparent 70%);top:-120px;left:-120px}
.hero-glow-2{width:380px;height:380px;background:radial-gradient(circle,rgba(255,183,3,.13) 0%,transparent 70%);bottom:-80px;right:-80px}
.hero-glow-3{width:320px;height:320px;background:radial-gradient(circle,rgba(142,202,230,.1) 0%,transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%)}
.lp-hero::before{content:'';position:absolute;inset:0;pointer-events:none;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23219EBC' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
.hero-inner{position:relative;z-index:1}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,183,3,.15);border:1px solid rgba(255,183,3,.3);border-radius:20px;padding:6px 16px;font-size:12px;font-weight:700;color:#FFB703;text-transform:uppercase;letter-spacing:1px;margin-bottom:28px;animation:fadeInDown .6s ease both}
.hero-h1{font-family:'Syne',sans-serif;font-size:clamp(34px,5.5vw,60px);font-weight:800;color:#fff;line-height:1.12;margin-bottom:22px;text-wrap:balance;animation:fadeInUp .7s .1s ease both}
.hero-h1 span{color:#8ECAE6}
.hero-p{font-size:18px;color:rgba(255,255,255,.72);max-width:580px;margin:0 auto 40px;line-height:1.75;animation:fadeInUp .7s .2s ease both}
.hero-actions{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;animation:fadeInUp .7s .3s ease both}
.btn-hero-primary{padding:15px 34px;background:#FFB703;color:#023047;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:16px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:10px;transition:background .2s,box-shadow .2s;letter-spacing:.3px;box-shadow:0 4px 20px rgba(255,183,3,.35);will-change:auto}
.btn-hero-primary:hover{background:#e6a600;box-shadow:0 8px 28px rgba(255,183,3,.45)}
.btn-hero-secondary{padding:15px 28px;background:rgba(255,255,255,.08);color:#fff;border:1.5px solid rgba(255,255,255,.22);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:background .2s,border-color .2s}
.btn-hero-secondary:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.4)}
/* floating cards decoration */
.hero-floats{position:absolute;inset:0;pointer-events:none;transform:translateZ(0);isolation:isolate}
.hero-float{position:absolute;border-radius:16px;border:1px solid rgba(255,255,255,.12);padding:14px 18px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;color:#fff;will-change:transform;animation:float 4s ease-in-out infinite;transform:translateZ(0)}
.hero-float-1{background:rgba(33,158,188,.25);left:5%;top:20%;animation-delay:0s}
.hero-float-2{background:rgba(255,183,3,.2);right:5%;top:30%;animation-delay:1.5s}
.hero-float-3{background:rgba(2,48,71,.5);left:8%;bottom:15%;animation-delay:.8s}
.hero-float i{font-size:18px}

/* ── Stats ── */
.lp-stats{background:#f0f7fa;border-top:1px solid #dce8ef;border-bottom:1px solid #dce8ef}
.stats-inner{max-width:960px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);padding:36px 24px;gap:0}
.stat-item{text-align:center;padding:0 20px;border-right:1px solid #dce8ef;position:relative}
.stat-item:last-child{border-right:none}
.stat-num{font-family:'Syne',sans-serif;font-size:36px;font-weight:800;color:var(--prussian);line-height:1}
.stat-suf{font-size:22px;color:var(--pacific)}
.stat-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-top:6px}

/* ── Section commons ── */
.section-inner{max-width:1100px;margin:0 auto}
.section-center{text-align:center}
.section-label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--pacific);margin-bottom:10px}
.section-title{font-family:'Syne',sans-serif;font-size:clamp(22px,3vw,36px);font-weight:800;color:var(--prussian);margin-bottom:12px;line-height:1.2}
.section-sub{font-size:16px;color:#64748b;line-height:1.75;margin-bottom:52px}

/* ── Reveal animation ── */
.reveal{opacity:0;transform:translateY(28px);transition:opacity .6s ease,transform .6s ease}
.reveal.visible{opacity:1;transform:none}
.reveal-delay-1{transition-delay:.1s}
.reveal-delay-2{transition-delay:.2s}
.reveal-delay-3{transition-delay:.3s}
.reveal-delay-4{transition-delay:.4s}
.reveal-delay-5{transition-delay:.5s}
.reveal-delay-6{transition-delay:.6s}

/* ── Features ── */
.lp-features{padding:80px 32px}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}
.feat-card{background:#fff;border:1.5px solid #e8f0f5;border-radius:18px;padding:30px 26px;transition:.3s;position:relative;overflow:hidden}
.feat-card::after{content:'';position:absolute;inset:0;border-radius:18px;background:linear-gradient(135deg,var(--pacific),var(--prussian));opacity:0;transition:.3s}
.feat-card:hover{border-color:transparent;transform:translateY(-6px);box-shadow:0 16px 40px rgba(33,158,188,.14)}
.feat-card:hover::after{opacity:.04}
.feat-icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#e8f5fa,#d0eaf4);display:flex;align-items:center;justify-content:center;margin-bottom:18px;font-size:22px;color:var(--pacific);transition:.3s;position:relative;z-index:1}
.feat-card:hover .feat-icon{background:linear-gradient(135deg,var(--pacific),#1a7d9a);color:#fff;transform:scale(1.08)}
.feat-title{font-size:16px;font-weight:700;color:var(--prussian);margin-bottom:9px;position:relative;z-index:1}
.feat-desc{font-size:14px;color:#64748b;line-height:1.65;position:relative;z-index:1}

/* ── How it works ── */
.lp-howitworks{background:linear-gradient(180deg,#f8fafc 0%,#fff 100%);padding:80px 32px;border-top:1px solid #e8f0f5}
.steps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:32px;position:relative}
.steps-grid::before{content:'';position:absolute;top:36px;left:calc(16.66% + 20px);right:calc(16.66% + 20px);height:2px;background:linear-gradient(90deg,var(--pacific),#8ECAE6);border-radius:2px}
.step-card{background:#fff;border:1.5px solid #e8f0f5;border-radius:20px;padding:32px 24px;text-align:center;transition:.3s;position:relative}
.step-card:hover{border-color:var(--pacific);box-shadow:0 12px 32px rgba(33,158,188,.1);transform:translateY(-4px)}
.step-num{width:56px;height:56px;background:linear-gradient(135deg,var(--pacific),#1a7d9a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#fff;position:relative;z-index:1;box-shadow:0 4px 16px rgba(33,158,188,.3)}
.step-title{font-size:16px;font-weight:700;color:var(--prussian);margin-bottom:10px}
.step-desc{font-size:14px;color:#64748b;line-height:1.65}

/* ── For managers ── */
.lp-managers{padding:80px 32px;background:#fff}
.managers-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start}
.managers-left h2{font-family:'Syne',sans-serif;font-size:clamp(22px,3vw,36px);font-weight:800;color:var(--prussian);line-height:1.2;margin-bottom:14px}
.managers-left p{font-size:16px;color:#64748b;line-height:1.75;margin-bottom:32px}
.managers-feature-list{display:flex;flex-direction:column;gap:14px}
.mgr-feat{display:flex;align-items:flex-start;gap:14px;padding:16px 18px;border:1.5px solid #e8f0f5;border-radius:14px;transition:.25s}
.mgr-feat:hover{border-color:var(--pacific);background:#f7fbfd;transform:translateX(4px)}
.mgr-feat-icon{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#e8f5fa,#d0eaf4);display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--pacific);flex-shrink:0;transition:.25s}
.mgr-feat:hover .mgr-feat-icon{background:linear-gradient(135deg,var(--pacific),#1a7d9a);color:#fff}
.mgr-feat-text strong{display:block;font-size:14px;font-weight:700;color:var(--prussian);margin-bottom:3px}
.mgr-feat-text span{font-size:13px;color:#64748b;line-height:1.5}
.managers-right{position:relative}
.mgr-mockup{background:linear-gradient(145deg,#023047,#034a6b);border-radius:24px;padding:28px;overflow:hidden;position:relative}
.mgr-mockup::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23219EBC' fill-opacity='0.06'%3E%3Ccircle cx='20' cy='20' r='3'/%3E%3C/g%3E%3C/svg%3E")}
.mgr-mockup-bar{display:flex;align-items:center;gap:6px;margin-bottom:20px;position:relative;z-index:1}
.mgr-mockup-dot{width:10px;height:10px;border-radius:50%}
.mgr-stat-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;position:relative;z-index:1}
.mgr-stat-box{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:14px 16px}
.mgr-stat-box-num{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:#fff}
.mgr-stat-box-lbl{font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
.mgr-chart-row{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:14px 16px;position:relative;z-index:1}
.mgr-chart-row p{font-size:12px;color:rgba(255,255,255,.5);margin-bottom:10px;font-weight:600}
.mgr-bar-list{display:flex;flex-direction:column;gap:8px}
.mgr-bar-item{display:flex;align-items:center;gap:10px;font-size:12px;color:rgba(255,255,255,.65)}
.mgr-bar-track{flex:1;height:6px;background:rgba(255,255,255,.1);border-radius:4px;overflow:hidden}
.mgr-bar-fill{height:100%;border-radius:4px;background:linear-gradient(90deg,var(--pacific),#8ECAE6);animation:growBar .8s ease both}
.mgr-badge-row{display:flex;gap:8px;margin-top:12px;position:relative;z-index:1;flex-wrap:wrap}
.mgr-badge{background:rgba(255,183,3,.15);border:1px solid rgba(255,183,3,.3);border-radius:20px;padding:5px 12px;font-size:11px;font-weight:700;color:#FFB703}

/* ── Quiz Cards ── */
.lp-quizzes{background:#f8fafc;border-top:1px solid #e2ecf1;padding:80px 32px}
.quiz-cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-top:0}
.qcard{background:#fff;border:1.5px solid #e8f0f5;border-radius:20px;overflow:hidden;transition:.3s;display:flex;flex-direction:column;position:relative}
.qcard:hover{border-color:var(--pacific);box-shadow:0 12px 36px rgba(33,158,188,.13);transform:translateY(-5px)}
.qcard-top{padding:22px 22px 16px;flex:1}
.qcard-sector-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.qcard-sector{background:#edf6fa;color:var(--pacific);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;padding:4px 11px;border-radius:20px}
.qcard-cert{color:#FFB703;font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px}
.qcard-title{font-size:16px;font-weight:700;color:var(--prussian);line-height:1.35;margin-bottom:10px}
.qcard-desc{font-size:13px;color:#64748b;line-height:1.65;margin-bottom:16px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.qcard-pills{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:4px}
.qcard-pill{display:flex;align-items:center;gap:5px;background:#f0f7fa;border-radius:8px;padding:5px 10px;font-size:12px;font-weight:600;color:#475569}
.qcard-pill i{color:var(--pacific);font-size:11px}
.qcard-bottom{padding:16px 22px 20px;border-top:1px solid #f0f4f7;display:flex;align-items:center;gap:10px}
.btn-start-quiz{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;background:var(--pacific);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;text-decoration:none;transition:.2s;cursor:pointer;flex-shrink:0}
.btn-start-quiz:hover{background:var(--prussian);transform:scale(1.03)}
.btn-login-quiz{display:inline-flex;align-items:center;gap:7px;padding:11px 16px;background:transparent;border:1.5px solid #dce8ef;color:#64748b;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;text-decoration:none;transition:.2s;flex-shrink:0}
.btn-login-quiz:hover{border-color:var(--pacific);color:var(--pacific)}
.qcard-pcount{font-size:11px;color:#94a3b8;margin-left:auto;white-space:nowrap}

/* ── Pagination ── */
.quiz-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:40px;flex-wrap:wrap}
.pag-btn{min-width:38px;height:38px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;text-decoration:none;transition:.15s;border:1.5px solid #e2ecf1;color:#475569;background:#fff}
.pag-btn:hover:not(.pag-active):not(.pag-disabled){border-color:var(--pacific);color:var(--pacific)}
.pag-active{background:var(--pacific);color:#fff;border-color:var(--pacific)}
.pag-disabled{opacity:.35;cursor:default}
.pag-dots{padding:0 4px;color:#94a3b8;font-size:14px;line-height:38px}

/* ── CTA Banner ── */
.lp-cta{background:linear-gradient(135deg,#023047 0%,#034a6b 50%,#023047 100%);padding:80px 32px;text-align:center;position:relative;overflow:hidden}
.lp-cta::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23219EBC' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/svg%3E")}
.cta-inner{position:relative;z-index:1;max-width:620px;margin:0 auto}
.cta-inner h2{font-family:'Syne',sans-serif;font-size:clamp(24px,3.5vw,40px);font-weight:800;color:#fff;margin-bottom:16px;line-height:1.2}
.cta-inner p{font-size:17px;color:rgba(255,255,255,.7);margin-bottom:36px;line-height:1.7}
.cta-actions{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap}

/* ── Footer ── */
.lp-footer{background:var(--prussian);padding:56px 32px 32px}
.footer-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:32px}
.footer-brand p{font-size:13px;color:rgba(142,202,230,.65);line-height:1.75;margin-top:12px;max-width:280px}
.footer-col h4{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.35);margin-bottom:14px}
.footer-col a{display:block;font-size:13px;color:rgba(255,255,255,.55);text-decoration:none;margin-bottom:9px;transition:.15s}
.footer-col a:hover{color:#fff}
.footer-bottom{max-width:1100px;margin:32px auto 0;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}

/* ── Comparativo ── */
.lp-compare{padding:80px 32px;background:#fff;border-top:1px solid #e8f0f5}
.compare-table-wrap{overflow-x:auto;border-radius:18px;border:1.5px solid #e8f0f5;box-shadow:0 8px 30px rgba(2,48,71,.05)}
.compare-table{width:100%;border-collapse:collapse;min-width:720px;font-size:13.5px}
.compare-table th,.compare-table td{padding:14px 16px;text-align:center;border-bottom:1px solid #eef2f6}
.compare-table thead th{background:#f8fafc;font-weight:700;color:var(--prussian);font-size:12.5px;text-transform:uppercase;letter-spacing:.4px}
.compare-table td:first-child,.compare-table th:first-child{text-align:left;font-weight:600;color:#334155;position:sticky;left:0;background:#fff}
.compare-table thead th:first-child{background:#f8fafc}
.compare-table th.compare-us,.compare-table td.compare-us{background:#f0f7fa}
.compare-table thead th.compare-us{background:#e0f0f6;color:var(--pacific)}
.compare-table tbody tr:last-child td{border-bottom:none}
.compare-yes{color:#16a34a;font-size:16px}
.compare-partial{color:#f59e0b;font-size:14px}
.compare-no{color:#cbd5e1;font-size:16px}
.compare-note{font-size:12px;color:#94a3b8;text-align:center;margin-top:16px}

/* ── Certificado / QR ── */
.lp-certflow{padding:80px 32px;background:linear-gradient(180deg,#f8fafc 0%,#fff 100%);border-top:1px solid #e8f0f5}
.certflow-grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}
.certflow-left h2{font-family:'Syne',sans-serif;font-size:clamp(22px,3vw,36px);font-weight:800;color:var(--prussian);line-height:1.2;margin-bottom:14px}
.certflow-left p{font-size:16px;color:#64748b;line-height:1.75;margin-bottom:28px}
.certflow-list{display:flex;flex-direction:column;gap:16px}
.certflow-item{display:flex;align-items:flex-start;gap:14px}
.certflow-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#e8f5fa,#d0eaf4);display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--pacific);flex-shrink:0}
.certflow-item strong{display:block;font-size:14.5px;font-weight:700;color:var(--prussian);margin-bottom:2px}
.certflow-item span{font-size:13.5px;color:#64748b;line-height:1.55}
.cert-mockup{background:#fff;border-radius:20px;border:1.5px solid #e8f0f5;box-shadow:0 20px 50px rgba(2,48,71,.1);padding:28px;position:relative}
.cert-mockup-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--prussian)}
.cert-mockup-head strong{font-family:'Syne',sans-serif;color:var(--prussian);font-size:15px}
.cert-mockup-body{text-align:center;padding:12px 0}
.cert-mockup-body .cert-name{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--prussian);margin:10px 0 4px}
.cert-mockup-body .cert-quiz{font-size:13px;color:#64748b;margin-bottom:18px}
.cert-mockup-foot{display:flex;align-items:center;justify-content:space-between;gap:14px;padding-top:16px;border-top:1px dashed #dce8ef}
.cert-qr{width:64px;height:64px;background:repeating-conic-gradient(#023047 0% 25%,#fff 0% 50%) 0 0/16px 16px;border-radius:8px;border:1px solid #e2ecf1;flex-shrink:0}
.cert-code{font-size:11px;color:#94a3b8;font-family:monospace;line-height:1.6}
.cert-seal{position:absolute;top:-14px;right:24px;background:#FFB703;color:#023047;font-size:11px;font-weight:800;padding:8px 14px;border-radius:20px;box-shadow:0 6px 16px rgba(255,183,3,.4);display:flex;align-items:center;gap:6px}

/* ── Painel ao vivo ── */
.lp-live{padding:80px 32px;background:#fff;border-top:1px solid #e8f0f5}
.live-grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}
.live-mockup{background:linear-gradient(145deg,#023047,#034a6b);border-radius:22px;padding:26px;position:relative;overflow:hidden}
.live-mockup-head{display:flex;align-items:center;gap:8px;margin-bottom:18px}
.live-dot{width:8px;height:8px;border-radius:50%;background:#ef4444;animation:pulse 1.6s ease-in-out infinite}
.live-mockup-head span{font-size:12px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.5px}
.live-row{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);border-radius:12px;padding:12px 14px;margin-bottom:10px}
.live-avatar{width:32px;height:32px;border-radius:50%;background:var(--pacific);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.live-row-name{font-size:13px;font-weight:700;color:#fff}
.live-row-sub{font-size:11.5px;color:rgba(255,255,255,.5)}
.live-progress-track{flex:1;height:6px;background:rgba(255,255,255,.12);border-radius:4px;overflow:hidden;margin:0 4px}
.live-progress-fill{height:100%;background:linear-gradient(90deg,var(--pacific),#8ECAE6);border-radius:4px}
.live-pct{font-size:12px;font-weight:700;color:#8ECAE6;width:36px;text-align:right;flex-shrink:0}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}

/* ── Trust / Auditoria ── */
.lp-trust{padding:64px 32px;background:linear-gradient(180deg,#f8fafc 0%,#fff 100%);border-top:1px solid #e8f0f5}
.trust-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;max-width:1000px;margin:0 auto}
.trust-item{text-align:center;padding:24px 16px}
.trust-item i{font-size:28px;color:var(--pacific);margin-bottom:12px;display:block}
.trust-item strong{display:block;font-size:14px;font-weight:700;color:var(--prussian);margin-bottom:4px}
.trust-item span{font-size:12.5px;color:#64748b;line-height:1.5}
.trust-badge-row{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:36px;flex-wrap:wrap}
.trust-flag{display:inline-flex;align-items:center;gap:8px;background:#f0f7fa;border:1px solid #dce8ef;border-radius:24px;padding:9px 18px;font-size:13px;font-weight:600;color:var(--prussian)}

/* ── Empty state ── */
.empty-quizzes{text-align:center;padding:64px 24px;color:#94a3b8;grid-column:1/-1}
.empty-quizzes i{font-size:52px;display:block;margin-bottom:16px;color:#cbd5e1}

/* ── Keyframes ── */
@keyframes fadeInDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:none}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
@keyframes growBar{from{width:0}to{width:var(--w)}}

/* ── Mobile ── */
@media(max-width:900px){
  .steps-grid{grid-template-columns:1fr;gap:16px}
  .steps-grid::before{display:none}
  .managers-grid{grid-template-columns:1fr}
  .managers-right{display:none}
  .certflow-grid{grid-template-columns:1fr}
  .live-grid{grid-template-columns:1fr}
  .trust-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  .lp-nav-links{display:none}
  .stats-inner{grid-template-columns:1fr 1fr}
  .stat-item:nth-child(2){border-right:none}
  .stat-item:nth-child(3){border-top:1px solid #dce8ef}
  .footer-inner{grid-template-columns:1fr}
  .lp-hero{padding:72px 20px 96px}
  .hero-floats{display:none}
  .quiz-cards-grid{grid-template-columns:1fr}
  .lp-nav{padding:0 14px;gap:10px}
  .lp-nav-auth{gap:6px}
  .btn-ghost,.btn-cta{padding:8px 12px;font-size:13px;gap:5px}
  .lp-features,.lp-howitworks,.lp-managers,.lp-quizzes,.lp-cta,.lp-compare,.lp-certflow,.lp-live,.lp-trust{padding:56px 20px}
  .trust-grid{grid-template-columns:1fr 1fr}
  .lp-footer{padding:48px 20px 24px}
  .section-sub{margin-bottom:36px}
  .footer-brand p{max-width:none}
}
@media(max-width:600px){
  .lp-nav{padding:0 10px}
  .btn-ghost,.btn-cta{padding:9px;font-size:0}
  .btn-ghost i,.btn-cta i{font-size:16px}
}
@media(prefers-reduced-motion:reduce){
  .hero-float,.reveal{animation:none;transition:none;opacity:1;transform:none}
}
</style>
</head>
<body>

<!-- ══ NAVBAR ══════════════════════════════════════════════════════ -->
<nav class="lp-nav" role="navigation" aria-label="Navegação principal">
  <a class="lp-nav-logo" href="index.php">
    <?php if ($logoPath): ?>
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= $companyName ?>" height="34"/>
    <?php else: ?>
      <img src="assets/logo.svg" alt="PageQuiz" height="34"/>
    <?php endif; ?>
  </a>
  <div class="lp-nav-links">
    <a href="#features">Recursos</a>
    <?php if (!$tenant): ?><a href="#comparativo">Comparativo</a><?php endif; ?>
    <?php if ($tenant): ?><a href="#quizzes">Quizzes</a><?php endif; ?>
    <?php if ($currentUser && !$tenant): ?><a href="user/dashboard.php">Meus treinamentos</a><?php endif; ?>
    <a href="verify.php">Verificar Certificado</a>
  </div>
  <div class="lp-nav-spacer"></div>
  <div class="lp-nav-auth">
    <?php if ($currentUser): ?>
      <a href="user/dashboard.php" class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($currentUser['name'],0,2)) ?></div>
        <?= htmlspecialchars($currentUser['name']) ?>
      </a>
      <a href="user/logout.php" class="btn-ghost">
        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sair
      </a>
    <?php else: ?>
      <a href="user/login.php" class="btn-ghost">
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Colaborador
      </a>
      <?php if ($tenant): ?>
      <a href="user/register.php" class="btn-cta">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Criar conta
      </a>
      <?php else: ?>
      <a href="admin/login.php" class="btn-ghost">
        <i class="fa-solid fa-building" aria-hidden="true"></i> Empresa
      </a>
      <a href="cadastro.php" class="btn-cta">
        <i class="fa-solid fa-building-circle-arrow-right" aria-hidden="true"></i> Cadastrar empresa
      </a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</nav>

<!-- ══ HERO ════════════════════════════════════════════════════════ -->
<section class="lp-hero" aria-labelledby="hero-title">
  <div class="hero-glow hero-glow-1"></div>
  <div class="hero-glow hero-glow-2"></div>
  <div class="hero-glow hero-glow-3"></div>
  <!-- decorative floating cards (container isolado para não vazar repaint) -->
  <div class="hero-floats">
    <div class="hero-float hero-float-1">
      <i class="fa-solid fa-award" style="color:#FFB703"></i>
      <span>Certificado emitido!</span>
    </div>
    <div class="hero-float hero-float-2">
      <i class="fa-solid fa-chart-line" style="color:#8ECAE6"></i>
      <span>92% aprovação</span>
    </div>
    <div class="hero-float hero-float-3">
      <i class="fa-solid fa-users" style="color:#8ECAE6"></i>
      <span>+<?= max((int)$stats['done'], 1) ?> participações</span>
    </div>
  </div>
  <div class="hero-inner">
    <div class="hero-badge">
      <i class="fa-solid fa-bolt" aria-hidden="true"></i>
      <?= $tenant ? htmlspecialchars($tenant['name']) : 'Plataforma de Treinamento Profissional' ?>
    </div>
    <h1 class="hero-h1" id="hero-title">
      <?php if ($tenant): ?>
        Bem-vindo ao treinamento<br/><span><?= $companyName ?></span>
      <?php else: ?>
        Treine sua equipe com<br/><span>quizzes inteligentes</span>
      <?php endif; ?>
    </h1>
    <p class="hero-p">
      <?= $tenant
          ? 'Realize os quizzes disponíveis, acompanhe seu desempenho e receba certificados verificáveis automaticamente.'
          : 'Crie avaliações personalizadas, acompanhe o desempenho em tempo real e emita certificados automáticos para os aprovados.'
      ?>
    </p>
    <div class="hero-actions">
      <?php if ($currentUser): ?>
        <?php if ($tenant): ?>
        <a href="#quizzes" class="btn-hero-primary">
          <i class="fa-solid fa-play" aria-hidden="true"></i> Ver quizzes disponíveis
        </a>
        <a href="user/dashboard.php" class="btn-hero-secondary">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Meu painel
        </a>
        <?php else: ?>
        <a href="user/dashboard.php" class="btn-hero-primary">
          <i class="fa-solid fa-list-check" aria-hidden="true"></i> Acessar meu painel
        </a>
        <a href="user/logout.php" class="btn-hero-secondary">
          <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sair
        </a>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($tenant): ?>
        <a href="user/register.php" class="btn-hero-primary">
          <i class="fa-solid fa-rocket" aria-hidden="true"></i> Criar conta grátis
        </a>
        <a href="user/login.php" class="btn-hero-secondary">
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Entrar
        </a>
        <?php else: ?>
        <a href="cadastro.php" class="btn-hero-primary">
          <i class="fa-solid fa-building" aria-hidden="true"></i> Cadastrar minha empresa
        </a>
        <a href="user/login.php" class="btn-hero-secondary">
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sou colaborador
        </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══ STATS ═══════════════════════════════════════════════════════ -->
<div class="lp-stats">
  <div class="stats-inner">
    <div class="stat-item">
      <div class="stat-num" data-target="<?= $stats['quizzes'] ?>">0</div>
      <div class="stat-lbl">Quizzes Ativos</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="<?= $stats['done'] ?>">0</div>
      <div class="stat-lbl">Participações</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="<?= $stats['pass_rate'] ?>" data-suffix="%">0<span class="stat-suf">%</span></div>
      <div class="stat-lbl">Taxa de Aprovação</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="<?= $stats['sectors'] ?>">0</div>
      <div class="stat-lbl">Setores</div>
    </div>
  </div>
</div>

<!-- ══ BANNER COLABORADOR LOGADO (landing sem tenant) ══════════════ -->
<?php if ($currentUser && !$tenant): ?>
<div style="background:linear-gradient(135deg,#f0f7fa 0%,#e8f4f8 100%);border-top:1px solid #c9e2ec;border-bottom:1px solid #c9e2ec;padding:32px 24px">
  <div style="max-width:820px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:16px;flex:1;min-width:0">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--pacific);color:#fff;font-size:18px;font-weight:800;font-family:'Syne',sans-serif;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <?= strtoupper(mb_substr($currentUser['name'],0,2)) ?>
      </div>
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--prussian)">
          Olá, <?= htmlspecialchars($currentUser['name']) ?>!
        </div>
        <div style="font-size:13px;color:#475569;margin-top:3px">
          Acesse seu painel para ver os treinamentos disponíveis para você.
        </div>
      </div>
    </div>
    <a href="user/dashboard.php"
       style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:var(--pacific);color:#fff;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;transition:.2s;white-space:nowrap;flex-shrink:0"
       onmouseover="this.style.background='var(--prussian)'" onmouseout="this.style.background='var(--pacific)'">
      <i class="fa-solid fa-list-check" aria-hidden="true"></i>
      Ver meus treinamentos
      <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
    </a>
  </div>
</div>
<?php endif; ?>

<!-- ══ FEATURES ════════════════════════════════════════════════════ -->
<section class="lp-features" id="features" aria-labelledby="feat-title">
  <div class="section-inner section-center">
    <div class="section-label reveal">Recursos</div>
    <h2 class="section-title reveal reveal-delay-1" id="feat-title">Tudo que você precisa para treinar</h2>
    <p class="section-sub reveal reveal-delay-2" style="margin:0 auto 52px">Do quiz ao certificado — uma plataforma completa para gestão de treinamentos corporativos.</p>
    <div class="features-grid">
      <div class="feat-card reveal reveal-delay-1">
        <div class="feat-icon"><i class="fa-solid fa-stopwatch" aria-hidden="true"></i></div>
        <div class="feat-title">Timer por questão</div>
        <div class="feat-desc">Controle o tempo de cada questão individualmente. O sistema avança automaticamente quando o tempo esgota.</div>
      </div>
      <div class="feat-card reveal reveal-delay-2">
        <div class="feat-icon"><i class="fa-solid fa-award" aria-hidden="true"></i></div>
        <div class="feat-title">Certificação automática</div>
        <div class="feat-desc">Participantes aprovados recebem certificado verificável com QR Code e código de autenticidade único.</div>
      </div>
      <div class="feat-card reveal reveal-delay-3">
        <div class="feat-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></div>
        <div class="feat-title">Dashboard em tempo real</div>
        <div class="feat-desc">Acompanhe aprovações, médias e desempenho por setor no painel administrativo com gráficos interativos.</div>
      </div>
      <div class="feat-card reveal reveal-delay-4">
        <div class="feat-icon"><i class="fa-solid fa-shuffle" aria-hidden="true"></i></div>
        <div class="feat-title">Questões aleatórias</div>
        <div class="feat-desc">Embaralhe questões e limite quantas serão exibidas, tornando cada aplicação única e anti-cola.</div>
      </div>
      <div class="feat-card reveal reveal-delay-5">
        <div class="feat-icon"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></div>
        <div class="feat-title">100% responsivo</div>
        <div class="feat-desc">Funciona em celular, tablet e desktop. Acesso via QR Code para facilitar a entrada em treinamentos presenciais.</div>
      </div>
      <div class="feat-card reveal reveal-delay-6">
        <div class="feat-icon"><i class="fa-solid fa-file-csv" aria-hidden="true"></i></div>
        <div class="feat-title">Importação em lote</div>
        <div class="feat-desc">Cadastre centenas de questões de uma vez via planilha CSV. Exporte resultados para Excel com um clique.</div>
      </div>
    </div>
  </div>
</section>

<!-- ══ HOW IT WORKS ════════════════════════════════════════════════ -->
<section class="lp-howitworks" id="como-funciona" aria-labelledby="hiw-title">
  <div class="section-inner section-center">
    <div class="section-label reveal">Como funciona</div>
    <h2 class="section-title reveal reveal-delay-1" id="hiw-title">Três passos para o treinamento completo</h2>
    <p class="section-sub reveal reveal-delay-2" style="margin:0 auto 52px">Sem complexidade, sem instalação. Do cadastro ao certificado em minutos.</p>
    <div class="steps-grid">
      <div class="step-card reveal reveal-delay-1">
        <div class="step-num">1</div>
        <div class="step-title">Crie o quiz</div>
        <div class="step-desc">Cadastre perguntas manualmente ou importe via CSV. Configure timer, aprovação mínima, randomização e expiração.</div>
      </div>
      <div class="step-card reveal reveal-delay-2">
        <div class="step-num">2</div>
        <div class="step-title">Compartilhe o link</div>
        <div class="step-desc">Envie o link ou QR Code para os participantes. Eles acessam de qualquer dispositivo, sem instalação.</div>
      </div>
      <div class="step-card reveal reveal-delay-3">
        <div class="step-num">3</div>
        <div class="step-title">Acompanhe e certifique</div>
        <div class="step-desc">Veja resultados em tempo real. Aprovados recebem certificado verificável automaticamente com código único.</div>
      </div>
    </div>
  </div>
</section>

<!-- ══ FOR MANAGERS ════════════════════════════════════════════════ -->
<section class="lp-managers" id="gestores" aria-labelledby="mgr-title">
  <div class="section-inner">
    <div class="managers-grid">
      <div class="managers-left reveal">
        <div class="section-label">Para gestores</div>
        <h2 id="mgr-title">Controle total na palma da mão</h2>
        <p>O painel administrativo oferece visibilidade completa sobre o desempenho da equipe, emissão de certificados e gestão de treinamentos — tudo em um só lugar.</p>
        <div class="managers-feature-list">
          <div class="mgr-feat">
            <div class="mgr-feat-icon"><i class="fa-solid fa-chart-bar" aria-hidden="true"></i></div>
            <div class="mgr-feat-text">
              <strong>Relatórios e resultados</strong>
              <span>Visualize aprovações, reprovações e notas individuais por quiz e por setor.</span>
            </div>
          </div>
          <div class="mgr-feat">
            <div class="mgr-feat-icon"><i class="fa-solid fa-certificate" aria-hidden="true"></i></div>
            <div class="mgr-feat-text">
              <strong>Gestão de certificados</strong>
              <span>Emissão automática com QR Code verificável e histórico completo por participante.</span>
            </div>
          </div>
          <div class="mgr-feat">
            <div class="mgr-feat-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></div>
            <div class="mgr-feat-text">
              <strong>Gestão de usuários</strong>
              <span>Cadastre participantes, redefina senhas e acompanhe o histórico de cada colaborador.</span>
            </div>
          </div>
          <div class="mgr-feat">
            <div class="mgr-feat-icon"><i class="fa-solid fa-file-export" aria-hidden="true"></i></div>
            <div class="mgr-feat-text">
              <strong>Exportação de dados</strong>
              <span>Exporte resultados e listas de aprovados em CSV/Excel para integrar com seu RH.</span>
            </div>
          </div>
        </div>
      </div>
      <div class="managers-right reveal reveal-delay-2">
        <div class="mgr-mockup">
          <div class="mgr-mockup-bar">
            <div class="mgr-mockup-dot" style="background:#FF5F57"></div>
            <div class="mgr-mockup-dot" style="background:#FEBC2E"></div>
            <div class="mgr-mockup-dot" style="background:#28C840"></div>
            <span style="font-size:12px;color:rgba(255,255,255,.4);margin-left:8px">Dashboard · PageQuiz</span>
          </div>
          <div class="mgr-stat-row">
            <div class="mgr-stat-box">
              <div class="mgr-stat-box-num"><?= $stats['done'] ?></div>
              <div class="mgr-stat-box-lbl">Participações</div>
            </div>
            <div class="mgr-stat-box">
              <div class="mgr-stat-box-num"><?= $stats['pass_rate'] ?>%</div>
              <div class="mgr-stat-box-lbl">Aprovação</div>
            </div>
          </div>
          <div class="mgr-chart-row">
            <p><i class="fa-solid fa-chart-bar" style="margin-right:6px"></i>Desempenho por setor</p>
            <div class="mgr-bar-list">
              <div class="mgr-bar-item">
                <span style="width:80px;text-align:left">Segurança</span>
                <div class="mgr-bar-track"><div class="mgr-bar-fill" style="--w:88%;width:88%"></div></div>
                <span>88%</span>
              </div>
              <div class="mgr-bar-item">
                <span style="width:80px;text-align:left">RH</span>
                <div class="mgr-bar-track"><div class="mgr-bar-fill" style="--w:74%;width:74%"></div></div>
                <span>74%</span>
              </div>
              <div class="mgr-bar-item">
                <span style="width:80px;text-align:left">Operações</span>
                <div class="mgr-bar-track"><div class="mgr-bar-fill" style="--w:92%;width:92%"></div></div>
                <span>92%</span>
              </div>
            </div>
          </div>
          <div class="mgr-badge-row">
            <span class="mgr-badge"><i class="fa-solid fa-award"></i> 3 certificados emitidos hoje</span>
            <span class="mgr-badge"><i class="fa-solid fa-bolt"></i> Ao vivo</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ COMPARATIVO COM O MERCADO (apenas na landing institucional) ══ -->
<?php if (!$tenant): ?>
<section class="lp-compare" id="comparativo" aria-labelledby="compare-title">
  <div class="section-inner section-center">
    <div class="section-label reveal">Comparativo</div>
    <h2 class="section-title reveal reveal-delay-1" id="compare-title">Mais do que um formulário de perguntas</h2>
    <p class="section-sub reveal reveal-delay-2" style="margin:0 auto 40px">Veja como o PageQuiz se compara às ferramentas mais usadas para treinamento e avaliação.</p>
    <div class="compare-table-wrap reveal reveal-delay-3">
      <table class="compare-table">
        <thead>
          <tr>
            <th>Funcionalidade</th>
            <th class="compare-us">PageQuiz</th>
            <th>Google Forms</th>
            <th>Kahoot</th>
            <th>Quizizz</th>
            <th>Moodle</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Treinamento corporativo</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-check compare-yes"></i></td>
          </tr>
          <tr>
            <td>Certificado automático</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
          </tr>
          <tr>
            <td>Certificado verificável por QR Code</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
          </tr>
          <tr>
            <td>Portal exclusivo por empresa</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
          </tr>
          <tr>
            <td>Gestão por setores</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
          </tr>
          <tr>
            <td>Sorteio de questões</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-check compare-yes"></i></td>
          </tr>
          <tr>
            <td>Importação em lote (CSV)</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
          </tr>
          <tr>
            <td>Dashboard corporativo</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-check compare-yes"></i></td>
          </tr>
          <tr>
            <td>Monitoramento ao vivo</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
          </tr>
          <tr>
            <td>Exportação para auditoria</td>
            <td class="compare-us"><i class="fa-solid fa-circle-check compare-yes"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-xmark compare-no"></i></td>
            <td><i class="fa-solid fa-circle-minus compare-partial"></i></td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="compare-note reveal reveal-delay-4"><i class="fa-solid fa-circle-check compare-yes"></i> Recurso nativo &nbsp;·&nbsp; <i class="fa-solid fa-circle-minus compare-partial"></i> Suporte parcial/limitado &nbsp;·&nbsp; <i class="fa-solid fa-circle-xmark compare-no"></i> Não disponível</p>
  </div>
</section>
<?php endif; ?>

<!-- ══ CERTIFICAÇÃO VERIFICÁVEL (apenas na landing institucional) ═══ -->
<?php if (!$tenant): ?>
<section class="lp-certflow" id="certificacao" aria-labelledby="certflow-title">
  <div class="section-inner">
    <div class="certflow-grid">
      <div class="certflow-left reveal">
        <div class="section-label">Certificação</div>
        <h2 id="certflow-title">Certificados que qualquer pessoa pode verificar</h2>
        <p>Ao ser aprovado, o participante recebe um certificado em PDF com validação pública — ideal para RH, compliance e auditorias externas.</p>
        <div class="certflow-list">
          <div class="certflow-item">
            <div class="certflow-icon"><i class="fa-solid fa-fingerprint"></i></div>
            <div><strong>Identificador único</strong><span>Cada certificado recebe um código exclusivo, impossível de duplicar.</span></div>
          </div>
          <div class="certflow-item">
            <div class="certflow-icon"><i class="fa-solid fa-qrcode"></i></div>
            <div><strong>QR Code de validação</strong><span>Basta escanear para conferir a autenticidade em segundos.</span></div>
          </div>
          <div class="certflow-item">
            <div class="certflow-icon"><i class="fa-solid fa-globe"></i></div>
            <div><strong>Página pública de verificação</strong><span>Qualquer terceiro pode confirmar o certificado sem precisar de login.</span></div>
          </div>
          <div class="certflow-item">
            <div class="certflow-icon"><i class="fa-solid fa-clipboard-check"></i></div>
            <div><strong>Pronto para auditorias</strong><span>Atende processos ligados a ISO, ONA e programas internos de qualidade.</span></div>
          </div>
        </div>
      </div>
      <div class="certflow-right reveal reveal-delay-2">
        <div class="cert-mockup">
          <div class="cert-seal"><i class="fa-solid fa-award"></i> Verificado</div>
          <div class="cert-mockup-head">
            <strong>Certificado de Conclusão</strong>
            <img src="assets/logo.svg" alt="PageQuiz" height="20"/>
          </div>
          <div class="cert-mockup-body">
            <i class="fa-solid fa-certificate" style="font-size:32px;color:#FFB703"></i>
            <div class="cert-name">Maria da Silva</div>
            <div class="cert-quiz">Concluiu "Segurança do Trabalho — NR-35" com 92% de aproveitamento</div>
          </div>
          <div class="cert-mockup-foot">
            <div class="cert-qr" role="img" aria-label="QR Code de verificação"></div>
            <div class="cert-code">
              ID: PQ-8F42-91AC<br/>
              Emitido em 23/07/2026<br/>
              quiz.pageup.net.br/verify
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ PAINEL AO VIVO (apenas na landing institucional) ═════════════ -->
<?php if (!$tenant): ?>
<section class="lp-live" id="ao-vivo" aria-labelledby="live-title">
  <div class="section-inner">
    <div class="live-grid">
      <div class="live-mockup reveal">
        <div class="live-mockup-head">
          <span class="live-dot"></span>
          <span>Ao vivo · Segurança do Trabalho</span>
        </div>
        <div class="live-row">
          <div class="live-avatar">JP</div>
          <div style="flex:1;min-width:0">
            <div class="live-row-name">João Pereira</div>
            <div class="live-row-sub">Questão 8 de 20</div>
          </div>
          <div class="live-progress-track"><div class="live-progress-fill" style="width:40%"></div></div>
          <div class="live-pct">40%</div>
        </div>
        <div class="live-row">
          <div class="live-avatar">AC</div>
          <div style="flex:1;min-width:0">
            <div class="live-row-name">Ana Costa</div>
            <div class="live-row-sub">Questão 15 de 20</div>
          </div>
          <div class="live-progress-track"><div class="live-progress-fill" style="width:75%"></div></div>
          <div class="live-pct">75%</div>
        </div>
        <div class="live-row">
          <div class="live-avatar">RL</div>
          <div style="flex:1;min-width:0">
            <div class="live-row-name">Rafael Lima</div>
            <div class="live-row-sub">Concluído — Aprovado</div>
          </div>
          <div class="live-progress-track"><div class="live-progress-fill" style="width:100%"></div></div>
          <div class="live-pct">100%</div>
        </div>
      </div>
      <div class="certflow-left reveal reveal-delay-2">
        <div class="section-label">Monitoramento</div>
        <h2>Acompanhe cada participante em tempo real</h2>
        <p>Sem precisar atualizar a página: veja quem está respondendo, em qual questão está e o progresso individual — ótimo para treinamentos presenciais ou remotos em grupo.</p>
        <div class="certflow-list">
          <div class="certflow-item">
            <div class="certflow-icon"><i class="fa-solid fa-eye"></i></div>
            <div><strong>Progresso individual</strong><span>Veja em qual questão cada participante está agora.</span></div>
          </div>
          <div class="certflow-item">
            <div class="certflow-icon"><i class="fa-solid fa-bolt"></i></div>
            <div><strong>Atualização automática</strong><span>Os dados são atualizados sem precisar recarregar a página.</span></div>
          </div>
          <div class="certflow-item">
            <div class="certflow-icon"><i class="fa-solid fa-user-group"></i></div>
            <div><strong>Ideal para capacitações em grupo</strong><span>Perfeito para treinamentos presenciais com dezenas de colaboradores simultâneos.</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ AUDITORIA / CONFIANÇA (apenas na landing institucional) ══════ -->
<?php if (!$tenant): ?>
<section class="lp-trust" aria-labelledby="trust-title">
  <div class="section-inner section-center">
    <div class="section-label reveal">Confiança e conformidade</div>
    <h2 class="section-title reveal reveal-delay-1" id="trust-title">Preparado para auditorias reais</h2>
    <p class="section-sub reveal reveal-delay-2" style="margin:0 auto 40px">Hospitais, clínicas, indústrias e empresas certificadas usam o PageQuiz para comprovar treinamentos realizados.</p>
    <div class="trust-grid">
      <div class="trust-item reveal reveal-delay-1">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <strong>Histórico completo</strong>
        <span>Todas as tentativas e resultados ficam registrados por participante.</span>
      </div>
      <div class="trust-item reveal reveal-delay-2">
        <i class="fa-solid fa-file-export"></i>
        <strong>Relatórios exportáveis</strong>
        <span>Exporte resultados e listas de aprovados em CSV/Excel.</span>
      </div>
      <div class="trust-item reveal reveal-delay-3">
        <i class="fa-solid fa-certificate"></i>
        <strong>Certificados verificáveis</strong>
        <span>Validação pública por QR Code, sem necessidade de login.</span>
      </div>
      <div class="trust-item reveal reveal-delay-4">
        <i class="fa-solid fa-shield-halved"></i>
        <strong>Evidências digitais</strong>
        <span>Dados prontos para apresentar em processos de ISO, ONA e compliance.</span>
      </div>
    </div>
    <div class="trust-badge-row reveal reveal-delay-5">
      <span class="trust-flag"><i class="fa-solid fa-map-location-dot" style="color:var(--pacific)"></i> Desenvolvido em Rondônia, para todo o Brasil</span>
      <span class="trust-flag"><i class="fa-solid fa-building-shield" style="color:var(--pacific)"></i> PageUp Sistemas</span>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ QUIZ CARDS (apenas no portal da empresa) ═════════════════════ -->
<?php if ($tenant): ?>
<section class="lp-quizzes" id="quizzes" aria-labelledby="quiz-list-title">
  <div class="section-inner">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:40px">
      <div class="reveal">
        <div class="section-label">Disponíveis agora</div>
        <h2 class="section-title" id="quiz-list-title" style="margin-bottom:4px">Quizzes disponíveis</h2>
        <p style="font-size:14px;color:#64748b"><?= $totalQuizzes ?> quiz<?= $totalQuizzes !== 1 ? 'zes' : '' ?> ativo<?= $totalQuizzes !== 1 ? 's' : '' ?></p>
      </div>
      <?php if ($currentUser): ?>
      <a href="user/dashboard.php" class="btn-ghost reveal" style="font-size:13px">
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Meu histórico
      </a>
      <?php endif; ?>
    </div>

    <div class="quiz-cards-grid">
    <?php if (empty($quizzes)): ?>
      <div class="empty-quizzes">
        <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
        <strong style="display:block;font-size:16px;color:#475569;margin-bottom:8px">Nenhum quiz disponível no momento</strong>
        <p style="font-size:14px">Novos quizzes serão publicados em breve.</p>
      </div>
    <?php else: ?>
      <?php foreach ($quizzes as $i => $q): ?>
      <div class="qcard reveal reveal-delay-<?= ($i % 3) + 1 ?>">
        <div class="qcard-top">
          <div class="qcard-sector-row">
            <span class="qcard-sector"><?= htmlspecialchars($q['sector']) ?></span>
            <?php if ($q['has_certificate']): ?>
            <span class="qcard-cert"><i class="fa-solid fa-award"></i> Certificado</span>
            <?php endif; ?>
          </div>
          <h3 class="qcard-title"><?= htmlspecialchars($q['title']) ?></h3>
          <?php if ($q['description']): ?>
          <p class="qcard-desc"><?= htmlspecialchars($q['description']) ?></p>
          <?php endif; ?>
          <div class="qcard-pills">
            <span class="qcard-pill"><i class="fa-solid fa-list-ol"></i><?= $q['question_count'] ?> questões<?= $q['max_questions'] > 0 ? ' (até '.$q['max_questions'].')' : '' ?></span>
            <span class="qcard-pill"><i class="fa-solid fa-stopwatch"></i><?= $q['time_per_question'] ?>s / questão</span>
            <span class="qcard-pill"><i class="fa-solid fa-bullseye"></i>≥<?= $q['pass_percentage'] ?>% aprovação</span>
            <?php if ($q['allow_retake']): ?>
            <span class="qcard-pill"><i class="fa-solid fa-rotate"></i>Retentativas</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="qcard-bottom">
          <a href="quiz.php?id=<?= $q['id'] ?><?= $tenant ? '&c='.urlencode($tenant['slug']) : '' ?>" class="btn-start-quiz">
            <i class="fa-solid fa-play" aria-hidden="true"></i> Iniciar
          </a>
          <?php if (!$currentUser): ?>
          <a href="user/login.php?redirect=../quiz.php?id=<?= $q['id'] ?>" class="btn-login-quiz" title="Entrar para salvar progresso">
            <i class="fa-solid fa-right-to-bracket"></i> Entrar
          </a>
          <?php endif; ?>
          <span class="qcard-pcount">
            <i class="fa-solid fa-users"></i>
            <?= $q['participant_count'] ?> participaç<?= $q['participant_count'] != 1 ? 'ões' : 'ão' ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="quiz-pagination" aria-label="Paginação de quizzes">
      <?php
        $base = '?c='.urlencode($tenant['slug']).'&page=';
        $prev = $page > 1 ? $page - 1 : null;
        $next = $page < $totalPages ? $page + 1 : null;
      ?>
      <?php if ($prev): ?>
      <a href="<?= $base.$prev ?>#quizzes" class="pag-btn"><i class="fa-solid fa-chevron-left"></i></a>
      <?php else: ?>
      <span class="pag-btn pag-disabled"><i class="fa-solid fa-chevron-left"></i></span>
      <?php endif; ?>

      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p === $page): ?>
        <span class="pag-btn pag-active"><?= $p ?></span>
        <?php elseif ($p === 1 || $p === $totalPages || abs($p - $page) <= 1): ?>
        <a href="<?= $base.$p ?>#quizzes" class="pag-btn"><?= $p ?></a>
        <?php elseif (abs($p - $page) === 2): ?>
        <span class="pag-dots">…</span>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($next): ?>
      <a href="<?= $base.$next ?>#quizzes" class="pag-btn"><i class="fa-solid fa-chevron-right"></i></a>
      <?php else: ?>
      <span class="pag-btn pag-disabled"><i class="fa-solid fa-chevron-right"></i></span>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

  </div>
</section>
<?php endif; ?>

<!-- ══ CTA ═════════════════════════════════════════════════════════ -->
<?php if (!$currentUser): ?>
<section class="lp-cta" aria-labelledby="cta-title">
  <div class="cta-inner reveal">
    <h2 id="cta-title">Pronto para capacitar sua equipe?</h2>
    <p>Cadastre sua empresa gratuitamente e publique seu primeiro quiz em menos de 5 minutos. Sem cartão de crédito.</p>
    <div class="cta-actions">
      <a href="cadastro.php" class="btn-hero-primary">
        <i class="fa-solid fa-building"></i> Cadastrar minha empresa
      </a>
      <a href="user/login.php" class="btn-hero-secondary">
        <i class="fa-solid fa-right-to-bracket"></i> Sou colaborador
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ FOOTER ══════════════════════════════════════════════════════ -->
<footer class="lp-footer" role="contentinfo">
  <div class="footer-inner">
    <div class="footer-brand">
      <?php if ($logoPath): ?>
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= $companyName ?>" height="34" style="filter:brightness(0) invert(1)"/>
      <?php else: ?>
        <img src="assets/logo-white.svg" alt="PageQuiz" height="34"/>
      <?php endif; ?>
      <p><?= $tenant ? htmlspecialchars($tenant['name']) . ' — Plataforma de treinamento e avaliação corporativa.' : 'Plataforma profissional de treinamento e avaliação corporativa. Simples, eficiente e com certificação automática.' ?></p>
      <a href="https://wa.me/5569993882222" target="_blank" rel="noopener"
         style="color:rgba(255,255,255,.5);font-size:20px;margin-top:14px;display:inline-block;transition:.2s"
         title="WhatsApp PageUp Sistemas">
        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
      </a>
    </div>
    <div class="footer-col">
      <h4>Plataforma</h4>
      <a href="#quizzes">Quizzes disponíveis</a>
      <a href="#features">Recursos</a>
      <a href="#como-funciona">Como funciona</a>
      <a href="verify.php">Verificar certificado</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <a href="termos.php">Termos de Uso</a>
      <a href="cancelamento.php">Cancelamento e Reembolso</a>
      <a href="lgpd.php">LGPD</a>
      <a href="privacidade.php">Privacidade</a>
      <a href="cookies.php">Cookies</a>
      <a href="contato.php">Fale Conosco</a>
    </div>
    <div class="footer-col">
      <h4>Conta</h4>
      <?php if ($currentUser): ?>
        <a href="user/dashboard.php">Meu painel</a>
        <a href="user/logout.php">Sair</a>
      <?php else: ?>
        <a href="user/login.php">Portal do colaborador</a>
        <?php if ($tenant): ?>
        <a href="user/register.php">Criar conta de colaborador</a>
        <?php else: ?>
        <a href="cadastro.php">Cadastrar empresa</a>
        <?php endif; ?>
        <a href="user/forgot-password.php">Esqueci a senha</a>
      <?php endif; ?>
      <a href="admin/login.php" style="margin-top:16px;opacity:.4">Admin — gestores</a>
    </div>
  </div>
  <div style="max-width:1100px;margin:32px auto 0;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <p style="font-size:12px;color:rgba(255,255,255,.3);margin:0">© <?= date('Y') ?> PageQuiz · PageUp Sistemas</p>
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <a href="lgpd.php" style="color:rgba(255,255,255,.35);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.35)'">LGPD</a>
      <a href="privacidade.php" style="color:rgba(255,255,255,.35);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.35)'">Privacidade</a>
      <a href="cookies.php" style="color:rgba(255,255,255,.35);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.35)'">Cookies</a>
      <a href="contato.php" style="color:rgba(255,255,255,.35);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.35)'">Contato</a>
      <a href="verify.php" style="color:rgba(255,255,255,.35);text-decoration:none;font-size:12px">
        <i class="fa-solid fa-shield-halved"></i> Verificar certificado
      </a>
    </div>
  </div>
</footer>

<script>
// ── Navbar shadow on scroll
window.addEventListener('scroll', () => {
    document.querySelector('.lp-nav').classList.toggle('scrolled', window.scrollY > 10);
}, {passive: true});

// ── Animated counters
function animateCounter(el) {
    const target = parseInt(el.dataset.target) || 0;
    const suffix = el.dataset.suffix || '';
    const duration = 1400;
    const start = performance.now();
    const inner = el.querySelector('span.stat-suf');
    const update = (now) => {
        const p = Math.min((now - start) / duration, 1);
        const ease = 1 - Math.pow(1 - p, 3);
        const val = Math.round(ease * target);
        el.childNodes[0].nodeValue = val;
        if (p < 1) requestAnimationFrame(update);
        else el.childNodes[0].nodeValue = target;
    };
    requestAnimationFrame(update);
}

const statsObs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            document.querySelectorAll('.stat-num[data-target]').forEach(animateCounter);
            statsObs.disconnect();
        }
    });
}, {threshold: .5});
const statsEl = document.querySelector('.lp-stats');
if (statsEl) statsObs.observe(statsEl);

// ── Reveal on scroll
const revealObs = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, {threshold: .12, rootMargin: '0px 0px -40px 0px'});
document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));
</script>
<?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>
