<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/efi.php';
require_once __DIR__ . '/../admin/layout.php';
sessionStart();
requireLogin();

$companyId = adminCompanyId();
$company   = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);

$supportEmail  = dbRow("SELECT value FROM system_settings WHERE `key`='support_email'")['value'] ?? 'contato@pageup.net.br';
$freeLimit     = (int)(dbRow("SELECT value FROM system_settings WHERE `key`='free_quiz_limit'")['value'] ?? 12);
$efiConfigured = (bool)(efiConfig()['client_id'] ?? '');
$priceStr      = efiProPriceFormatted();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if ($company['plan'] === 'pro') {
        $msg = 'Você já está no plano Pro!';
    } elseif ($company['status'] === 'pending_payment') {
        $msg = 'Sua solicitação Pro já está sendo processada. Aguarde o contato da equipe PageUp.';
    } else {
        dbExec("UPDATE companies SET status='pending_payment', updated_at=NOW() WHERE id=?", [$companyId]);
        notifyProRequest($company['name'], $company['email'], $supportEmail);
        $msg = 'Solicitação enviada! Entraremos em contato em breve para ativar o plano Pro.';
    }
}

// Recarregar
$company     = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);
// Pagar diretamente (PIX/cartão) deve continuar disponível mesmo com uma
// solicitação manual pendente — pagar agora é mais rápido que esperar
// contato manual, e o webhook ativa o Pro independente do status atual.
$canUpgrade      = $company['plan'] !== 'pro';
$canRequestManual = $canUpgrade && $company['status'] !== 'pending_payment';
adminHead('Upgrade para Pro', 'upgrade.php');
?>
<style>
/* ── Upgrade / checkout ─────────────────────────────────────────────── */
.upg-header      { margin-bottom: 24px; }
.upg-header h2   { font-family: var(--font-heading); font-size: 22px; color: var(--prussian); margin: 0 0 4px; display: flex; align-items: center; gap: 10px; }
.upg-header p    { color: var(--gray-500); font-size: 14px; margin: 0; }

/* Métodos de pagamento — mesmo peso visual, diferenciados só por ícone/label */
.upg-pay-card    { border-radius: var(--radius); padding: 28px; box-shadow: 0 1px 4px rgba(0,0,0,.08); background: #fff; margin-bottom: 24px; }
.upg-pay-card h3 { font-size: 16px; color: var(--prussian); margin: 0 0 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.upg-pay-price   { color: var(--pacific); font-weight: 800; }
.upg-pay-sub     { font-size: 13px; color: var(--gray-500); margin: 0 0 20px; }
.upg-method-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.upg-method      {
    position: relative;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    text-align: center; text-decoration: none;
    padding: 22px 16px 18px;
    border: 1.5px solid var(--gray-200);
    border-radius: 14px;
    color: var(--gray-700);
    transition: border-color .15s, box-shadow .15s, transform .15s;
}
.upg-method:hover { border-color: var(--pacific); box-shadow: 0 6px 18px rgba(33,158,188,.14); transform: translateY(-2px); }
.upg-method i.upg-method-icon { font-size: 26px; color: var(--pacific); }
.upg-method strong { font-size: 14px; color: var(--prussian); }
.upg-method span.upg-method-desc { font-size: 12px; color: var(--gray-500); line-height: 1.4; }
.upg-method-badge {
    position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
    background: var(--yellow); color: var(--prussian);
    font-size: 10px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
    padding: 3px 10px; border-radius: 20px; white-space: nowrap;
}

/* Comparativo Free x Pro — referência secundária, abaixo da decisão de compra */
.upg-compare-title { font-size: 13px; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .04em; margin: 0 0 12px; }
.upg-compare-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
.upg-plan         { border: 1.5px solid var(--gray-200); border-radius: 12px; padding: 22px 24px; }
.upg-plan-pro     { border-color: #f59e0b; background: #fffbeb; }
.upg-plan-tag     { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-bottom: 12px; }
.upg-plan-tag-free{ background: #e0f2fe; color: #0369a1; }
.upg-plan-tag-pro { background: #fef3c7; color: #92400e; }
.upg-plan ul      { margin: 0; padding-left: 20px; font-size: 14px; line-height: 2; color: var(--gray-600); }
.upg-plan-pro ul  { color: #1e293b; }
.upg-plan-pro strong { color: #1e293b; }
.upg-plan-off     { color: var(--gray-300); }

/* Alternativa manual — deliberadamente discreta, não compete com o CTA principal */
.upg-fallback     { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 16px 4px; border-top: 1px solid var(--gray-100); font-size: 13px; color: var(--gray-500); }
.upg-fallback form { margin: 0; }
.upg-fallback .btn-link { background: none; border: none; padding: 0; color: var(--pacific); font-weight: 700; font-size: 13px; cursor: pointer; font-family: inherit; text-decoration: underline; }

@media (max-width: 720px) {
    .upg-method-grid, .upg-compare-grid { grid-template-columns: 1fr; }
}
</style>

<div class="admin-wrap">
    <div class="upg-header">
        <h2><i class="fa-solid fa-star" style="color:#f59e0b"></i> Upgrade para o plano Pro</h2>
        <p>Desbloqueie todos os recursos do PageQuiz para sua empresa.</p>
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

    <?php if ($canUpgrade): ?>

        <?php if ($efiConfigured): ?>
        <!-- 1) Decisão de compra — sempre primeiro, é o que a maioria veio fazer -->
        <div class="upg-pay-card">
            <h3><i class="fa-solid fa-star" style="color:#f59e0b"></i> Assinar plano Pro — <span class="upg-pay-price"><?= htmlspecialchars($priceStr) ?>/mês</span></h3>
            <p class="upg-pay-sub">Pagamento processado com segurança pela EFI Bank. Ativação imediata após confirmação.</p>
            <div class="upg-method-grid">
                <a href="../payments/checkout.php?method=pix" class="upg-method">
                    <i class="fa-brands fa-pix upg-method-icon"></i>
                    <strong>PIX</strong>
                    <span class="upg-method-desc">Aprovação instantânea</span>
                </a>
                <a href="../payments/checkout.php?method=card_once" class="upg-method">
                    <i class="fa-solid fa-credit-card upg-method-icon"></i>
                    <strong>Cartão</strong>
                    <span class="upg-method-desc">Cobrança única — vale 1 mês</span>
                </a>
                <a href="../payments/checkout.php?method=card_recurring" class="upg-method">
                    <span class="upg-method-badge">Recomendado</span>
                    <i class="fa-solid fa-rotate upg-method-icon"></i>
                    <strong>Assinatura</strong>
                    <span class="upg-method-desc">Renova sozinha, cancele quando quiser</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 2) Comparativo — referência de apoio para quem ainda está decidindo -->
        <div class="upg-compare-title">O que muda no Pro</div>
        <div class="upg-compare-grid">
            <div class="upg-plan">
                <div class="upg-plan-tag upg-plan-tag-free">Seu plano atual — Free</div>
                <ul>
                    <li>Até <?= $freeLimit ?> quizzes ativos</li>
                    <li>Usuários ilimitados</li>
                    <li>Certificado padrão</li>
                    <li>Subdomínio próprio</li>
                    <li class="upg-plan-off"><s>Logo e cor da empresa</s></li>
                    <li class="upg-plan-off"><s>Certificado personalizado</s></li>
                    <li class="upg-plan-off"><s>Quizzes ilimitados</s></li>
                </ul>
            </div>
            <div class="upg-plan upg-plan-pro">
                <div class="upg-plan-tag upg-plan-tag-pro"><i class="fa-solid fa-star"></i> Pro — Todos os recursos</div>
                <ul>
                    <li><strong>Quizzes ilimitados</strong></li>
                    <li>Usuários ilimitados</li>
                    <li><strong>Certificado personalizado</strong> (logo + cor)</li>
                    <li>Subdomínio próprio</li>
                    <li><strong>Logo da empresa</strong> em todas as páginas</li>
                    <li><strong>Cor primária</strong> da empresa</li>
                </ul>
            </div>
        </div>

        <!-- 3) Alternativa manual — discreta de propósito, não é o caminho principal.
             Some quando já há uma solicitação pendente (a mensagem acima já cobre isso),
             mas os cards de pagamento acima continuam disponíveis para quem quiser pagar agora. -->
        <?php if ($canRequestManual): ?>
        <div class="upg-fallback">
            <span>
                <?= $efiConfigured
                    ? 'Prefere combinar diretamente com a gente (boleto, transferência, etc.)?'
                    : 'A ativação é manual nesta fase — nossa equipe confirma os detalhes por e-mail em até 1 dia útil.'
                ?>
                <a href="mailto:<?= htmlspecialchars($supportEmail) ?>"><?= htmlspecialchars($supportEmail) ?></a>
            </span>
            <form method="POST">
                <?= csrfField() ?>
                <button type="submit" class="btn-link">Solicitar ativação manual</button>
            </form>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
<?php adminFoot(); ?>
