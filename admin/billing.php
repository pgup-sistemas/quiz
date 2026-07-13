<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../admin/layout.php';
sessionStart();
requireLogin();

$companyId = adminCompanyId();
$company   = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);

// Assinatura ativa ou mais recente
$activeSub = dbRow(
    "SELECT * FROM subscriptions WHERE company_id=? AND status IN ('active','pending','overdue') ORDER BY created_at DESC LIMIT 1",
    [$companyId]
);

// Histórico de pagamentos
$history = dbRows(
    "SELECT * FROM subscriptions WHERE company_id=? ORDER BY created_at DESC LIMIT 20",
    [$companyId]
);

$planLabels = [
    'free' => 'Free',
    'pro'  => 'Pro',
];
$statusLabels = [
    'pending'   => ['Aguardando',    '#92400e', '#fef3c7'],
    'active'    => ['Ativo',         '#166534', '#dcfce7'],
    'paid'      => ['Pago',          '#166534', '#dcfce7'],
    'overdue'   => ['Inadimplente',  '#991b1b', '#fee2e2'],
    'cancelled' => ['Cancelado',     '#6b7280', '#f3f4f6'],
    'expired'   => ['Expirado',      '#6b7280', '#f3f4f6'],
];
$typeLabels = [
    'pix'            => 'PIX',
    'card_once'      => 'Cartão — cobrança única',
    'card_recurring' => 'Cartão — assinatura',
    'payment_link'   => 'Link de pagamento',
    'manual'         => 'Ativação manual',
];

adminHead('Cobrança e Assinatura', 'billing.php');
?>
<div class="admin-wrap">
    <div style="margin-bottom:24px">
        <h2 style="font-family:var(--font-heading);font-size:22px;color:var(--prussian);margin:0 0 4px">
            <i class="fa-solid fa-receipt" style="color:var(--pacific)"></i> Cobrança e Assinatura
        </h2>
        <p style="color:var(--gray-500);font-size:14px;margin:0">Gerencie seu plano e histórico de pagamentos.</p>
    </div>

    <?php if (isset($_GET['activated'])): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:20px">
        <i class="fa-solid fa-star" style="color:#f59e0b"></i>
        <strong>Plano Pro ativado!</strong> Agora você tem quizzes ilimitados e todos os recursos Pro.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['cancelled'])): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:20px">
        <i class="fa-solid fa-circle-check"></i> Assinatura cancelada com sucesso.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert" style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:20px">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <!-- Plano atual -->
    <div class="card shadow-sm" style="margin-bottom:24px;border-radius:var(--radius);padding:24px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
        <div>
            <div style="font-size:12px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Plano atual</div>
            <div style="display:flex;align-items:center;gap:12px">
                <span style="font-size:28px;font-weight:800;font-family:var(--font-heading);color:var(--prussian)">
                    <?= $planLabels[$company['plan']] ?? $company['plan'] ?>
                </span>
                <?php if ($company['plan'] === 'pro'): ?>
                <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700">
                    <i class="fa-solid fa-star"></i> Pro
                </span>
                <?php else: ?>
                <span style="background:#e0f2fe;color:#0369a1;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700">Free</span>
                <?php endif; ?>
            </div>
            <?php if ($activeSub && $activeSub['type'] === 'card_recurring' && $activeSub['next_billing_at']): ?>
            <div style="font-size:13px;color:var(--gray-500);margin-top:6px">
                <i class="fa-solid fa-rotate"></i>
                Próxima cobrança: <strong><?= date('d/m/Y', strtotime($activeSub['next_billing_at'])) ?></strong>
                — <?= 'R$ ' . number_format(($activeSub['amount'] ?? 0) / 100, 2, ',', '.') ?>
            </div>
            <?php endif; ?>
            <?php if ($activeSub && $activeSub['status'] === 'overdue' && $activeSub['grace_until']): ?>
            <div style="margin-top:8px;background:#fee2e2;color:#991b1b;border-radius:8px;padding:8px 12px;font-size:13px">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Pagamento em atraso. Regularize até <strong><?= date('d/m/Y', strtotime($activeSub['grace_until'])) ?></strong>
                para não perder o Pro.
            </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if ($company['plan'] === 'free'): ?>
            <a href="../payments/checkout.php?method=pix" class="btn btn-primary">
                <i class="fa-solid fa-star"></i> Assinar Pro
            </a>
            <?php elseif ($activeSub && $activeSub['type'] === 'card_recurring' && $activeSub['status'] === 'active'): ?>
            <form method="POST" action="../payments/cancel.php" onsubmit="return confirm('Cancelar assinatura recorrente? Seu Pro ficará ativo até o fim do período pago.')">
                <input type="hidden" name="sub_id" value="<?= $activeSub['id'] ?>"/>
                <button type="submit" class="btn btn-outline">
                    <i class="fa-solid fa-xmark"></i> Cancelar assinatura
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Histórico -->
    <div class="card shadow-sm" style="border-radius:var(--radius);overflow:hidden">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100)">
            <strong style="color:var(--prussian)"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de pagamentos</strong>
        </div>
        <?php if (empty($history)): ?>
        <div style="padding:40px;text-align:center;color:var(--gray-400)">
            <i class="fa-solid fa-receipt" style="font-size:32px;margin-bottom:8px;display:block;opacity:.3"></i>
            Nenhum pagamento registrado.
            <?php if ($company['plan'] === 'free'): ?>
            <div style="margin-top:12px"><a href="../payments/checkout.php" class="btn btn-primary btn-sm">Assinar Pro</a></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--gray-50)">
                    <th style="text-align:left;font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;padding:10px 16px;border-bottom:2px solid var(--gray-100)">Data</th>
                    <th style="text-align:left;font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;padding:10px 16px;border-bottom:2px solid var(--gray-100)">Método</th>
                    <th style="text-align:left;font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;padding:10px 16px;border-bottom:2px solid var(--gray-100)">Valor</th>
                    <th style="text-align:left;font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;padding:10px 16px;border-bottom:2px solid var(--gray-100)">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <?php [$label, $col, $bg] = $statusLabels[$h['status']] ?? [$h['status'], '#6b7280', '#f3f4f6']; ?>
            <tr style="border-bottom:1px solid var(--gray-100)">
                <td style="padding:11px 16px;font-size:13px;color:var(--gray-500)"><?= substr($h['created_at'], 0, 16) ?></td>
                <td style="padding:11px 16px;font-size:13px"><?= htmlspecialchars($typeLabels[$h['type']] ?? $h['type']) ?></td>
                <td style="padding:11px 16px;font-size:13px;font-weight:600">R$ <?= number_format(($h['amount'] ?? 0) / 100, 2, ',', '.') ?></td>
                <td style="padding:11px 16px">
                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:<?= $bg ?>;color:<?= $col ?>">
                        <?= htmlspecialchars($label) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php adminFoot(); ?>
