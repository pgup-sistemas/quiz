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
<title>Política de Privacidade · PageQuiz</title>
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
  <div class="page-hero-badge"><i class="fa-solid fa-lock"></i> Privacidade</div>
  <h1>Política de Privacidade</h1>
  <p>Entenda como coletamos, utilizamos e protegemos suas informações pessoais no PageQuiz</p>
</div>

<div class="page-content">
  <nav class="page-breadcrumb" aria-label="Caminho de navegação">
    <a href="index.php"><i class="fa-solid fa-house"></i> Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>Política de Privacidade</span>
  </nav>

  <div class="doc-updated">
    <i class="fa-solid fa-calendar-check"></i>
    Última atualização: <?= $updated ?>
  </div>

  <div class="doc-nav">
    <h3>Sumário</h3>
    <ol>
      <li><a href="#s1">Quem somos</a></li>
      <li><a href="#s2">Dados que coletamos</a></li>
      <li><a href="#s3">Como usamos seus dados</a></li>
      <li><a href="#s4">Compartilhamento de informações</a></li>
      <li><a href="#s5">Segurança</a></li>
      <li><a href="#s6">Seus direitos</a></li>
      <li><a href="#s7">Crianças e adolescentes</a></li>
      <li><a href="#s8">Links externos</a></li>
      <li><a href="#s9">Alterações nesta política</a></li>
      <li><a href="#s10">Contato</a></li>
    </ol>
  </div>

  <div class="doc-section" id="s1">
    <h2><span class="sec-num">1</span> Quem somos</h2>
    <p>O <strong>PageQuiz</strong> é uma plataforma de treinamento e avaliação corporativa desenvolvida e operada pela <strong>PageUp Sistemas</strong>, com sede em Rondônia, Brasil.</p>
    <p>Esta Política de Privacidade descreve como tratamos seus dados pessoais quando você utiliza nossa plataforma, em conformidade com a Lei Geral de Proteção de Dados (LGPD — Lei nº 13.709/2018).</p>
    <div class="info-box">
      <p><strong>Controlador:</strong> PageUp Sistemas &nbsp;·&nbsp; <strong>DPO:</strong> <a href="mailto:privacidade@pageup.net.br" style="color:var(--pacific)">privacidade@pageup.net.br</a></p>
    </div>
  </div>

  <div class="doc-section" id="s2">
    <h2><span class="sec-num">2</span> Dados que coletamos</h2>
    <p><strong>Dados fornecidos por você:</strong></p>
    <ul>
      <li>Nome completo e endereço de e-mail (ao criar uma conta);</li>
      <li>Setor profissional (opcional, para segmentação de treinamentos);</li>
      <li>Respostas aos quizzes e resultados de avaliações.</li>
    </ul>
    <p><strong>Dados coletados automaticamente:</strong></p>
    <ul>
      <li>Endereço IP e informações do dispositivo e navegador;</li>
      <li>Data e hora de acesso, páginas visitadas e ações realizadas;</li>
      <li>Cookies essenciais para funcionamento da sessão (veja nossa <a href="cookies.php" style="color:var(--pacific)">Política de Cookies</a>).</li>
    </ul>
  </div>

  <div class="doc-section" id="s3">
    <h2><span class="sec-num">3</span> Como usamos seus dados</h2>
    <p>Utilizamos seus dados para:</p>
    <ul>
      <li>Criar e manter sua conta na plataforma;</li>
      <li>Processar sua participação nos quizzes e registrar seus resultados;</li>
      <li>Emitir certificados de aprovação verificáveis;</li>
      <li>Enviar notificações relacionadas à plataforma (resultado, certificado, senha);</li>
      <li>Analisar o desempenho agregado para melhoria dos treinamentos;</li>
      <li>Garantir a segurança e prevenir uso indevido da plataforma;</li>
      <li>Cumprir obrigações legais e regulatórias.</li>
    </ul>
  </div>

  <div class="doc-section" id="s4">
    <h2><span class="sec-num">4</span> Compartilhamento de informações</h2>
    <p>Seus dados pessoais podem ser compartilhados somente nas seguintes situações:</p>
    <ul>
      <li><strong>Gestores e administradores da plataforma:</strong> responsáveis pelo treinamento corporativo têm acesso aos resultados individuais dos participantes;</li>
      <li><strong>Prestadores de serviço:</strong> empresas que nos auxiliam na operação (hospedagem, envio de e-mails), sempre sob contrato e obrigações de confidencialidade;</li>
      <li><strong>Exigências legais:</strong> quando determinado por autoridade competente ou para cumprimento de obrigação legal.</li>
    </ul>
    <p><strong>Não vendemos, alugamos nem comercializamos seus dados pessoais.</strong></p>
  </div>

  <div class="doc-section" id="s5">
    <h2><span class="sec-num">5</span> Segurança</h2>
    <p>Adotamos medidas técnicas e administrativas para proteger seus dados, incluindo:</p>
    <ul>
      <li>Senhas armazenadas com hash criptográfico (bcrypt);</li>
      <li>Comunicações protegidas por HTTPS com TLS;</li>
      <li>Acesso restrito por perfil e autenticação de sessão;</li>
      <li>Backups regulares com verificação de integridade.</li>
    </ul>
    <p>Embora nos esforcemos para proteger seus dados, nenhum sistema é 100% seguro. Em caso de incidente de segurança que possa afetar seus dados, notificaremos você e a ANPD dentro dos prazos legais.</p>
  </div>

  <div class="doc-section" id="s6">
    <h2><span class="sec-num">6</span> Seus direitos</h2>
    <p>Como titular de dados, você tem direito a:</p>
    <ul>
      <li>Confirmar se tratamos seus dados e acessá-los;</li>
      <li>Corrigir dados incorretos ou desatualizados;</li>
      <li>Solicitar a anonimização, bloqueio ou eliminação de dados desnecessários;</li>
      <li>Revogar o consentimento a qualquer momento (sem afetar tratamentos anteriores);</li>
      <li>Solicitar a portabilidade dos seus dados.</li>
    </ul>
    <p>Para exercer seus direitos, acesse o <a href="contato.php" style="color:var(--pacific)">formulário de contato</a> ou envie e-mail para <a href="mailto:privacidade@pageup.net.br" style="color:var(--pacific)">privacidade@pageup.net.br</a>. Consulte também nossa página sobre <a href="lgpd.php" style="color:var(--pacific)">LGPD</a>.</p>
  </div>

  <div class="doc-section" id="s7">
    <h2><span class="sec-num">7</span> Crianças e adolescentes</h2>
    <p>O PageQuiz é destinado a pessoas com 18 anos ou mais, ou a menores de 18 anos mediante autorização expressa dos pais ou responsáveis legais, conforme o Art. 14 da LGPD. Não coletamos intencionalmente dados de crianças sem o devido consentimento.</p>
  </div>

  <div class="doc-section" id="s8">
    <h2><span class="sec-num">8</span> Links externos</h2>
    <p>Nossa plataforma pode conter links para sites de terceiros. Esta Política de Privacidade aplica-se exclusivamente ao PageQuiz. Não nos responsabilizamos pelas práticas de privacidade de outros sites.</p>
  </div>

  <div class="doc-section" id="s9">
    <h2><span class="sec-num">9</span> Alterações nesta política</h2>
    <p>Podemos atualizar esta política periodicamente. Quando houver alterações relevantes, notificaremos você por e-mail ou mediante aviso na plataforma. A data da última atualização é sempre indicada no topo do documento.</p>
  </div>

  <div class="doc-section" id="s10">
    <h2><span class="sec-num">10</span> Contato</h2>
    <p>Dúvidas, solicitações ou reclamações sobre privacidade:</p>
    <ul>
      <li><strong>E-mail:</strong> <a href="mailto:privacidade@pageup.net.br" style="color:var(--pacific)">privacidade@pageup.net.br</a></li>
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
