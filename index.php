<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user-auth.php';

userSessionStart();
$currentUser = currentUser();

$quizzes = dbRows("
    SELECT q.*, COUNT(DISTINCT p.id) AS participant_count, COUNT(DISTINCT qs.id) AS question_count
    FROM quizzes q
    LEFT JOIN participants p  ON p.quiz_id = q.id
    LEFT JOIN questions   qs  ON qs.quiz_id = q.id
    WHERE q.active = 1
      AND (q.expires_at IS NULL OR q.expires_at = '' OR q.expires_at >= date('now','localtime'))
    GROUP BY q.id
    ORDER BY q.created_at DESC
");

$stats = [
    'quizzes'  => dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE active=1")['c'],
    'done'     => dbRow("SELECT COUNT(*) AS c FROM participants WHERE completed_at IS NOT NULL")['c'],
    'sectors'  => dbRow("SELECT COUNT(*) AS c FROM sectors")['c'],
    'pass_rate'=> dbRow("SELECT ROUND(AVG(passed)*100) AS r FROM participants WHERE completed_at IS NOT NULL")['r'] ?? 0,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<meta name="description" content="PageQuiz — Plataforma profissional de treinamento e avaliação da PageUp Sistemas."/>
<title>PageQuiz · Plataforma de Treinamento</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ── Reset & Base ── */
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;color:#1e293b;background:#fff}

/* ── Navbar ── */
.lp-nav{position:sticky;top:0;z-index:200;background:#fff;border-bottom:1px solid #e2ecf1;height:64px;display:flex;align-items:center;padding:0 32px;gap:24px}
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
.lp-hero{background:linear-gradient(135deg,#023047 0%,#03506f 60%,#023047 100%);padding:80px 32px 100px;text-align:center;position:relative;overflow:hidden}
.lp-hero::before{content:'';position:absolute;inset:0;pointer-events:none;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23219EBC' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,183,3,.15);border:1px solid rgba(255,183,3,.3);border-radius:20px;padding:6px 16px;font-size:12px;font-weight:700;color:#FFB703;text-transform:uppercase;letter-spacing:1px;margin-bottom:24px}
.hero-h1{font-family:'Syne',sans-serif;font-size:clamp(32px,5vw,54px);font-weight:800;color:#fff;line-height:1.15;margin-bottom:20px;text-wrap:balance}
.hero-h1 span{color:#8ECAE6}
.hero-p{font-size:18px;color:rgba(255,255,255,.75);max-width:560px;margin:0 auto 36px;line-height:1.7}
.hero-actions{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap}
.btn-hero-primary{padding:14px 32px;background:#FFB703;color:#023047;border:none;border-radius:12px;font-family:'DM Sans',sans-serif;font-size:16px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:10px;transition:.2s;letter-spacing:.3px}
.btn-hero-primary:hover{background:#e6a600;transform:translateY(-2px)}
.btn-hero-secondary{padding:14px 28px;background:rgba(255,255,255,.1);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:.2s}
.btn-hero-secondary:hover{background:rgba(255,255,255,.18)}

/* ── Stats ── */
.lp-stats{background:#f0f7fa;border-top:1px solid #dce8ef;border-bottom:1px solid #dce8ef}
.stats-inner{max-width:900px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);padding:32px 24px;gap:0}
.stat-item{text-align:center;padding:0 16px;border-right:1px solid #dce8ef}
.stat-item:last-child{border-right:none}
.stat-num{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:var(--prussian)}
.stat-lbl{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-top:4px}

/* ── Features ── */
.lp-features{padding:72px 32px}
.section-inner{max-width:1100px;margin:0 auto}
.section-label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--pacific);margin-bottom:12px}
.section-title{font-family:'Syne',sans-serif;font-size:clamp(22px,3vw,34px);font-weight:800;color:var(--prussian);margin-bottom:12px;line-height:1.2}
.section-sub{font-size:16px;color:#64748b;max-width:520px;line-height:1.7;margin-bottom:48px}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px}
.feat-card{background:#fff;border:1px solid #e2ecf1;border-radius:16px;padding:28px 24px;transition:.2s}
.feat-card:hover{border-color:var(--pacific);box-shadow:0 8px 24px rgba(33,158,188,.08)}
.feat-icon{width:48px;height:48px;border-radius:12px;background:#f0f7fa;display:flex;align-items:center;justify-content:center;margin-bottom:16px;font-size:20px;color:var(--pacific)}
.feat-title{font-size:16px;font-weight:700;color:var(--prussian);margin-bottom:8px}
.feat-desc{font-size:14px;color:#64748b;line-height:1.6}

/* ── Quiz List ── */
.lp-quizzes{background:#f8fafc;border-top:1px solid #e2ecf1;padding:72px 32px}
.quiz-list{display:flex;flex-direction:column;gap:10px;margin-top:0}
.qi{background:#fff;border:1.5px solid #e2ecf1;border-radius:14px;overflow:hidden;transition:border-color .2s}
.qi.open{border-color:var(--pacific)}
.qi-header{display:flex;align-items:center;gap:16px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none}
.qi-header:hover .qi-title{color:var(--pacific)}
.qi-sector{background:#f0f7fa;color:var(--pacific);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;padding:3px 10px;border-radius:20px;white-space:nowrap;flex-shrink:0}
.qi-title{font-size:15px;font-weight:700;color:var(--prussian);flex:1;transition:.15s;line-height:1.3}
.qi-meta{display:flex;align-items:center;gap:14px;flex-shrink:0}
.qi-meta span{display:flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#64748b;white-space:nowrap}
.qi-meta i{color:var(--pacific);font-size:11px}
.qi-chevron{color:var(--gray-300);font-size:13px;transition:transform .25s;flex-shrink:0}
.qi.open .qi-chevron{transform:rotate(90deg)}
.qi-body{display:none;border-top:1px solid #f0f4f7;padding:22px 22px 24px}
.qi.open .qi-body{display:block}
.qi-desc{font-size:14px;color:#64748b;line-height:1.7;margin-bottom:20px}
.qi-details{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:22px}
.qi-detail-pill{display:flex;align-items:center;gap:7px;background:#f0f7fa;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;color:var(--prussian)}
.qi-detail-pill i{color:var(--pacific);font-size:12px}
.qi-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.btn-start-quiz{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:var(--pacific);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;text-decoration:none;transition:.2s;cursor:pointer}
.btn-start-quiz:hover{background:var(--prussian)}
.btn-login-quiz{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;background:transparent;border:1.5px solid var(--pacific);color:var(--pacific);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;text-decoration:none;transition:.2s}
.btn-login-quiz:hover{background:var(--pacific);color:#fff}
.qi-pcount{font-size:12px;color:#94a3b8;margin-left:auto}

/* ── Footer ── */
.lp-footer{background:var(--prussian);padding:48px 32px 32px}
.footer-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:32px}
.footer-brand p{font-size:13px;color:rgba(142,202,230,.7);line-height:1.7;margin-top:12px}
.footer-col h4{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);margin-bottom:14px}
.footer-col a{display:block;font-size:13px;color:rgba(255,255,255,.6);text-decoration:none;margin-bottom:8px;transition:.15s}
.footer-col a:hover{color:#fff}
.footer-bottom{max-width:1100px;margin:32px auto 0;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.footer-bottom p{font-size:12px;color:rgba(255,255,255,.3)}

/* ── Empty state ── */
.empty-quizzes{text-align:center;padding:60px 24px;color:#94a3b8}
.empty-quizzes i{font-size:48px;display:block;margin-bottom:16px;color:#cbd5e1}

/* ── Mobile ── */
@media(max-width:768px){
  .lp-nav-links{display:none}
  .stats-inner{grid-template-columns:1fr 1fr}
  .stat-item:nth-child(2){border-right:none}
  .stat-item:nth-child(3){border-top:1px solid #dce8ef}
  .footer-inner{grid-template-columns:1fr}
  .qi-meta{display:none}
  .lp-hero{padding:60px 24px 80px}
}
</style>
</head>
<body>

<!-- ══ NAVBAR ══════════════════════════════════════════════════════ -->
<nav class="lp-nav" role="navigation" aria-label="Navegação principal">
  <a class="lp-nav-logo" href="index.php">
    <img src="assets/logo.svg" alt="PageQuiz" height="34"/>
  </a>
  <div class="lp-nav-links">
    <a href="#features">Recursos</a>
    <a href="#quizzes">Quizzes</a>
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
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Entrar
      </a>
      <a href="user/register.php" class="btn-cta">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Criar conta
      </a>
    <?php endif; ?>
  </div>
</nav>

<!-- ══ HERO ════════════════════════════════════════════════════════ -->
<section class="lp-hero" aria-labelledby="hero-title">
  <div class="hero-badge">
    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
    Plataforma de Treinamento Profissional
  </div>
  <h1 class="hero-h1" id="hero-title">
    Treine sua equipe com<br/><span>quizzes inteligentes</span>
  </h1>
  <p class="hero-p">
    Crie avaliações personalizadas, acompanhe o desempenho em tempo real e emita certificados automaticamente para os aprovados.
  </p>
  <div class="hero-actions">
    <?php if ($currentUser): ?>
      <a href="#quizzes" class="btn-hero-primary">
        <i class="fa-solid fa-play" aria-hidden="true"></i> Ver quizzes disponíveis
      </a>
      <a href="user/dashboard.php" class="btn-hero-secondary">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i> Meu painel
      </a>
    <?php else: ?>
      <a href="user/register.php" class="btn-hero-primary">
        <i class="fa-solid fa-rocket" aria-hidden="true"></i> Começar gratuitamente
      </a>
      <a href="#quizzes" class="btn-hero-secondary">
        <i class="fa-solid fa-eye" aria-hidden="true"></i> Ver quizzes
      </a>
    <?php endif; ?>
  </div>
</section>

<!-- ══ STATS ═══════════════════════════════════════════════════════ -->
<div class="lp-stats">
  <div class="stats-inner">
    <div class="stat-item">
      <div class="stat-num"><?= $stats['quizzes'] ?></div>
      <div class="stat-lbl">Quizzes Ativos</div>
    </div>
    <div class="stat-item">
      <div class="stat-num"><?= $stats['done'] ?></div>
      <div class="stat-lbl">Participações</div>
    </div>
    <div class="stat-item">
      <div class="stat-num"><?= $stats['pass_rate'] ?>%</div>
      <div class="stat-lbl">Taxa de Aprovação</div>
    </div>
    <div class="stat-item">
      <div class="stat-num"><?= $stats['sectors'] ?></div>
      <div class="stat-lbl">Setores</div>
    </div>
  </div>
</div>

<!-- ══ FEATURES ════════════════════════════════════════════════════ -->
<section class="lp-features" id="features" aria-labelledby="feat-title">
  <div class="section-inner">
    <div class="section-label">Recursos</div>
    <h2 class="section-title" id="feat-title">Tudo que você precisa para treinar</h2>
    <p class="section-sub">Do quiz ao certificado, uma plataforma completa para gestão de treinamentos corporativos.</p>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon"><i class="fa-solid fa-stopwatch" aria-hidden="true"></i></div>
        <div class="feat-title">Timer por questão</div>
        <div class="feat-desc">Controle o tempo de cada questão individualmente. O sistema avança automaticamente quando o tempo esgota.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i class="fa-solid fa-award" aria-hidden="true"></i></div>
        <div class="feat-title">Certificação automática</div>
        <div class="feat-desc">Participantes aprovados recebem um certificado verificável com QR Code e código de autenticidade.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></div>
        <div class="feat-title">Dashboard em tempo real</div>
        <div class="feat-desc">Acompanhe aprovações, médias e desempenho por setor no painel administrativo.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i class="fa-solid fa-shuffle" aria-hidden="true"></i></div>
        <div class="feat-title">Questões aleatórias</div>
        <div class="feat-desc">Embaralhe questões e limite quantas serão apresentadas, tornando cada aplicação única.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></div>
        <div class="feat-title">100% responsivo</div>
        <div class="feat-desc">Funciona perfeitamente em celular, tablet e desktop. Acesso por QR Code para facilitar a entrada.</div>
      </div>
      <div class="feat-card">
        <div class="feat-icon"><i class="fa-solid fa-file-csv" aria-hidden="true"></i></div>
        <div class="feat-title">Importação em lote</div>
        <div class="feat-desc">Cadastre centenas de questões de uma vez via CSV. Exportação de resultados para Excel.</div>
      </div>
    </div>
  </div>
</section>

<!-- ══ QUIZ LIST ════════════════════════════════════════════════════ -->
<section class="lp-quizzes" id="quizzes" aria-labelledby="quiz-list-title">
  <div class="section-inner">
    <div class="section-label">Disponíveis agora</div>
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:32px">
      <div>
        <h2 class="section-title" id="quiz-list-title" style="margin-bottom:0">Quizzes disponíveis</h2>
        <p style="font-size:14px;color:#64748b;margin-top:6px"><?= count($quizzes) ?> quiz<?= count($quizzes) !== 1 ? 'zes' : '' ?> ativo<?= count($quizzes) !== 1 ? 's' : '' ?></p>
      </div>
      <?php if ($currentUser): ?>
      <a href="user/dashboard.php" class="btn-ghost" style="font-size:13px">
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Meu histórico
      </a>
      <?php endif; ?>
    </div>

    <?php if (empty($quizzes)): ?>
    <div class="empty-quizzes">
      <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
      <strong style="display:block;font-size:16px;color:#475569">Nenhum quiz disponível no momento</strong>
      <p style="margin-top:8px;font-size:14px">Novos quizzes serão publicados em breve.</p>
    </div>
    <?php else: ?>
    <div class="quiz-list" role="list">
      <?php foreach ($quizzes as $i => $q): ?>
      <div class="qi" id="qi-<?= $q['id'] ?>" role="listitem">
        <div class="qi-header" onclick="toggleQi(<?= $q['id'] ?>)"
             role="button" aria-expanded="false" aria-controls="qi-body-<?= $q['id'] ?>"
             tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){toggleQi(<?= $q['id'] ?>);event.preventDefault()}">
          <span class="qi-sector"><?= htmlspecialchars($q['sector']) ?></span>
          <h3 class="qi-title"><?= htmlspecialchars($q['title']) ?></h3>
          <div class="qi-meta">
            <span><i class="fa-solid fa-list-ol" aria-hidden="true"></i><?= $q['question_count'] ?> questões</span>
            <span><i class="fa-solid fa-stopwatch" aria-hidden="true"></i><?= $q['time_per_question'] ?>s</span>
            <span><i class="fa-solid fa-bullseye" aria-hidden="true"></i>≥<?= $q['pass_percentage'] ?>%</span>
            <span><i class="fa-solid fa-users" aria-hidden="true"></i><?= $q['participant_count'] ?></span>
          </div>
          <i class="fa-solid fa-chevron-right qi-chevron" aria-hidden="true"></i>
        </div>
        <div class="qi-body" id="qi-body-<?= $q['id'] ?>" hidden>
          <?php if ($q['description']): ?>
          <p class="qi-desc"><?= htmlspecialchars($q['description']) ?></p>
          <?php endif; ?>
          <div class="qi-details">
            <div class="qi-detail-pill">
              <i class="fa-solid fa-list-ol" aria-hidden="true"></i>
              <?= $q['question_count'] ?> questões<?= $q['max_questions'] > 0 ? ' (até ' . $q['max_questions'] . ' por sessão)' : '' ?>
            </div>
            <div class="qi-detail-pill">
              <i class="fa-solid fa-stopwatch" aria-hidden="true"></i>
              <?= $q['time_per_question'] ?> segundos por questão
            </div>
            <div class="qi-detail-pill">
              <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
              Aprovação com <?= $q['pass_percentage'] ?>%
            </div>
            <?php if ($q['has_certificate']): ?>
            <div class="qi-detail-pill">
              <i class="fa-solid fa-award" aria-hidden="true"></i>
              Emite certificado
            </div>
            <?php endif; ?>
            <?php if ($q['allow_retake']): ?>
            <div class="qi-detail-pill">
              <i class="fa-solid fa-rotate" aria-hidden="true"></i>
              Permite nova tentativa
            </div>
            <?php endif; ?>
          </div>
          <div class="qi-actions">
            <a href="quiz.php?id=<?= $q['id'] ?>" class="btn-start-quiz">
              <i class="fa-solid fa-play" aria-hidden="true"></i>
              Iniciar Quiz
            </a>
            <?php if (!$currentUser): ?>
            <a href="user/login.php?redirect=../quiz.php?id=<?= $q['id'] ?>" class="btn-login-quiz">
              <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
              Entrar para salvar progresso
            </a>
            <?php endif; ?>
            <span class="qi-pcount">
              <i class="fa-solid fa-users" aria-hidden="true"></i>
              <?= $q['participant_count'] ?> participação<?= $q['participant_count'] !== '1' ? 'ões' : '' ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ══ FOOTER ══════════════════════════════════════════════════════ -->
<footer class="lp-footer" role="contentinfo">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="assets/logo-white.svg" alt="PageQuiz" height="34"/>
      <p>Plataforma profissional de treinamento e avaliação corporativa. Simples, eficiente e com certificação automática.</p>
      <a href="https://wa.me/5569993882222" target="_blank" rel="noopener"
         style="color:rgba(255,255,255,.5);font-size:18px;margin-top:12px;display:inline-block;transition:.2s"
         title="WhatsApp PageUp Sistemas">
        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
      </a>
    </div>
    <div class="footer-col">
      <h4>Plataforma</h4>
      <a href="#quizzes">Quizzes disponíveis</a>
      <a href="#features">Recursos</a>
      <a href="verify.php">Verificar certificado</a>
      <a href="user/dashboard.php">Meu painel</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
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
        <a href="user/login.php">Entrar</a>
        <a href="user/register.php">Criar conta</a>
        <a href="user/forgot-password.php">Esqueci a senha</a>
      <?php endif; ?>
      <a href="admin/login.php" style="margin-top:16px;opacity:.5">Área administrativa</a>
    </div>
  </div>
  <div style="max-width:1100px;margin:32px auto 0;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <p style="font-size:12px;color:rgba(255,255,255,.3);margin:0">© <?= date('Y') ?> PageQuiz · PageUp Sistemas</p>
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <a href="lgpd.php" style="color:rgba(255,255,255,.4);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.4)'">LGPD</a>
      <a href="privacidade.php" style="color:rgba(255,255,255,.4);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.4)'">Privacidade</a>
      <a href="cookies.php" style="color:rgba(255,255,255,.4);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.4)'">Cookies</a>
      <a href="contato.php" style="color:rgba(255,255,255,.4);text-decoration:none;font-size:12px;transition:.15s" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.4)'">Contato</a>
      <a href="verify.php" style="color:rgba(255,255,255,.4);text-decoration:none;font-size:12px">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Verificar certificado
      </a>
    </div>
  </div>
</footer>

<script>
function toggleQi(id) {
    const qi     = document.getElementById('qi-' + id);
    const body   = document.getElementById('qi-body-' + id);
    const header = qi.querySelector('.qi-header');
    const isOpen = qi.classList.contains('open');

    document.querySelectorAll('.qi.open').forEach(el => {
        el.classList.remove('open');
        el.querySelector('.qi-body').hidden = true;
        el.querySelector('.qi-header').setAttribute('aria-expanded','false');
    });

    if (!isOpen) {
        qi.classList.add('open');
        body.hidden = false;
        header.setAttribute('aria-expanded','true');
        setTimeout(() => qi.scrollIntoView({behavior:'smooth',block:'nearest'}), 50);
    }
}

// Auto-open if URL has #quiz-N
if (window.location.hash.startsWith('#quiz-')) {
    const id = window.location.hash.replace('#quiz-','');
    if (id) toggleQi(id);
}
</script>
</body>
</html>
