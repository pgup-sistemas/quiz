<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
sessionStart();
requireLogin();

$companyId = adminCompanyId();
$company   = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);

$subId = (int)($_GET['id'] ?? 0);
$sub   = $subId ? dbRow("SELECT * FROM subscriptions WHERE id=? AND company_id=?", [$subId, $companyId]) : null;

if (!$sub || !in_array($sub['status'], ['active', 'paid'], true)) {
    header('Location: billing.php');
    exit;
}

$typeLabels = [
    'pix'            => 'PIX',
    'card_once'      => 'Cartão de crédito — cobrança única',
    'card_recurring' => 'Cartão de crédito — assinatura recorrente',
    'payment_link'   => 'Link de pagamento',
    'manual'         => 'Ativação manual',
];
$typeLabel = $typeLabels[$sub['type']] ?? $sub['type'];
$ref       = $sub['efi_charge_id'] ?? $sub['pix_txid'] ?? $sub['efi_subscription_id'] ?? ('PQ-' . str_pad((string)$sub['id'], 6, '0', STR_PAD_LEFT));
$appName   = dbRow("SELECT value FROM system_settings WHERE `key`='app_name'")['value'] ?? 'PageQuiz';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Recibo #<?= (int)$sub['id'] ?> · <?= htmlspecialchars($appName) ?></title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body { background:#0b1e35; min-height:100vh; font-family:'DM Sans',sans-serif; margin:0; }
.rc-nav { background:#05111f; border-bottom:2px solid var(--yellow); padding:14px 24px; display:flex; align-items:center; gap:16px; }
.rc-nav a { color:rgba(255,255,255,.7); text-decoration:none; font-size:14px; display:flex; align-items:center; gap:6px; }
.rc-nav a:hover { color:var(--yellow); }
.rc-outer { max-width:640px; margin:32px auto; padding:0 16px 40px; }
.rc-actions { display:flex; gap:12px; justify-content:center; margin-bottom:24px; }
.btn-print { padding:12px 24px; background:var(--prussian); color:#fff; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; font-family:inherit; }
.rc-doc { background:#fff; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,.3); padding:40px; }
.rc-head { display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid var(--prussian); padding-bottom:20px; margin-bottom:24px; }
.rc-head h1 { font-family:'Syne',sans-serif; font-size:20px; color:var(--prussian); margin:0; }
.rc-head .status { background:#dcfce7; color:#166534; font-size:12px; font-weight:700; padding:4px 12px; border-radius:20px; }
.rc-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--gray-100); font-size:14px; }
.rc-row span:first-child { color:var(--gray-500); }
.rc-row span:last-child { color:var(--gray-800); font-weight:600; text-align:right; }
.rc-total { display:flex; justify-content:space-between; padding:16px 0 0; margin-top:10px; font-size:18px; font-weight:800; color:var(--prussian); }
.rc-foot { margin-top:28px; padding-top:16px; border-top:1px dashed var(--gray-200); font-size:11px; color:var(--gray-400); text-align:center; }
@media print {
    @page { size: A4; margin: 18mm; }
    body { background:#fff; }
    .rc-nav, .rc-actions { display:none !important; }
    .rc-outer { margin:0; padding:0; max-width:none; }
    .rc-doc { box-shadow:none; padding:0; }
}
</style>
</head>
<body>
<nav class="rc-nav">
    <a href="billing.php"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
</nav>
<div class="rc-outer">
    <div class="rc-actions">
        <button type="button" class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir / Salvar PDF</button>
    </div>
    <div class="rc-doc">
        <div class="rc-head">
            <h1><?= htmlspecialchars($appName) ?> · Recibo de pagamento</h1>
            <span class="status"><i class="fa-solid fa-circle-check"></i> Pago</span>
        </div>
        <div class="rc-row"><span>Empresa</span><span><?= htmlspecialchars($company['name']) ?></span></div>
        <?php if (!empty($company['cnpj'])): ?>
        <div class="rc-row"><span>CNPJ/CPF</span><span><?= htmlspecialchars($company['cnpj']) ?></span></div>
        <?php endif; ?>
        <div class="rc-row"><span>E-mail</span><span><?= htmlspecialchars($company['email']) ?></span></div>
        <div class="rc-row"><span>Referência</span><span>#<?= (int)$sub['id'] ?> · <?= htmlspecialchars($ref) ?></span></div>
        <div class="rc-row"><span>Método de pagamento</span><span><?= htmlspecialchars($typeLabel) ?></span></div>
        <div class="rc-row"><span>Data</span><span><?= date('d/m/Y H:i', strtotime($sub['created_at'])) ?></span></div>
        <?php if ($sub['next_billing_at']): ?>
        <div class="rc-row"><span>Válido até / próxima cobrança</span><span><?= date('d/m/Y', strtotime($sub['next_billing_at'])) ?></span></div>
        <?php endif; ?>
        <div class="rc-total"><span>Total pago</span><span>R$ <?= number_format(($sub['amount'] ?? 0) / 100, 2, ',', '.') ?></span></div>
        <div class="rc-foot">
            Pagamento processado por EFI Bank · <?= htmlspecialchars($appName) ?> — PageUp Sistemas<br/>
            Documento gerado eletronicamente em <?= date('d/m/Y H:i') ?>, sem valor fiscal.
        </div>
    </div>
</div>
</body>
</html>
