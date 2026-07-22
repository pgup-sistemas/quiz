<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/layout.php';

// Exportação CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $all = dbRows(
        "SELECT s.created_at, c.name AS company_name, s.type, s.amount, s.status,
                s.efi_charge_id, s.pix_txid, s.efi_subscription_id, s.next_billing_at
         FROM subscriptions s LEFT JOIN companies c ON c.id=s.company_id
         ORDER BY s.created_at DESC"
    );
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pagamentos_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Data','Empresa','Método','Valor (R$)','Status','Ref. EFI','Próxima cobrança'], ';');
    $typeMap = ['pix'=>'PIX','card_once'=>'Cartão único','card_recurring'=>'Assinatura','payment_link'=>'Link','manual'=>'Manual'];
    foreach ($all as $row) {
        fputcsv($out, [
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['company_name'] ?? '',
            $typeMap[$row['type']] ?? $row['type'],
            number_format(($row['amount'] ?? 0) / 100, 2, ',', '.'),
            $row['status'],
            $row['efi_charge_id'] ?? $row['pix_txid'] ?? $row['efi_subscription_id'] ?? '',
            $row['next_billing_at'] ? date('d/m/Y', strtotime($row['next_billing_at'])) : '',
        ], ';');
    }
    fclose($out);
    exit;
}

// Retry de evento de webhook com falha
if (isset($_GET['retry'])) {
    $retryId = (int)$_GET['retry'];
    $ev = dbRow("SELECT id FROM payment_events WHERE id=? AND processed=2", [$retryId]);
    $ok = $ev && reprocessPaymentEvent($retryId);
    logAudit('webhook_retry', 0, json_encode(['event_id' => $retryId, 'success' => $ok]));
    header('Location: payments.php?_msg=' . urlencode($ok ? 'Evento reprocessado com sucesso.' : 'Falha ao reprocessar o evento.') . '&_ok=' . ($ok ? '1' : '0'));
    exit;
}

$flashMsg = $_GET['_msg'] ?? '';
$flashOk  = ($_GET['_ok'] ?? '1') === '1';

$statusFlt   = trim($_GET['status']     ?? '');
$typeFlt     = trim($_GET['type']       ?? '');
$coFlt       = (int)($_GET['company']   ?? 0);
$dateFrom    = trim($_GET['date_from']  ?? '');
$dateTo      = trim($_GET['date_to']    ?? '');
$page        = max(1, (int)($_GET['p']  ?? 1));
$perPage     = 30;
$offset      = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($statusFlt) { $where[] = "s.status=?"; $params[] = $statusFlt; }
if ($typeFlt)   { $where[] = "s.type=?";   $params[] = $typeFlt; }
if ($coFlt)     { $where[] = "s.company_id=?"; $params[] = $coFlt; }
if ($dateFrom)  { $where[] = "DATE(s.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)    { $where[] = "DATE(s.created_at) <= ?"; $params[] = $dateTo; }
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

$failedEventRows = dbRows(
    "SELECT pe.*, c.name AS company_name
     FROM payment_events pe
     LEFT JOIN companies c ON c.id = pe.company_id
     WHERE pe.processed = 2
     ORDER BY pe.created_at DESC
     LIMIT 50"
);

$statusLabels = [
    'pending'   => ['Aguardando', '#fcd34d', 'rgba(251,191,36,.15)'],
    'active'    => ['Ativo',      '#86efac', 'rgba(34,197,94,.15)'],
    'paid'      => ['Pago',       '#86efac', 'rgba(34,197,94,.15)'],
    'overdue'   => ['Inadim.',    '#fca5a5', 'rgba(239,68,68,.15)'],
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

superadminHead('Pagamentos', 'payments.php');
?>
<div class="sa-wrap">

    <?php if ($flashMsg): ?>
    <div class="alert <?= $flashOk ? 'alert-success' : '' ?>" style="<?= $flashOk ? '' : 'background:rgba(239,68,68,.15);color:#fca5a5;' ?>border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <i class="fa-solid fa-<?= $flashOk ? 'circle-check' : 'circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flashMsg) ?>
    </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-money-bill-wave" style="color:var(--yellow)"></i> Pagamentos</h1>
            <div class="sub"><?= $total ?> transaç<?= $total===1?'ão':'ões' ?> encontrada<?= $total===1?'':'s' ?></div>
        </div>
        <div style="display:flex;gap:8px">
            <a href="payments.php?export=csv" class="btn" style="background:var(--gray-100);color:var(--gray-700);font-weight:600">
                <i class="fa-solid fa-file-csv"></i> Exportar CSV
            </a>
            <a href="payment-link.php" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
                <i class="fa-solid fa-link"></i> Gerar Link de Pagamento
            </a>
        </div>
    </div>

    <div class="stat-cards">
        <div class="stat-card">
            <div class="sc-label">Total Pago/Ativo</div>
            <div class="sc-val" style="color:#86efac"><?= $totalPaid ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Receita Total</div>
            <div class="sc-val" style="color:var(--pacific)">R$<?= number_format($totalRevenue/100,0,'.','.') ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Aguardando</div>
            <div class="sc-val" style="color:#fcd34d"><?= $totalPending ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Erros Webhook</div>
            <div class="sc-val" style="color:<?= $failedEvents > 0 ? '#fca5a5' : 'var(--gray-400)' ?>"><?= $failedEvents ?></div>
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
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
               title="De"
               style="padding:8px 10px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px"/>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
               title="Até"
               style="padding:8px 10px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px"/>
        <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-size:13px">
            <i class="fa-solid fa-magnifying-glass"></i> Filtrar
        </button>
        <?php if ($statusFlt||$typeFlt||$coFlt||$dateFrom||$dateTo): ?>
        <a href="payments.php" class="btn" style="background:var(--gray-200);color:var(--gray-700);font-size:13px"><i class="fa-solid fa-xmark"></i> Limpar</a>
        <?php endif; ?>
    </form>

    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <div style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Data</th><th>Empresa</th><th>Método</th>
                    <th>Valor</th><th>Status</th><th>Referência EFI</th><th>Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($subs)): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400)">
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
                <td>
                    <div class="actions">
                        <a href="payment-detail.php?id=<?= $s['id'] ?>" class="btn-xs ghost" title="Ver detalhes">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <?php if ($s['type'] === 'payment_link' && !empty($s['payment_link_url'])): ?>
                        <button type="button" class="btn-xs primary" title="Copiar link"
                                onclick="navigator.clipboard.writeText('<?= htmlspecialchars($s['payment_link_url'], ENT_QUOTES) ?>').then(()=>this.innerHTML='<i class=\'fa-solid fa-check\'></i>')">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid var(--gray-100);display:flex;gap:8px">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <a href="?<?= http_build_query(['status'=>$statusFlt,'type'=>$typeFlt,'company'=>$coFlt,'date_from'=>$dateFrom,'date_to'=>$dateTo,'p'=>$i]) ?>"
               style="padding:4px 10px;border-radius:6px;font-size:13px;text-decoration:none;
                      background:<?= $i===$page?'var(--pacific)':'var(--gray-100)' ?>;
                      color:<?= $i===$page?'#fff':'var(--gray-600)' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

<?php if (!empty($failedEventRows)): ?>
    <div style="margin-top:32px">
        <div class="page-header" style="margin-bottom:16px">
            <div>
                <h2 style="font-size:17px;font-weight:700;display:flex;align-items:center;gap:8px">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444"></i>
                    Eventos de Webhook com Falha
                    <span style="background:rgba(239,68,68,.15);color:#fca5a5;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px"><?= count($failedEventRows) ?></span>
                </h2>
                <div class="sub" style="color:#ef4444">Estes eventos não foram processados. Clique em Reprocessar para tentar novamente.</div>
            </div>
        </div>
        <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
            <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo de Evento</th>
                        <th>Empresa</th>
                        <th>Payload (resumo)</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($failedEventRows as $ev): ?>
                <tr>
                    <td style="font-size:12px;color:var(--gray-500);white-space:nowrap"><?= substr($ev['created_at'],0,16) ?></td>
                    <td>
                        <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:rgba(239,68,68,.15);color:#fca5a5">
                            <?= htmlspecialchars($ev['event_type']) ?>
                        </span>
                    </td>
                    <td style="font-size:13px"><?= htmlspecialchars($ev['company_name'] ?? '—') ?></td>
                    <td style="font-size:11px;color:var(--gray-400);max-width:240px">
                        <code style="font-size:10px;background:rgba(0,0,0,.05);padding:2px 6px;border-radius:4px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                              title="<?= htmlspecialchars($ev['raw_payload']) ?>">
                            <?= htmlspecialchars(mb_substr($ev['raw_payload'] ?? '', 0, 80)) ?>…
                        </code>
                    </td>
                    <td>
                        <a href="payments.php?retry=<?= $ev['id'] ?>"
                           onclick="return confirm('Reprocessar este evento de webhook?')"
                           class="btn" style="background:var(--pacific);color:#fff;font-size:12px;padding:5px 12px">
                            <i class="fa-solid fa-rotate-right"></i> Reprocessar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.sa-wrap -->
<?php superadminFoot(); ?>
