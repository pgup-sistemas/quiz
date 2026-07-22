<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user-auth.php';
userSessionStart();
$currentUser = currentUser();
$updated = '09/07/2025';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<title>Política de Cookies · PageQuiz</title>
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
.page-content{max-width:820px;margin:0 auto;padding:56px 32px 80px}
.page-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:#94a3b8;margin-bottom:40px}
.page-breadcrumb a{color:#64748b;text-decoration:none;transition:.15s}
.page-breadcrumb a:hover{color:var(--pacific)}
.page-breadcrumb i{font-size:10px}
.doc-section{margin-bottom:40px}
.doc-section h2{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--prussian);margin:0 0 12px;display:flex;align-items:center;gap:10px}
.doc-section h2 .sec-num{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;background:var(--pacific);color:#fff;border-radius:8px;font-size:13px;font-weight:800;flex-shrink:0}
.doc-section p{font-size:15px;color:#475569;line-height:1.8;margin:0 0 12px}
.doc-section ul{margin:0 0 12px;padding-left:20px}
.doc-section ul li{font-size:15px;color:#475569;line-height:1.8;margin-bottom:4px}
.doc-section strong{color:#1e293b}
.doc-updated{font-size:12px;color:#94a3b8;background:#f8fafc;border:1px solid #e2ecf1;border-radius:8px;padding:10px 16px;display:inline-flex;align-items:center;gap:8px;margin-bottom:32px}

/* Tabela de cookies */
.cookie-table-wrap{overflow-x:auto;margin:20px 0;border-radius:12px;border:1px solid #e2ecf1}
table.cookie-table{width:100%;border-collapse:collapse;font-size:14px}
table.cookie-table th{background:#f0f7fa;color:var(--prussian);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:12px 16px;text-align:left;border-bottom:1px solid #dce8ef}
table.cookie-table td{padding:12px 16px;color:#475569;border-bottom:1px solid #f0f4f7;vertical-align:top;line-height:1.6}
table.cookie-table tr:last-child td{border-bottom:none}
table.cookie-table tr:hover td{background:#fafcfe}

.cookie-type-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.badge-essential{background:#dcfce7;color:#166534}
.badge-functional{background:#dbeafe;color:#1e40af}
.badge-analytics{background:#fef3c7;color:#92400e}

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
  .page-content{padding:40px 20px 60px}
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
  <div class="page-hero-badge"><i class="fa-solid fa-cookie-bite"></i> Transparência</div>
  <h1>Política de Cookies</h1>
  <p>Saiba quais cookies utilizamos, por que e como você pode controlá-los</p>
</div>

<div class="page-content">
  <nav class="page-breadcrumb" aria-label="Caminho de navegação">
    <a href="index.php"><i class="fa-solid fa-house"></i> Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Política de Cookies</span>
  </nav>

  <div class="doc-updated">
    <i class="fa-solid fa-calendar-check"></i>
    Última atualização: <?= $updated ?>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">1</span> O que são cookies?</h2>
    <p>Cookies são pequenos arquivos de texto armazenados no seu navegador quando você visita um site. Eles permitem que o site reconheça seu navegador em visitas futuras, mantenha sua sessão ativa e lembre suas preferências.</p>
    <p>No PageQuiz, utilizamos cookies de forma mínima e responsável, apenas para garantir o funcionamento correto da plataforma e para fins analíticos agregados que nos ajudam a melhorar sua experiência.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">2</span> Cookies que utilizamos</h2>
    <div class="cookie-table-wrap">
      <table class="cookie-table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Duração</th>
            <th>Finalidade</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>PHPSESSID</strong></td>
            <td><span class="cookie-type-badge badge-essential">Essencial</span></td>
            <td>Sessão</td>
            <td>Mantém a sessão autenticada do usuário enquanto o navegador está aberto. Necessário para login e realização de quizzes.</td>
          </tr>
          <tr>
            <td><strong>pageup_user_sess</strong></td>
            <td><span class="cookie-type-badge badge-essential">Essencial</span></td>
            <td>30 dias</td>
            <td>Mantém o usuário autenticado entre sessões ("lembrar de mim"). Invalidado no logout.</td>
          </tr>
          <tr>
            <td><strong>pageup_admin</strong></td>
            <td><span class="cookie-type-badge badge-essential">Essencial</span></td>
            <td>Sessão</td>
            <td>Controla a sessão administrativa. Presente apenas para usuários administradores.</td>
          </tr>
          <tr>
            <td><strong>cookie_consent</strong></td>
            <td><span class="cookie-type-badge badge-functional">Funcional</span></td>
            <td>1 ano</td>
            <td>Armazena sua preferência de consentimento para evitar que o aviso de cookies apareça repetidamente.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">3</span> Cookies de terceiros</h2>
    <p>O PageQuiz pode carregar recursos de terceiros que definem seus próprios cookies:</p>
    <ul>
      <li><strong>Google Fonts:</strong> utilizado para carregar as fontes DM Sans e Syne. O Google pode definir cookies de rastreamento. Você pode desativá-los nas configurações do seu navegador.</li>
      <li><strong>Font Awesome (cdnjs):</strong> utilizado para ícones. A Cloudflare/Font Awesome pode registrar acessos ao CDN.</li>
    </ul>
    <p>Não utilizamos cookies de redes sociais, publicidade comportamental ou plataformas de analytics externas como Google Analytics.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">4</span> Cookies essenciais</h2>
    <p>Os cookies classificados como <strong>Essenciais</strong> são estritamente necessários para o funcionamento da plataforma e não podem ser desativados sem comprometer a usabilidade. Eles não armazenam informações pessoais identificáveis além do identificador de sessão.</p>
    <p>Com base no Art. 7º, V da LGPD (execução de contrato), esses cookies não requerem consentimento explícito, pois são indispensáveis para a prestação do serviço solicitado.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">5</span> Como gerenciar cookies</h2>
    <p>Você pode controlar e excluir cookies diretamente pelas configurações do seu navegador:</p>
    <ul>
      <li><strong>Google Chrome:</strong> Configurações → Privacidade e segurança → Cookies e outros dados do site</li>
      <li><strong>Mozilla Firefox:</strong> Configurações → Privacidade e Segurança → Cookies e dados do site</li>
      <li><strong>Microsoft Edge:</strong> Configurações → Cookies e permissões do site</li>
      <li><strong>Safari:</strong> Preferências → Privacidade → Gerenciar dados do site</li>
    </ul>
    <p>Atenção: desativar os cookies essenciais impedirá o funcionamento correto da plataforma, incluindo login e realização de quizzes.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">6</span> Atualizações desta política</h2>
    <p>Esta Política de Cookies pode ser atualizada periodicamente. A data da última revisão é sempre indicada no topo do documento. Alterações significativas serão comunicadas na plataforma.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">7</span> Contato</h2>
    <p>Dúvidas sobre o uso de cookies?</p>
    <ul>
      <li><strong>E-mail:</strong> <a href="mailto:privacidade@pageup.net.br" style="color:var(--pacific)">privacidade@pageup.net.br</a></li>
      <li><strong>Formulário:</strong> <a href="contato.php" style="color:var(--pacific)">Fale Conosco</a></li>
    </ul>
    <p>Consulte também nossa <a href="privacidade.php" style="color:var(--pacific)">Política de Privacidade</a> e nossa página sobre <a href="lgpd.php" style="color:var(--pacific)">LGPD</a>.</p>
  </div>
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
<?php include __DIR__ . '/includes/cookie-banner.php'; ?>
</body>
</html>
