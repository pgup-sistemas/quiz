<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

$statusFlt = trim($_GET['status']  ?? '');
$typeFlt   = trim($_GET['type']    ?? '');
$coFlt     = (int)($_GET['company'] ?? 0);
$page      = max(1, (int)($_GET['p'] ?? 1));
$perPage   = 30;
$offset    = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($statusFlt) { $where[] = "s.status=?"; $params[] = $statusFlt; }
if ($typeFlt)   { $where[] = "s.type=?";   $params[] = $typeFlt; }
if ($coFlt)     { $where[] = "s.company_id=?"; $params[] = $coFlt; }
$whereSql = implode(' AND ', $where);

$total = (int)dbRow("SELECT COUNT(*) AS c FROM subscriptions s WHERE $whereSql", $params)['c'];
$subs  = dbRows(
    "SELECT s.*, c.name AS company_name, c.plan AS company_plan
     FROM subscriptions s LEFT JOIN companies c ON c.id=s.company_id
     WHERE $whereSql ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$totalPages = (int)ceil($total / $perPage);

// Totais para stats
$totalPaid    = (int)dbRow("SELECT COUNT(*) AS c FROM subscriptions WHERE status IN ('paid','active')")['c'];
$totalRevenue = (int)(dbRow("SELECT SUM(amount) AS s FROM subscriptions WHERE status IN ('paid','active')")['s'] ?? 0);
$totalPending = (int)dbRow("SELECT COUNT(*) AS c FROM subscriptions WHERE status='pending'")['c'];
$failedEvents = (int)dbRow("SELECT COUNT(*) AS c FROM payment_events WHERE processed=2")['c'];

$companies = dbRows("SELECT id, name FROM companies ORDER BY name");

$statusLabels = [
    'pending'   => ['Aguardando', '#92400e', '#fef3c7'],
    'active'    => ['Ativo',      '#166534', '#dcfce7'],
    'paid'      => ['Pago',       '#166534', '#dcfce7'],
    'overdue'   => ['Inadim.',    '#991b1b', '#fee2e2'],
    'cancelled' => ['Cancelado',  '#6b7280', '#f3f4f6'],
    'expired'   => ['Expirado',   '#6b7280', '#f3f4f6'],
];
$typeLabels = [
    'pix'            => '<i class="fa-brands fa-pix"></i> PIX',
    'card_once'      => '<i class="fa-solid fa-credit-card"></i> Cartão único',
    'card_recurring' => '<i class="fa-solid fa-rotate"></i> Assinatura',
    'payment_link'   => '<i class="fa-solid fa-link"></i> Link',
    'manual'         => '<i class="fa-solid fa-user-shield"></i> Manual',
];

superadminHead('Pagamentos', 'companies.php');
?>
<div class="sa-wrap">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-money-bill-wave" style="color:var(--yellow)"></i> Pagamentos</h1>
            <div class="sub"><?= $total ?> transaç<?= $total===1?'ão':'ões' ?> encontrada<?= $total===1?'':'s' ?></div>
        </div>
        <a href="payment-link.php" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
            <i class="fa-solid fa-link"></i> Gerar Link de Pagamento
        </a>
    </div>

    <div class="stat-cards">
        <div class="stat-card">
            <div class="sc-label">Total Pago/Ativo</div>
            <div class="sc-val" style="color:#166534"><?= $totalPaid ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Receita Total</div>
            <div class="sc-val" style="color:var(--pacific)">R$<?= number_format($totalRevenue/100,0,'.','.') ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Aguardando</div>
            <div class="sc-val" style="color:#92400e"><?= $totalPending ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Erros Webhook</div>
            <div class="sc-val" style="color:<?= $failedEvents > 0 ? '#991b1b' : 'var(--gray-400)' ?>"><?= $failedEvents ?></div>
            <div class="sc-sub"><?= $failedEvents > 0 ? 'reprocessar manualmente' : 'ok' ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:#1a2d45;padding:16px;border-radius:var(--radius)">
        <select name="status" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todos os status</option>
            <?php foreach ($statusLabels as $k => [$l,$c,$b]): ?>
            <option value="<?= $k ?>" <?= $statusFlt===$k?'selected':'' ?>><?= strip_tags($l) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todos os métodos</option>
            <?php foreach ($typeLabels as $k => $l): ?>
            <option value="<?= $k ?>" <?= $typeFlt===$k?'selected':'' ?>><?= strip_tags($l) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="company" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todas as empresas</option>
            <?php foreach ($companies as $co): ?>
            <option value="<?= $co['id'] ?>" <?= $coFlt==$co['id']?'selected':'' ?>><?= htmlspecialchars($co['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-size:13px">
            <i class="fa-solid fa-magnifying-glass"></i> Filtrar
        </button>
        <?php if ($statusFlt||$typeFlt||$coFlt): ?>
        <a href="payments.php" class="btn" style="background:var(--gray-200);color:var(--gray-700);font-size:13px"><i class="fa-solid fa-xmark"></i> Limpar</a>
        <?php endif; ?>
    </form>

    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <div style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Data</th><th>Empresa</th><th>Método</th>
                    <th>Valor</th><th>Status</th><th>Referência EFI</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($subs)): ?>
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400)">
                Nenhum pagamento encontrado.
            </td></tr>
            <?php endif; ?>
            <?php foreach ($subs as $s): ?>
            <?php [$slabel,$scol,$sbg] = $statusLabels[$s['status']] ?? [$s['status'],'#6b7280','#f3f4f6']; ?>
            <tr>
                <td style="font-size:12px;color:var(--gray-500);white-space:nowrap"><?= substr($s['created_at'],0,16) ?></td>
                <td>
                    <div style="font-weight:600;color:var(--prussian);font-size:13px"><?= htmlspecialchars($s['company_name'] ?? '—') ?></div>
                    <div style="font-size:11px;color:var(--gray-400)"><?= $s['company_plan']==='pro'?'Pro':'Free' ?></div>
                </td>
                <td style="font-size:13px"><?= $typeLabels[$s['type']] ?? $s['type'] ?></td>
                <td style="font-size:13px;font-weight:600">R$ <?= number_format(($s['amount']??0)/100,2,',','.') ?></td>
                <td>
                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:<?= $sbg ?>;color:<?= $scol ?>">
                        <?= htmlspecialchars($slabel) ?>
                    </span>
                </td>
                <td style="font-size:11px;color:var(--gray-400)">
                    <?= htmlspecialchars($s['efi_charge_id'] ?? $s['pix_txid'] ?? $s['efi_subscription_id'] ?? '—') ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid var(--gray-100);display:flex;gap:8px">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <a href="?<?= http_build_query(['status'=>$statusFlt,'type'=>$typeFlt,'company'=>$coFlt,'p'=>$i]) ?>"
               style="padding:4px 10px;border-radius:6px;font-size:13px;text-decoration:none;
                      background:<?= $i===$page?'var(--pacific)':'var(--gray-100)' ?>;
                      color:<?= $i===$page?'#fff':'var(--gray-600)' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php superadminFoot(); ?>
