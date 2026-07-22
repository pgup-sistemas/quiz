<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/layout.php';

$id  = (int)($_GET['id'] ?? 0);
$sub = $id ? dbRow(
    "SELECT s.*, c.name AS company_name, c.slug AS company_slug, c.plan AS company_plan
     FROM subscriptions s LEFT JOIN companies c ON c.id = s.company_id
     WHERE s.id = ?", [$id]
) : null;
if (!$sub) { header('Location: payments.php'); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $act = $_POST['action'] ?? '';
    if ($act === 'cancel' && in_array($sub['status'], ['pending', 'overdue'], true)) {
        dbExec("UPDATE subscriptions SET status='cancelled', updated_at=NOW() WHERE id=?", [$id]);
        logAudit('cancel_subscription', (int)$sub['company_id'], json_encode(['sub_id' => $id, 'type' => $sub['type']]));
        $msg = 'Registro marcado como cancelado.';
        $sub['status'] = 'cancelled';
    } elseif ($act === 'retry_event') {
        $evId = (int)($_POST['event_id'] ?? 0);
        $ok = $evId && reprocessPaymentEvent($evId);
        logAudit('webhook_retry', (int)$sub['company_id'], json_encode(['event_id' => $evId, 'success' => $ok]));
        $msg = $ok ? 'Evento reprocessado com sucesso.' : 'Falha ao reprocessar o evento.';
        $sub = dbRow(
            "SELECT s.*, c.name AS company_name, c.slug AS company_slug, c.plan AS company_plan
             FROM subscriptions s LEFT JOIN companies c ON c.id = s.company_id
             WHERE s.id = ?", [$id]
        );
    }
}

$events = dbRows(
    "SELECT * FROM payment_events WHERE subscription_id = ? ORDER BY created_at DESC",
    [$id]
);

$statusLabels = [
    'pending'   => ['Aguardando',    '#92400e', 'rgba(251,191,36,.15)'],
    'active'    => ['Ativo',         '#166534', 'rgba(34,197,94,.15)'],
    'paid'      => ['Pago',          '#166534', 'rgba(34,197,94,.15)'],
    'overdue'   => ['Inadimplente',  '#991b1b', 'rgba(239,68,68,.15)'],
    'cancelled' => ['Cancelado',     '#6b7280', 'rgba(255,255,255,.08)'],
    'expired'   => ['Expirado',      '#6b7280', 'rgba(255,255,255,.08)'],
];
[$slabel, $scol, $sbg] = $statusLabels[$sub['status']] ?? [$sub['status'], '#6b7280', 'rgba(255,255,255,.08)'];

$typeLabels = [
    'pix'            => 'PIX',
    'card_once'      => 'Cartão — cobrança única',
    'card_recurring' => 'Cartão — assinatura recorrente',
    'payment_link'   => 'Link de pagamento',
    'manual'         => 'Ativação manual',
];

superadminHead('Detalhes do Pagamento', 'payments.php');
?>
<div class="sa-wrap" style="max-width:820px">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-receipt" style="color:var(--yellow)"></i> Detalhes do Pagamento #<?= $id ?></h1>
            <div class="sub"><a href="payments.php" style="color:var(--gray-400)">← Voltar para pagamentos</a></div>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:16px">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="border-radius:var(--radius);padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:20px">
            <div>
                <div style="font-size:12px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Empresa</div>
                <div style="font-size:18px;font-weight:700;color:var(--prussian)">
                    <a href="company-detail.php?id=<?= (int)$sub['company_id'] ?>" style="color:inherit;text-decoration:none">
                        <?= htmlspecialchars($sub['company_name'] ?? '—') ?>
                    </a>
                </div>
                <div style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($sub['company_slug'] ?? '') ?> · <?= $sub['company_plan'] === 'pro' ? 'Pro' : 'Free' ?></div>
            </div>
            <span style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:13px;font-weight:700;background:<?= $sbg ?>;color:<?= $scol ?>">
                <?= htmlspecialchars($slabel) ?>
            </span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--gray-100)">
            <div>
                <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Método</div>
                <div style="font-size:14px;font-weight:600;color:var(--gray-700)"><?= htmlspecialchars($typeLabels[$sub['type']] ?? $sub['type']) ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Valor</div>
                <div style="font-size:14px;font-weight:600;color:var(--gray-700)">R$ <?= number_format(($sub['amount'] ?? 0) / 100, 2, ',', '.') ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Criado em</div>
                <div style="font-size:14px;color:var(--gray-700)"><?= date('d/m/Y H:i', strtotime($sub['created_at'])) ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Próxima cobrança / validade</div>
                <div style="font-size:14px;color:var(--gray-700)"><?= $sub['next_billing_at'] ? date('d/m/Y H:i', strtotime($sub['next_billing_at'])) : '—' ?></div>
            </div>
            <?php if ($sub['grace_until']): ?>
            <div>
                <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Carência até</div>
                <div style="font-size:14px;color:var(--gray-700)"><?= date('d/m/Y H:i', strtotime($sub['grace_until'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($sub['notes']): ?>
            <div style="grid-column:1/-1">
                <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Observação</div>
                <div style="font-size:14px;color:var(--gray-700)"><?= htmlspecialchars($sub['notes']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Referencias EFI -->
        <div style="margin-bottom:20px">
            <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Referências EFI Bank</div>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;font-family:monospace">
                <?php if ($sub['efi_charge_id']): ?><div><span style="color:var(--gray-400)">charge_id:</span> <?= htmlspecialchars($sub['efi_charge_id']) ?></div><?php endif; ?>
                <?php if ($sub['efi_subscription_id']): ?><div><span style="color:var(--gray-400)">subscription_id:</span> <?= htmlspecialchars($sub['efi_subscription_id']) ?></div><?php endif; ?>
                <?php if ($sub['pix_txid']): ?><div><span style="color:var(--gray-400)">pix_txid:</span> <?= htmlspecialchars($sub['pix_txid']) ?></div><?php endif; ?>
                <?php if (!$sub['efi_charge_id'] && !$sub['efi_subscription_id'] && !$sub['pix_txid']): ?>
                <div style="color:var(--gray-400)">Nenhuma referência registrada ainda.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Link de pagamento -->
        <?php if ($sub['type'] === 'payment_link' && $sub['payment_link_url']): ?>
        <div style="margin-bottom:20px">
            <div style="font-size:11px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Link de Pagamento</div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <code id="link-url" style="background:rgba(255,255,255,.08);padding:8px 12px;border-radius:8px;font-size:12px;word-break:break-all;flex:1;min-width:200px"><?= htmlspecialchars($sub['payment_link_url']) ?></code>
                <button type="button" class="btn btn-sm" style="background:var(--pacific);color:#fff"
                        onclick="navigator.clipboard.writeText(document.getElementById('link-url').textContent).then(()=>{this.innerHTML='<i class=\'fa-solid fa-check\'></i> Copiado!';})">
                    <i class="fa-solid fa-copy"></i> Copiar
                </button>
                <a href="<?= htmlspecialchars($sub['payment_link_url']) ?>" target="_blank" class="btn btn-sm" style="background:var(--gray-100);color:var(--gray-700)">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Acoes -->
        <?php if (in_array($sub['status'], ['pending', 'overdue'], true)): ?>
        <form method="POST" onsubmit="return confirm('Marcar este registro como cancelado? Isso não afeta o plano atual da empresa.')" style="border-top:1px solid var(--gray-100);padding-top:16px">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="cancel"/>
            <button type="submit" class="btn btn-sm" style="background:rgba(239,68,68,.15);color:#fca5a5;font-weight:700">
                <i class="fa-solid fa-ban"></i> Marcar como cancelado
            </button>
            <span style="font-size:12px;color:var(--gray-400);margin-left:8px">Use para links/PIX gerados que nunca foram pagos.</span>
        </form>
        <?php endif; ?>
    </div>

    <!-- Historico de eventos de webhook -->
    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:20px">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gray-100)">
            <strong style="color:var(--prussian)"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de eventos de webhook</strong>
        </div>
        <?php if (empty($events)): ?>
        <div style="padding:32px;text-align:center;color:var(--gray-400);font-size:13px">
            Nenhum evento de webhook recebido para este pagamento ainda.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr><th>Data</th><th>Evento</th><th>Payload</th><th>Status</th><th>Ação</th></tr>
            </thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
            <tr>
                <td style="font-size:12px;color:var(--gray-500);white-space:nowrap"><?= date('d/m/Y H:i:s', strtotime($ev['created_at'])) ?></td>
                <td style="font-size:12px"><code><?= htmlspecialchars($ev['event_type']) ?></code></td>
                <td style="font-size:11px;color:var(--gray-400);max-width:260px">
                    <code style="font-size:10px;background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                          title="<?= htmlspecialchars($ev['raw_payload'] ?? '') ?>">
                        <?= htmlspecialchars(mb_substr($ev['raw_payload'] ?? '', 0, 60)) ?>…
                    </code>
                </td>
                <td>
                    <?php if ((int)$ev['processed'] === 1): ?>
                    <span style="color:#86efac;font-size:12px"><i class="fa-solid fa-circle-check"></i> Processado</span>
                    <?php elseif ((int)$ev['processed'] === 2): ?>
                    <span style="color:#fca5a5;font-size:12px"><i class="fa-solid fa-circle-xmark"></i> Falhou</span>
                    <?php else: ?>
                    <span style="color:var(--gray-400);font-size:12px"><i class="fa-solid fa-hourglass-half"></i> Pendente</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int)$ev['processed'] === 2): ?>
                    <form method="POST" onsubmit="return confirm('Reprocessar este evento?')" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="retry_event"/>
                        <input type="hidden" name="event_id" value="<?= $ev['id'] ?>"/>
                        <button type="submit" class="btn-xs primary"><i class="fa-solid fa-rotate-right"></i> Reprocessar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php superadminFoot(); ?>
