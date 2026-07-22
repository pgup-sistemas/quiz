<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user-auth.php';
userSessionStart();
$currentUser = currentUser();
$updated = '22/07/2026';
$proPrice = (int)(dbRow("SELECT value FROM system_settings WHERE `key`='pro_price_monthly'")['value'] ?? 4990);
$proPriceStr = 'R$ ' . number_format($proPrice / 100, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<title>Cancelamento e Reembolso · PageQuiz</title>
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
.info-box{background:#f0f7fa;border-left:4px solid var(--pacific);border-radius:0 12px 12px 0;padding:16px 20px;margin:20px 0}
.info-box p{margin:0;font-size:14px;color:#334155;line-height:1.7}
.warn-box{background:#fffbeb;border-left:4px solid var(--yellow);border-radius:0 12px 12px 0;padding:16px 20px;margin:20px 0}
.warn-box p{margin:0;font-size:14px;color:#78350f;line-height:1.7}
.doc-updated{font-size:12px;color:#94a3b8;background:#f8fafc;border:1px solid #e2ecf1;border-radius:8px;padding:10px 16px;display:inline-flex;align-items:center;gap:8px;margin-bottom:32px}
.doc-nav{background:#f8fafc;border:1px solid #e2ecf1;border-radius:14px;padding:20px 24px;margin-bottom:40px}
.doc-nav h3{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin:0 0 12px}
.doc-nav ol{margin:0;padding-left:18px}
.doc-nav ol li{margin-bottom:6px}
.doc-nav ol li a{font-size:14px;color:var(--pacific);text-decoration:none;transition:.15s}
.doc-nav ol li a:hover{color:var(--prussian)}
.cookie-table-wrap{overflow-x:auto;margin:20px 0;border-radius:12px;border:1px solid #e2ecf1}
table.cookie-table{width:100%;border-collapse:collapse;font-size:14px}
table.cookie-table th{background:#f0f7fa;color:var(--prussian);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:12px 16px;text-align:left;border-bottom:1px solid #dce8ef}
table.cookie-table td{padding:12px 16px;color:#475569;border-bottom:1px solid #f0f4f7;vertical-align:top;line-height:1.6}
table.cookie-table tr:last-child td{border-bottom:none}
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
  <div class="page-hero-badge"><i class="fa-solid fa-rotate-left"></i> Assinaturas</div>
  <h1>Cancelamento e Reembolso</h1>
  <p>Regras claras sobre como cancelar sua assinatura e quando você tem direito a reembolso</p>
</div>

<div class="page-content">
  <nav class="page-breadcrumb" aria-label="Caminho de navegação">
    <a href="index.php"><i class="fa-solid fa-house"></i> Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Cancelamento e Reembolso</span>
  </nav>

  <div class="doc-updated">
    <i class="fa-solid fa-calendar-check"></i>
    Última atualização: <?= $updated ?>
  </div>

  <div class="doc-nav">
    <h3>Sumário</h3>
    <ol>
      <li><a href="#s1">Como cancelar</a></li>
      <li><a href="#s2">O que acontece após o cancelamento</a></li>
      <li><a href="#s3">Pagamentos avulsos (PIX, cartão único, link)</a></li>
      <li><a href="#s4">Assinatura recorrente (cartão)</a></li>
      <li><a href="#s5">Direito de arrependimento (7 dias)</a></li>
      <li><a href="#s6">Reembolso após o uso</a></li>
      <li><a href="#s7">Situações que não geram reembolso</a></li>
      <li><a href="#s8">Como solicitar</a></li>
    </ol>
  </div>

  <div class="doc-section" id="s1">
    <h2><span class="sec-num">1</span> Como cancelar</h2>
    <p>Você pode cancelar sua assinatura recorrente a qualquer momento, diretamente pelo painel administrativo, em <strong>Cobrança → Cancelar assinatura</strong>. Não é necessário justificativa, ligação ou envio de documentos — o cancelamento é imediato na sua solicitação.</p>
    <p>Se preferir, também pode solicitar o cancelamento pelo <a href="contato.php" style="color:var(--pacific)">formulário de contato</a> ou e-mail <a href="mailto:contato@pageup.net.br" style="color:var(--pacific)">contato@pageup.net.br</a>.</p>
  </div>

  <div class="doc-section" id="s2">
    <h2><span class="sec-num">2</span> O que acontece após o cancelamento</h2>
    <p>Ao cancelar uma <strong>assinatura recorrente</strong>, nenhuma cobrança futura é realizada, mas o plano Pro permanece ativo até o fim do período já pago. Após essa data, a Empresa é automaticamente rebaixada para o plano Free, e quizzes ativos que excederem o limite do plano Free são desativados (não excluídos).</p>
    <p>Seus dados (quizzes, participantes, certificados emitidos) não são apagados por causa do cancelamento — permanecem acessíveis no plano Free, respeitando os limites desse plano.</p>
  </div>

  <div class="doc-section" id="s3">
    <h2><span class="sec-num">3</span> Pagamentos avulsos (PIX, cartão único, link de pagamento)</h2>
    <p>Pagamentos que não são assinatura recorrente concedem acesso ao plano Pro por <strong>30 dias corridos</strong> a partir da confirmação do pagamento, sem renovação automática. Ao final desse período, a Empresa retorna automaticamente ao plano Free, a menos que um novo pagamento seja realizado.</p>
    <p>Não há cobrança recorrente nesses métodos — cada pagamento é único e não gera novas cobranças sem uma nova ação sua.</p>
  </div>

  <div class="doc-section" id="s4">
    <h2><span class="sec-num">4</span> Assinatura recorrente (cartão)</h2>
    <p>Na assinatura recorrente, o valor de <?= htmlspecialchars($proPriceStr) ?>/mês é cobrado automaticamente no cartão cadastrado, a cada ciclo de 30 dias, até que você cancele. Caso uma cobrança falhe (cartão vencido, saldo insuficiente etc.), você tem um <strong>período de carência de 7 dias</strong> para regularizar o pagamento antes que o plano seja rebaixado para Free.</p>
  </div>

  <div class="doc-section" id="s5">
    <h2><span class="sec-num">5</span> Direito de arrependimento (7 dias)</h2>
    <div class="info-box">
      <p>Nos termos do <strong>Art. 49 do Código de Defesa do Consumidor</strong>, por se tratar de contratação realizada fora do estabelecimento comercial (via internet), você tem o direito de desistir da contratação do plano Pro em até <strong>7 (sete) dias corridos</strong> a partir da data do pagamento, com direito a reembolso integral.</p>
    </div>
    <p>Para exercer esse direito, entre em contato dentro do prazo pelos canais informados na Seção 8. O reembolso será processado pelo mesmo meio de pagamento utilizado, em até 10 dias úteis após a confirmação, conforme prazos do meio de pagamento (PIX ou operadora de cartão via EFI Bank).</p>
  </div>

  <div class="doc-section" id="s6">
    <h2><span class="sec-num">6</span> Reembolso após o uso</h2>
    <p>Após o prazo de arrependimento de 7 dias, por se tratar de serviço digital de acesso imediato e uso contínuo, <strong>não há reembolso proporcional</strong> pelo tempo restante do período já pago em caso de cancelamento voluntário. Você mantém acesso ao plano Pro até o fim do ciclo vigente, conforme a Seção 2.</p>
    <p>Exceções podem ser avaliadas caso a caso em situações de erro comprovado de cobrança (ex.: cobrança duplicada, valor incorreto) ou indisponibilidade prolongada e não programada da plataforma atribuível à PageUp Sistemas.</p>
  </div>

  <div class="doc-section" id="s7">
    <h2><span class="sec-num">7</span> Situações que não geram reembolso</h2>
    <ul>
      <li>Cancelamento por decisão própria após o prazo de arrependimento de 7 dias;</li>
      <li>Suspensão da conta por violação dos <a href="termos.php" style="color:var(--pacific)">Termos de Uso</a>;</li>
      <li>Não utilização voluntária da plataforma durante o período pago;</li>
      <li>Ativações manuais/cortesias combinadas sem valor efetivamente cobrado.</li>
    </ul>
    <div class="warn-box">
      <p><i class="fa-solid fa-triangle-exclamation"></i> Contestações de cobrança (chargeback) sem tentativa prévia de contato conosco podem resultar em suspensão imediata da conta até a regularização, sem prejuízo de outras medidas cabíveis.</p>
    </div>
  </div>

  <div class="doc-section" id="s8">
    <h2><span class="sec-num">8</span> Como solicitar</h2>
    <p>Para cancelar, solicitar reembolso ou tirar dúvidas sobre cobrança:</p>
    <ul>
      <li><strong>Painel:</strong> Admin → Cobrança → Cancelar assinatura</li>
      <li><strong>E-mail:</strong> <a href="mailto:contato@pageup.net.br" style="color:var(--pacific)">contato@pageup.net.br</a></li>
      <li><strong>Formulário:</strong> <a href="contato.php" style="color:var(--pacific)">Fale Conosco</a></li>
    </ul>
    <p>Esta política complementa os nossos <a href="termos.php" style="color:var(--pacific)">Termos de Uso</a> e é parte integrante do contrato entre você e a PageUp Sistemas.</p>
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
      <a href="termos.php">Termos de Uso</a>
      <a href="cancelamento.php">Cancelamento e Reembolso</a>
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
