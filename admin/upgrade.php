<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../admin/layout.php';
sessionStart();
requireLogin();

$companyId = adminCompanyId();
$company   = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);

$supportEmail = dbRow("SELECT value FROM system_settings WHERE key='support_email'")['value'] ?? 'contato@pageup.net.br';
$freeLimit    = (int)(dbRow("SELECT value FROM system_settings WHERE key='free_quiz_limit'")['value'] ?? 12);

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($company['plan'] === 'pro') {
        $msg = 'Você já está no plano Pro!';
    } elseif ($company['status'] === 'pending_payment') {
        $msg = 'Sua solicitação Pro já está sendo processada. Aguarde o contato da equipe PageUp.';
    } else {
        dbExec("UPDATE companies SET status='pending_payment', updated_at=datetime('now','localtime') WHERE id=?", [$companyId]);
        $msg = 'Solicitação enviada! Entraremos em contato em breve para ativar o plano Pro.';
    }
}

// Recarregar
$company = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);
adminHead('Upgrade para Pro', 'settings.php');
?>
<div class="admin-wrap" style="max-width:760px">
    <div style="margin-bottom:24px">
        <h2 style="font-family:var(--font-heading);font-size:22px;color:var(--prussian);margin:0 0 4px">
            <i class="fa-solid fa-star" style="color:#f59e0b"></i> Upgrade para o plano Pro
        </h2>
        <p style="color:var(--gray-500);font-size:14px;margin:0">Desbloqueie todos os recursos do PageQuiz para sua empresa.</p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:20px">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <?php if ($company['plan'] === 'pro'): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:20px">
        <i class="fa-solid fa-star"></i> Você já está no plano <strong>Pro</strong>! Aproveite todos os recursos.
    </div>
    <?php elseif ($company['status'] === 'pending_payment'): ?>
    <div class="alert" style="background:#fef3c7;color:#92400e;border-radius:8px;padding:14px 18px;margin-bottom:20px">
        <i class="fa-solid fa-hourglass-half"></i>
        <strong>Solicitação Pro em análise.</strong> Nossa equipe entrará em contato em breve pelo e-mail <strong><?= htmlspecialchars($company['email']) ?></strong>.
    </div>
    <?php endif; ?>

    <!-- Comparativo -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px">
        <div style="border:2px solid var(--gray-200);border-radius:12px;padding:24px">
            <div style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#e0f2fe;color:#0369a1;margin-bottom:12px">Seu plano atual — Free</div>
            <ul style="margin:0;padding-left:20px;font-size:14px;color:var(--gray-600);line-height:2">
                <li>Até <?= $freeLimit ?> quizzes ativos</li>
                <li>Usuários ilimitados</li>
                <li>Certificado padrão</li>
                <li>Subdomínio próprio</li>
                <li style="color:var(--gray-300)"><s>Logo e cor da empresa</s></li>
                <li style="color:var(--gray-300)"><s>Certificado personalizado</s></li>
                <li style="color:var(--gray-300)"><s>Quizzes ilimitados</s></li>
            </ul>
        </div>
        <div style="border:2px solid #f59e0b;border-radius:12px;padding:24px;background:#fffbeb">
            <div style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#fef3c7;color:#92400e;margin-bottom:12px">
                <i class="fa-solid fa-star"></i> Pro — Todos os recursos
            </div>
            <ul style="margin:0;padding-left:20px;font-size:14px;color:var(--gray-700);line-height:2">
                <li><strong>Quizzes ilimitados</strong></li>
                <li>Usuários ilimitados</li>
                <li><strong>Certificado personalizado</strong> (logo + cor)</li>
                <li>Subdomínio próprio</li>
                <li><strong>Logo da empresa</strong> em todas as páginas</li>
                <li><strong>Cor primária</strong> da empresa</li>
            </ul>
        </div>
    </div>

    <!-- Formulário de solicitação -->
    <?php if ($company['plan'] !== 'pro' && $company['status'] !== 'pending_payment'): ?>
    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <h3 style="font-size:16px;color:var(--prussian);margin:0 0 8px">
            <i class="fa-solid fa-paper-plane" style="color:var(--pacific)"></i> Solicitar ativação do Pro
        </h3>
        <p style="font-size:13px;color:var(--gray-500);margin:0 0 20px">
            A ativação é manual e gratuita nesta fase. Nossa equipe confirmará os detalhes por e-mail e ativará o plano em até 1 dia útil.
        </p>
        <form method="POST">
            <button type="submit" class="btn" style="background:#f59e0b;color:#fff;font-weight:700;font-size:15px;padding:12px 28px">
                <i class="fa-solid fa-star"></i> Solicitar plano Pro
            </button>
        </form>
        <div style="margin-top:16px;font-size:13px;color:var(--gray-400)">
            Ou entre em contato diretamente: <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" style="color:var(--pacific)"><?= htmlspecialchars($supportEmail) ?></a>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php adminFoot(); ?>
