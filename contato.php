<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user-auth.php';
userSessionStart();
$currentUser = currentUser();

// Cria tabela de contatos se não existir
getDB()->exec("
    CREATE TABLE IF NOT EXISTS contact_messages (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT NOT NULL,
        email      TEXT NOT NULL,
        subject    TEXT NOT NULL,
        message    TEXT NOT NULL,
        ip         TEXT DEFAULT '',
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )
");

$success = false;
$errors  = [];
$fields  = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

$subjects = [
    'suporte'    => 'Suporte técnico',
    'privacidade'=> 'Privacidade / LGPD',
    'comercial'  => 'Informações comerciais',
    'certificado'=> 'Certificado / verificação',
    'outro'      => 'Outro assunto',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields['name']    = trim($_POST['name']    ?? '');
    $fields['email']   = trim($_POST['email']   ?? '');
    $fields['subject'] = trim($_POST['subject'] ?? '');
    $fields['message'] = trim($_POST['message'] ?? '');

    if (mb_strlen($fields['name']) < 2)          $errors['name']    = 'Informe seu nome completo.';
    if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'E-mail inválido.';
    if (!isset($subjects[$fields['subject']]))   $errors['subject'] = 'Selecione um assunto.';
    if (mb_strlen($fields['message']) < 10)      $errors['message'] = 'Mensagem muito curta (mínimo 10 caracteres).';

    if (empty($errors)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        dbExec(
            "INSERT INTO contact_messages (name, email, subject, message, ip) VALUES (?,?,?,?,?)",
            [$fields['name'], $fields['email'], $subjects[$fields['subject']], $fields['message'], $ip]
        );
        $success = true;
        $fields  = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
    }
}

function f(string $key, array $fields): string {
    return htmlspecialchars($fields[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<title>Fale Conosco · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;color:#1e293b;background:#fff}
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

.page-hero{background:linear-gradient(135deg,#023047 0%,#03506f 60%,#023047 100%);padding:56px 32px 64px;text-align:center}
.page-hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,183,3,.15);border:1px solid rgba(255,183,3,.3);border-radius:20px;padding:6px 16px;font-size:11px;font-weight:700;color:#FFB703;text-transform:uppercase;letter-spacing:1px;margin-bottom:20px}
.page-hero h1{font-family:'Syne',sans-serif;font-size:clamp(26px,4vw,42px);font-weight:800;color:#fff;margin:0 0 12px}
.page-hero p{font-size:15px;color:rgba(255,255,255,.65);margin:0}

.page-content{max-width:960px;margin:0 auto;padding:56px 32px 80px;display:grid;grid-template-columns:1fr 380px;gap:48px;align-items:start}
.page-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:#94a3b8;margin-bottom:32px;grid-column:1/-1}
.page-breadcrumb a{color:#64748b;text-decoration:none;transition:.15s}
.page-breadcrumb a:hover{color:var(--pacific)}
.page-breadcrumb i{font-size:10px}

/* Form */
.contact-form-wrap{min-width:0}
.contact-form-wrap h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--prussian);margin:0 0 6px}
.contact-form-wrap p.sub{font-size:14px;color:#64748b;margin:0 0 28px;line-height:1.6}

.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:6px}
.form-group label span.req{color:var(--pacific)}
.form-control{width:100%;padding:11px 14px;border:1.5px solid #dce8ef;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;color:#1e293b;background:#fff;transition:.2s;outline:none}
.form-control:focus{border-color:var(--pacific);box-shadow:0 0 0 3px rgba(33,158,188,.1)}
.form-control.is-error{border-color:#ef4444}
.form-control.is-error:focus{box-shadow:0 0 0 3px rgba(239,68,68,.1)}
.field-error{font-size:12px;color:#ef4444;margin-top:5px;display:flex;align-items:center;gap:4px}
textarea.form-control{resize:vertical;min-height:140px}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}

.btn-submit{width:100%;padding:14px;background:var(--pacific);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px}
.btn-submit:hover{background:var(--prussian)}

.alert-success{background:#dcfce7;border:1px solid #86efac;border-radius:12px;padding:20px 24px;display:flex;align-items:flex-start;gap:14px;margin-bottom:28px}
.alert-success .ai{font-size:22px;color:#16a34a;flex-shrink:0;margin-top:2px}
.alert-success h3{font-size:15px;font-weight:700;color:#166534;margin:0 0 4px}
.alert-success p{font-size:13px;color:#15803d;margin:0;line-height:1.5}

/* Sidebar info */
.contact-sidebar{display:flex;flex-direction:column;gap:20px}
.contact-card{background:#f8fafc;border:1px solid #e2ecf1;border-radius:14px;padding:22px 20px}
.contact-card h3{font-size:14px;font-weight:700;color:var(--prussian);margin:0 0 14px;display:flex;align-items:center;gap:8px}
.contact-card h3 i{color:var(--pacific)}
.contact-item{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
.contact-item:last-child{margin-bottom:0}
.ci-icon{width:36px;height:36px;background:#e0f2fe;border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--pacific);font-size:14px;flex-shrink:0;margin-top:1px}
.ci-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#94a3b8;margin-bottom:2px}
.ci-value{font-size:13px;color:#334155;font-weight:500}
.ci-value a{color:var(--pacific);text-decoration:none}
.ci-value a:hover{text-decoration:underline}

.legal-links{display:flex;flex-direction:column;gap:8px}
.legal-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #e2ecf1;border-radius:10px;text-decoration:none;color:#475569;font-size:13px;font-weight:500;transition:.15s;background:#fff}
.legal-link:hover{border-color:var(--pacific);color:var(--pacific);background:#f0f7fa}
.legal-link i{color:var(--pacific);font-size:14px;width:16px;text-align:center}

.lp-footer{background:var(--prussian);padding:48px 32px 32px}
.footer-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:40px}
.footer-brand p{font-size:13px;color:rgba(142,202,230,.7);line-height:1.7;margin-top:12px}
.footer-col h4{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);margin-bottom:14px}
.footer-col a{display:block;font-size:13px;color:rgba(255,255,255,.6);text-decoration:none;margin-bottom:8px;transition:.15s}
.footer-col a:hover{color:#fff}
.footer-bottom{max-width:1100px;margin:32px auto 0;padding-top:20px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.footer-bottom p{font-size:12px;color:rgba(255,255,255,.3)}

@media(max-width:768px){
  .lp-nav-links{display:none}
  .footer-inner{grid-template-columns:1fr}
  .page-content{grid-template-columns:1fr;padding:40px 20px 60px;gap:32px}
  .form-row{grid-template-columns:1fr}
}
</style>
</head>
<body>

<nav class="lp-nav" role="navigation" aria-label="Navegação principal">
  <a class="lp-nav-logo" href="index.php">
    <img src="assets/logo.svg" alt="PageQuiz" height="34"/>
  </a>
  <div class="lp-nav-links">
    <a href="index.php#features">Recursos</a>
    <a href="index.php#quizzes">Quizzes</a>
    <a href="verify.php">Verificar Certificado</a>
  </div>
  <div class="lp-nav-spacer"></div>
  <div class="lp-nav-auth">
    <?php if ($currentUser): ?>
      <a href="user/dashboard.php" class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($currentUser['name'],0,2)) ?></div>
        <?= htmlspecialchars($currentUser['name']) ?>
      </a>
      <a href="user/logout.php" class="btn-ghost"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
    <?php else: ?>
      <a href="user/login.php" class="btn-ghost"><i class="fa-solid fa-right-to-bracket"></i> Entrar</a>
      <a href="user/register.php" class="btn-cta"><i class="fa-solid fa-user-plus"></i> Criar conta</a>
    <?php endif; ?>
  </div>
</nav>

<div class="page-hero">
  <div class="page-hero-badge"><i class="fa-solid fa-envelope"></i> Suporte</div>
  <h1>Fale Conosco</h1>
  <p>Estamos aqui para ajudar. Envie sua mensagem e responderemos em até 2 dias úteis</p>
</div>

<div class="page-content">
  <nav class="page-breadcrumb" aria-label="Caminho de navegação">
    <a href="index.php"><i class="fa-solid fa-house"></i> Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Fale Conosco</span>
  </nav>

  <!-- Formulário -->
  <div class="contact-form-wrap">
    <h2>Envie sua mensagem</h2>
    <p class="sub">Preencha o formulário abaixo. Todos os campos marcados com <span style="color:var(--pacific)">*</span> são obrigatórios.</p>

    <?php if ($success): ?>
    <div class="alert-success" role="alert">
      <div class="ai"><i class="fa-solid fa-circle-check"></i></div>
      <div>
        <h3>Mensagem enviada com sucesso!</h3>
        <p>Recebemos sua mensagem e entraremos em contato em até 2 dias úteis no e-mail informado.</p>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="contato.php" novalidate>
      <div class="form-row">
        <div class="form-group">
          <label for="name">Nome completo <span class="req">*</span></label>
          <input type="text" id="name" name="name" class="form-control<?= isset($errors['name']) ? ' is-error' : '' ?>"
                 value="<?= f('name',$fields) ?>" placeholder="Seu nome" maxlength="120" autocomplete="name"/>
          <?php if (isset($errors['name'])): ?>
          <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($errors['name']) ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label for="email">E-mail <span class="req">*</span></label>
          <input type="email" id="email" name="email" class="form-control<?= isset($errors['email']) ? ' is-error' : '' ?>"
                 value="<?= f('email',$fields) ?>" placeholder="seu@email.com" maxlength="180" autocomplete="email"/>
          <?php if (isset($errors['email'])): ?>
          <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($errors['email']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label for="subject">Assunto <span class="req">*</span></label>
        <select id="subject" name="subject" class="form-control<?= isset($errors['subject']) ? ' is-error' : '' ?>">
          <option value="">Selecione um assunto…</option>
          <?php foreach ($subjects as $val => $label): ?>
          <option value="<?= $val ?>"<?= $fields['subject'] === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['subject'])): ?>
        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($errors['subject']) ?></div>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label for="message">Mensagem <span class="req">*</span></label>
        <textarea id="message" name="message" class="form-control<?= isset($errors['message']) ? ' is-error' : '' ?>"
                  placeholder="Descreva sua dúvida, solicitação ou sugestão com o máximo de detalhes possível…"
                  maxlength="3000"><?= f('message',$fields) ?></textarea>
        <?php if (isset($errors['message'])): ?>
        <div class="field-error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($errors['message']) ?></div>
        <?php endif; ?>
      </div>

      <p style="font-size:12px;color:#94a3b8;margin:0 0 16px;line-height:1.6">
        Ao enviar esta mensagem, você concorda com nossa
        <a href="privacidade.php" style="color:var(--pacific)">Política de Privacidade</a>.
        Seus dados serão usados exclusivamente para responder ao seu contato.
      </p>

      <button type="submit" class="btn-submit">
        <i class="fa-solid fa-paper-plane"></i> Enviar mensagem
      </button>
    </form>
  </div>

  <!-- Sidebar -->
  <aside class="contact-sidebar">
    <div class="contact-card">
      <h3><i class="fa-solid fa-address-card"></i> Informações de contato</h3>
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-solid fa-envelope"></i></div>
        <div>
          <div class="ci-label">E-mail geral</div>
          <div class="ci-value"><a href="mailto:contato@pageup.net.br">contato@pageup.net.br</a></div>
        </div>
      </div>
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
          <div class="ci-label">Privacidade / DPO</div>
          <div class="ci-value"><a href="mailto:privacidade@pageup.net.br">privacidade@pageup.net.br</a></div>
        </div>
      </div>
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-brands fa-whatsapp"></i></div>
        <div>
          <div class="ci-label">WhatsApp</div>
          <div class="ci-value"><a href="https://wa.me/5569993882222" target="_blank" rel="noopener">(69) 9 9388-2222</a></div>
        </div>
      </div>
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-solid fa-clock"></i></div>
        <div>
          <div class="ci-label">Horário de atendimento</div>
          <div class="ci-value">Seg–Sex, 8h às 18h (BRT)</div>
        </div>
      </div>
    </div>

    <div class="contact-card">
      <h3><i class="fa-solid fa-scale-balanced"></i> Documentos legais</h3>
      <div class="legal-links">
        <a href="lgpd.php" class="legal-link">
          <i class="fa-solid fa-shield-halved"></i> LGPD — Proteção de Dados
        </a>
        <a href="privacidade.php" class="legal-link">
          <i class="fa-solid fa-lock"></i> Política de Privacidade
        </a>
        <a href="cookies.php" class="legal-link">
          <i class="fa-solid fa-cookie-bite"></i> Política de Cookies
        </a>
      </div>
    </div>

    <div class="contact-card">
      <h3><i class="fa-solid fa-circle-info"></i> Tempo de resposta</h3>
      <p style="font-size:13px;color:#475569;margin:0;line-height:1.7">
        Respondemos todas as mensagens em até <strong>2 dias úteis</strong>.
        Para solicitações relacionadas à LGPD (acesso, exclusão, portabilidade de dados), o prazo é de até <strong>15 dias úteis</strong>.
      </p>
    </div>
  </aside>
</div>

<footer class="lp-footer" role="contentinfo">
  <div class="footer-inner">
    <div class="footer-brand">
      <img src="assets/logo-white.svg" alt="PageQuiz" height="34"/>
      <p>Plataforma profissional de treinamento e avaliação corporativa. Simples, eficiente e com certificação automática.</p>
    </div>
    <div class="footer-col">
      <h4>Plataforma</h4>
      <a href="index.php#quizzes">Quizzes disponíveis</a>
      <a href="index.php#features">Recursos</a>
      <a href="verify.php">Verificar certificado</a>
      <a href="user/dashboard.php">Meu painel</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <a href="lgpd.php">LGPD</a>
      <a href="privacidade.php">Política de Privacidade</a>
      <a href="cookies.php">Política de Cookies</a>
      <a href="contato.php">Fale Conosco</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> PageQuiz · PageUp Sistemas</p>
    <p><a href="verify.php" style="color:rgba(255,255,255,.4);text-decoration:none;font-size:12px"><i class="fa-solid fa-shield-halved"></i> Verificar certificado</a></p>
  </div>
</footer>
</body>
</html>
