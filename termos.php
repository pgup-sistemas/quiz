<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user-auth.php';
userSessionStart();
$currentUser = currentUser();
$updated = '22/07/2026';
$proPrice = (int)(dbRow("SELECT value FROM system_settings WHERE `key`='pro_price_monthly'")['value'] ?? 4990);
$proPriceStr = 'R$ ' . number_format($proPrice / 100, 2, ',', '.');
$freeLimit = (int)(dbRow("SELECT value FROM system_settings WHERE `key`='free_quiz_limit'")['value'] ?? 12);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<title>Termos de Uso · PageQuiz</title>
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
  <div class="page-hero-badge"><i class="fa-solid fa-file-contract"></i> Termos</div>
  <h1>Termos de Uso</h1>
  <p>Condições que regem o uso da plataforma PageQuiz, incluindo planos, cobrança e responsabilidades</p>
</div>

<div class="page-content">
  <nav class="page-breadcrumb" aria-label="Caminho de navegação">
    <a href="index.php"><i class="fa-solid fa-house"></i> Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Termos de Uso</span>
  </nav>

  <div class="doc-updated">
    <i class="fa-solid fa-calendar-check"></i>
    Última atualização: <?= $updated ?>
  </div>

  <div class="doc-nav">
    <h3>Sumário</h3>
    <ol>
      <li><a href="#s1">Aceitação dos termos</a></li>
      <li><a href="#s2">Descrição do serviço</a></li>
      <li><a href="#s3">Cadastro e conta</a></li>
      <li><a href="#s4">Planos, preços e pagamento</a></li>
      <li><a href="#s5">Cancelamento e reembolso</a></li>
      <li><a href="#s6">Uso aceitável</a></li>
      <li><a href="#s7">Propriedade intelectual e conteúdo</a></li>
      <li><a href="#s8">Disponibilidade e limitação de responsabilidade</a></li>
      <li><a href="#s9">Suspensão e encerramento de conta</a></li>
      <li><a href="#s10">Alterações nestes termos</a></li>
      <li><a href="#s11">Lei aplicável e foro</a></li>
      <li><a href="#s12">Contato</a></li>
    </ol>
  </div>

  <div class="doc-section" id="s1">
    <h2><span class="sec-num">1</span> Aceitação dos termos</h2>
    <p>Estes Termos de Uso ("Termos") regem o acesso e uso da plataforma <strong>PageQuiz</strong>, operada pela <strong>PageUp Sistemas</strong>. Ao criar uma conta, contratar um plano ou utilizar a plataforma de qualquer forma, você declara que leu, compreendeu e concorda integralmente com estes Termos.</p>
    <p>Se você está aceitando estes Termos em nome de uma empresa, declara ter poderes para vinculá-la a este contrato.</p>
  </div>

  <div class="doc-section" id="s2">
    <h2><span class="sec-num">2</span> Descrição do serviço</h2>
    <p>O PageQuiz é uma plataforma SaaS (Software as a Service) de treinamento e avaliação corporativa via quizzes, com emissão de certificados verificáveis. Cada empresa contratante ("Empresa", "Cliente") possui um espaço isolado de quizzes, usuários e resultados.</p>
    <p>A plataforma é fornecida "como está" e pode ser atualizada, modificada ou ter funcionalidades adicionadas/removidas a qualquer momento, visando sua melhoria contínua.</p>
  </div>

  <div class="doc-section" id="s3">
    <h2><span class="sec-num">3</span> Cadastro e conta</h2>
    <p>Para usar o PageQuiz é necessário criar uma conta, fornecendo informações verdadeiras, completas e atualizadas. Você é responsável por:</p>
    <ul>
      <li>Manter a confidencialidade da sua senha de acesso;</li>
      <li>Todas as atividades realizadas na sua conta;</li>
      <li>Notificar-nos imediatamente sobre qualquer uso não autorizado.</li>
    </ul>
    <p>Contas de gestores/administradores de empresa podem convidar e gerenciar colaboradores dentro do espaço da própria Empresa. Colaboradores acessam via cadastro próprio, convite ou credenciais fornecidas pelo gestor.</p>
  </div>

  <div class="doc-section" id="s4">
    <h2><span class="sec-num">4</span> Planos, preços e pagamento</h2>
    <p>O PageQuiz oferece os seguintes planos:</p>
    <ul>
      <li><strong>Free:</strong> gratuito, com limite de até <?= $freeLimit ?> quizzes ativos simultâneos, usuários ilimitados e certificado padrão da plataforma.</li>
      <li><strong>Pro:</strong> <?= htmlspecialchars($proPriceStr) ?>/mês (valor sujeito a alteração mediante aviso prévio), com quizzes ilimitados, certificado personalizado (logo e cores da Empresa) e demais recursos avançados.</li>
    </ul>
    <p>Os pagamentos do plano Pro são processados por meio da <strong>EFI Bank</strong>, instituição de pagamentos parceira, via PIX, cartão de crédito (cobrança única ou assinatura recorrente) ou link de pagamento. O PageQuiz não armazena dados completos de cartão de crédito — a tokenização é feita diretamente pelo provedor de pagamentos.</p>
    <div class="info-box">
      <p><strong>Ativação manual:</strong> em determinadas situações (negociações específicas, cortesias, ou período de transição), a ativação do plano Pro pode ser feita manualmente pela equipe PageUp mediante combinação prévia com o Cliente.</p>
    </div>
  </div>

  <div class="doc-section" id="s5">
    <h2><span class="sec-num">5</span> Cancelamento e reembolso</h2>
    <p>Assinaturas recorrentes podem ser canceladas a qualquer momento pelo próprio painel administrativo, sem multa. Pagamentos avulsos (PIX, cartão de cobrança única ou link) concedem acesso ao plano Pro por 30 dias corridos, sem renovação automática.</p>
    <p>As regras completas de cancelamento, reembolso e direito de arrependimento estão detalhadas na nossa <a href="cancelamento.php" style="color:var(--pacific);font-weight:600">Política de Cancelamento e Reembolso</a>, que é parte integrante destes Termos.</p>
  </div>

  <div class="doc-section" id="s6">
    <h2><span class="sec-num">6</span> Uso aceitável</h2>
    <p>Ao usar o PageQuiz, você concorda em não:</p>
    <ul>
      <li>Utilizar a plataforma para fins ilegais, fraudulentos ou não autorizados;</li>
      <li>Tentar acessar dados de outras Empresas ou contornar o isolamento multi-tenant da plataforma;</li>
      <li>Realizar engenharia reversa, copiar ou explorar comercialmente o código-fonte ou a estrutura da plataforma sem autorização;</li>
      <li>Sobrecarregar, atacar ou comprometer a infraestrutura, segurança ou disponibilidade do serviço;</li>
      <li>Inserir conteúdo ofensivo, discriminatório, difamatório ou que viole direitos de terceiros nos quizzes ou mensagens da plataforma;</li>
      <li>Compartilhar credenciais de acesso com pessoas não autorizadas.</li>
    </ul>
    <p>O descumprimento destas regras pode resultar em suspensão ou encerramento da conta, conforme a Seção 9.</p>
  </div>

  <div class="doc-section" id="s7">
    <h2><span class="sec-num">7</span> Propriedade intelectual e conteúdo</h2>
    <p>A marca PageQuiz, o software, o design e todo o código-fonte da plataforma são de propriedade exclusiva da PageUp Sistemas, protegidos por leis de propriedade intelectual.</p>
    <p>O conteúdo inserido pela Empresa (quizzes, questões, respostas, dados de colaboradores, logo e cores personalizadas) permanece de propriedade da Empresa. Ao inserir esse conteúdo, você concede à PageUp Sistemas uma licença limitada, não exclusiva, para armazená-lo e processá-lo exclusivamente com a finalidade de fornecer o serviço contratado.</p>
  </div>

  <div class="doc-section" id="s8">
    <h2><span class="sec-num">8</span> Disponibilidade e limitação de responsabilidade</h2>
    <p>Empregamos esforços razoáveis para manter a plataforma disponível e funcionando corretamente, mas não garantimos disponibilidade ininterrupta. Podem ocorrer interrupções para manutenção, atualizações ou por motivos fora do nosso controle (falhas de provedores de infraestrutura, internet, força maior).</p>
    <p>Na máxima extensão permitida pela lei, a PageUp Sistemas não se responsabiliza por danos indiretos, lucros cessantes ou perda de dados decorrentes do uso ou da impossibilidade de uso da plataforma, exceto nos casos de dolo ou culpa grave comprovados.</p>
  </div>

  <div class="doc-section" id="s9">
    <h2><span class="sec-num">9</span> Suspensão e encerramento de conta</h2>
    <p>Podemos suspender ou encerrar o acesso de uma Empresa em caso de:</p>
    <ul>
      <li>Inadimplência não regularizada após o período de carência informado na cobrança;</li>
      <li>Violação destes Termos ou uso indevido da plataforma;</li>
      <li>Solicitação da própria Empresa.</li>
    </ul>
    <p>A exclusão definitiva de uma conta e seus dados (quizzes, participantes, certificados, usuários) é uma ação irreversível, disponível mediante solicitação. Após a exclusão, os dados não podem ser recuperados.</p>
  </div>

  <div class="doc-section" id="s10">
    <h2><span class="sec-num">10</span> Alterações nestes termos</h2>
    <p>Podemos atualizar estes Termos periodicamente para refletir mudanças na plataforma, na legislação ou nas práticas de mercado. Alterações relevantes serão comunicadas por e-mail ou aviso na plataforma, com antecedência razoável quando aplicável. O uso continuado do PageQuiz após a alteração constitui aceite dos novos Termos.</p>
  </div>

  <div class="doc-section" id="s11">
    <h2><span class="sec-num">11</span> Lei aplicável e foro</h2>
    <p>Estes Termos são regidos pelas leis da República Federativa do Brasil. Fica eleito o foro da comarca de domicílio da PageUp Sistemas, em Rondônia, para dirimir quaisquer controvérsias decorrentes destes Termos, com renúncia expressa a qualquer outro, por mais privilegiado que seja, ressalvadas as disposições de foro do Código de Defesa do Consumidor quando aplicáveis.</p>
  </div>

  <div class="doc-section" id="s12">
    <h2><span class="sec-num">12</span> Contato</h2>
    <p>Dúvidas sobre estes Termos de Uso podem ser enviadas para:</p>
    <ul>
      <li><strong>E-mail:</strong> <a href="mailto:contato@pageup.net.br" style="color:var(--pacific)">contato@pageup.net.br</a></li>
      <li><strong>Formulário:</strong> <a href="contato.php" style="color:var(--pacific)">Fale Conosco</a></li>
    </ul>
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
