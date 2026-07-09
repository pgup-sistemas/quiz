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
<title>LGPD · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'DM Sans',sans-serif;color:#1e293b;background:#fff}

/* Navbar */
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

/* Page hero */
.page-hero{background:linear-gradient(135deg,#023047 0%,#03506f 60%,#023047 100%);padding:56px 32px 64px;text-align:center}
.page-hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,183,3,.15);border:1px solid rgba(255,183,3,.3);border-radius:20px;padding:6px 16px;font-size:11px;font-weight:700;color:#FFB703;text-transform:uppercase;letter-spacing:1px;margin-bottom:20px}
.page-hero h1{font-family:'Syne',sans-serif;font-size:clamp(26px,4vw,42px);font-weight:800;color:#fff;margin:0 0 12px}
.page-hero p{font-size:15px;color:rgba(255,255,255,.65);margin:0}

/* Content */
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

.rights-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:20px 0}
.right-card{background:#f8fafc;border:1px solid #e2ecf1;border-radius:12px;padding:18px 16px}
.right-card .ri{font-size:20px;color:var(--pacific);margin-bottom:8px}
.right-card h3{font-size:14px;font-weight:700;color:var(--prussian);margin:0 0 6px}
.right-card p{font-size:13px;color:#64748b;margin:0;line-height:1.6}

.doc-updated{font-size:12px;color:#94a3b8;background:#f8fafc;border:1px solid #e2ecf1;border-radius:8px;padding:10px 16px;display:inline-flex;align-items:center;gap:8px;margin-bottom:32px}

/* Footer */
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
  .rights-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:480px){.rights-grid{grid-template-columns:1fr}}
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
  <div class="page-hero-badge"><i class="fa-solid fa-shield-halved"></i> Conformidade Legal</div>
  <h1>Lei Geral de Proteção de Dados</h1>
  <p>Como o PageQuiz aplica a LGPD (Lei nº 13.709/2018) no tratamento dos seus dados pessoais</p>
</div>

<div class="page-content">
  <nav class="page-breadcrumb" aria-label="Caminho de navegação">
    <a href="index.php"><i class="fa-solid fa-house"></i> Início</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span>LGPD</span>
  </nav>

  <div class="doc-updated">
    <i class="fa-solid fa-calendar-check"></i>
    Última atualização: <?= $updated ?>
  </div>

  <div class="info-box">
    <p><strong>Controlador dos dados:</strong> PageUp Sistemas · CNPJ 00.000.000/0001-00 · Rondônia, Brasil<br>
    <strong>Encarregado (DPO):</strong> <a href="mailto:privacidade@pageup.net.br" style="color:var(--pacific)">privacidade@pageup.net.br</a></p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">1</span> O que é a LGPD?</h2>
    <p>A <strong>Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018)</strong> é a legislação brasileira que regula o tratamento de dados pessoais por pessoas físicas e jurídicas, de direito público ou privado, com o objetivo de proteger os direitos fundamentais de liberdade, privacidade e o livre desenvolvimento da personalidade.</p>
    <p>Estamos comprometidos com o cumprimento integral da LGPD e com a transparência sobre como coletamos, usamos e protegemos seus dados pessoais na plataforma PageQuiz.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">2</span> Bases legais para o tratamento</h2>
    <p>Tratamos seus dados pessoais com fundamento nas seguintes bases legais previstas no Art. 7º da LGPD:</p>
    <ul>
      <li><strong>Execução de contrato (Art. 7º, V):</strong> para viabilizar o acesso à plataforma, realização de quizzes e emissão de certificados.</li>
      <li><strong>Legítimo interesse (Art. 7º, IX):</strong> para melhoria contínua dos serviços, segurança da plataforma e prevenção de fraudes.</li>
      <li><strong>Cumprimento de obrigação legal (Art. 7º, II):</strong> quando exigido por regulamentações aplicáveis.</li>
      <li><strong>Consentimento (Art. 7º, I):</strong> para comunicações de marketing e uso de cookies não essenciais.</li>
    </ul>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">3</span> Dados pessoais coletados</h2>
    <p>Coletamos apenas os dados estritamente necessários para a prestação dos serviços:</p>
    <ul>
      <li><strong>Dados de cadastro:</strong> nome completo, e-mail e setor profissional.</li>
      <li><strong>Dados de desempenho:</strong> respostas, pontuação, tempo de resposta e resultados dos quizzes.</li>
      <li><strong>Dados de acesso:</strong> data/hora de login e utilização da plataforma (logs de sessão).</li>
      <li><strong>Dados técnicos:</strong> endereço IP, tipo de navegador e sistema operacional (para segurança e funcionamento).</li>
    </ul>
    <p>Não coletamos dados sensíveis (art. 5º, II da LGPD) como origem racial, convicção religiosa, saúde ou dados genéticos.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">4</span> Finalidades do tratamento</h2>
    <p>Seus dados são tratados para as seguintes finalidades:</p>
    <ul>
      <li>Criar e gerenciar sua conta na plataforma;</li>
      <li>Viabilizar a realização de quizzes e armazenar seus resultados;</li>
      <li>Emitir e validar certificados de conclusão;</li>
      <li>Enviar comunicações transacionais (resultado de quiz, certificado, recuperação de senha);</li>
      <li>Garantir a segurança e integridade da plataforma;</li>
      <li>Cumprir obrigações legais e regulatórias.</li>
    </ul>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">5</span> Seus direitos como titular</h2>
    <p>A LGPD garante a você, como titular dos dados, os seguintes direitos (Art. 18):</p>
    <div class="rights-grid">
      <div class="right-card">
        <div class="ri"><i class="fa-solid fa-eye"></i></div>
        <h3>Acesso</h3>
        <p>Solicitar confirmação e acesso aos seus dados que tratamos.</p>
      </div>
      <div class="right-card">
        <div class="ri"><i class="fa-solid fa-pen"></i></div>
        <h3>Correção</h3>
        <p>Corrigir dados incompletos, inexatos ou desatualizados.</p>
      </div>
      <div class="right-card">
        <div class="ri"><i class="fa-solid fa-trash"></i></div>
        <h3>Eliminação</h3>
        <p>Solicitar a exclusão dos dados tratados com base no consentimento.</p>
      </div>
      <div class="right-card">
        <div class="ri"><i class="fa-solid fa-ban"></i></div>
        <h3>Revogação</h3>
        <p>Retirar o consentimento a qualquer momento, quando aplicável.</p>
      </div>
      <div class="right-card">
        <div class="ri"><i class="fa-solid fa-file-export"></i></div>
        <h3>Portabilidade</h3>
        <p>Receber seus dados em formato estruturado e interoperável.</p>
      </div>
      <div class="right-card">
        <div class="ri"><i class="fa-solid fa-circle-info"></i></div>
        <h3>Informação</h3>
        <p>Ser informado sobre as entidades com quem compartilhamos seus dados.</p>
      </div>
    </div>
    <p>Para exercer qualquer desses direitos, entre em contato pelo e-mail <a href="mailto:privacidade@pageup.net.br" style="color:var(--pacific)">privacidade@pageup.net.br</a> ou pelo nosso <a href="contato.php" style="color:var(--pacific)">formulário de contato</a>. Responderemos em até <strong>15 dias úteis</strong>.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">6</span> Retenção e exclusão de dados</h2>
    <p>Seus dados são mantidos pelo tempo necessário para cumprir as finalidades descritas, observando os seguintes prazos:</p>
    <ul>
      <li><strong>Dados de conta ativa:</strong> enquanto a conta estiver ativa na plataforma.</li>
      <li><strong>Resultados e certificados:</strong> por até 5 anos após a conclusão, para fins de comprovação e auditoria.</li>
      <li><strong>Logs de acesso:</strong> por 6 meses, conforme exigência do Marco Civil da Internet (Lei nº 12.965/2014).</li>
    </ul>
    <p>Após o prazo de retenção, os dados são eliminados de forma segura ou anonimizados.</p>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">7</span> Compartilhamento de dados</h2>
    <p>Não vendemos, alugamos ou comercializamos seus dados pessoais. Podemos compartilhá-los apenas:</p>
    <ul>
      <li>Com a organização contratante da plataforma (empregador/gestores), para fins de treinamento corporativo;</li>
      <li>Com prestadores de serviço que nos auxiliam na operação da plataforma (hospedagem, e-mail transacional), sob acordos de confidencialidade;</li>
      <li>Com autoridades competentes, quando exigido por lei ou ordem judicial.</li>
    </ul>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">8</span> Segurança dos dados</h2>
    <p>Adotamos medidas técnicas e organizacionais adequadas para proteger seus dados contra acesso não autorizado, perda, alteração ou divulgação indevida, incluindo:</p>
    <ul>
      <li>Criptografia de senhas com hashing seguro (bcrypt);</li>
      <li>Conexões protegidas via HTTPS/TLS;</li>
      <li>Controle de acesso baseado em perfis de usuário;</li>
      <li>Backups periódicos com controle de integridade.</li>
    </ul>
  </div>

  <div class="doc-section">
    <h2><span class="sec-num">9</span> Contato e reclamações</h2>
    <p>Para dúvidas, solicitações ou reclamações relacionadas ao tratamento de dados pessoais:</p>
    <ul>
      <li><strong>E-mail do DPO:</strong> <a href="mailto:privacidade@pageup.net.br" style="color:var(--pacific)">privacidade@pageup.net.br</a></li>
      <li><strong>Formulário de contato:</strong> <a href="contato.php" style="color:var(--pacific)">contato.php</a></li>
    </ul>
    <p>Você também pode apresentar reclamação à <strong>Autoridade Nacional de Proteção de Dados (ANPD)</strong> pelo site <a href="https://www.gov.br/anpd" target="_blank" rel="noopener" style="color:var(--pacific)">www.gov.br/anpd</a>.</p>
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
