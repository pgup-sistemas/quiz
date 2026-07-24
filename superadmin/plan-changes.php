<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

/**
 * Linha do tempo de mudanças de plano — quem virou Pro, quem perdeu o Pro,
 * quem está em atraso, quem cancelou e quem está aguardando aprovação manual.
 * Não é uma tabela nova: consolida audit_log (eventos já registrados pelos
 * fluxos de pagamento/downgrade) + o estado atual de companies/subscriptions
 * para o caso "pendente", que nunca gera evento em audit_log.
 */

$typeFlt    = trim($_GET['type']    ?? '');
$companyFlt = (int)($_GET['company'] ?? 0);
$page       = max(1, (int)($_GET['p'] ?? 1));
$perPage    = 30;
$offset     = ($page - 1) * $perPage;

$typeLabels = [
    'became_pro' => ['bg' => 'rgba(34,197,94,.15)',  'col' => '#86efac', 'icon' => 'fa-star',           'label' => 'Virou Pro'],
    'lost_pro'   => ['bg' => 'rgba(239,68,68,.15)',  'col' => '#fca5a5', 'icon' => 'fa-arrow-down',     'label' => 'Perdeu o Pro (expirou)'],
    'overdue'    => ['bg' => 'rgba(251,191,36,.15)', 'col' => '#fcd34d', 'icon' => 'fa-triangle-exclamation', 'label' => 'Entrou em atraso'],
    'cancelled'  => ['bg' => 'rgba(148,163,184,.18)','col' => '#cbd5e1', 'icon' => 'fa-ban',            'label' => 'Cancelou assinatura'],
    'pending'    => ['bg' => 'rgba(56,189,248,.15)', 'col' => '#7dd3fc', 'icon' => 'fa-hourglass-half', 'label' => 'Aguardando aprovação'],
];

// União dos eventos de audit_log + estado atual "pendente" (que não gera evento)
$unionSql = "
    SELECT 'became_pro' AS event_type, target_company_id AS company_id, created_at, detail FROM audit_log WHERE action IN ('payment_confirmed','approve_pro')
    UNION ALL
    SELECT 'lost_pro', target_company_id, created_at, detail FROM audit_log WHERE action = 'auto_downgrade'
    UNION ALL
    SELECT 'overdue', target_company_id, created_at, detail FROM audit_log WHERE action = 'subscription_overdue'
    UNION ALL
    SELECT 'cancelled', target_company_id, created_at, detail FROM audit_log WHERE action = 'subscription_cancelled'
    UNION ALL
    SELECT 'pending', id, updated_at, NULL FROM companies WHERE status = 'pending_payment'
";

$where  = ['1=1'];
$params = [];
if ($typeFlt && isset($typeLabels[$typeFlt])) { $where[] = 't.event_type = ?'; $params[] = $typeFlt; }
if ($companyFlt) { $where[] = 't.company_id = ?'; $params[] = $companyFlt; }
$whereSql = implode(' AND ', $where);

$total = (int)dbRow("SELECT COUNT(*) AS c FROM ($unionSql) t WHERE $whereSql", $params)['c'];
$rows  = dbRows(
    "SELECT t.*, c.name AS company_name, c.plan AS company_plan
     FROM ($unionSql) t
     LEFT JOIN companies c ON c.id = t.company_id
     WHERE $whereSql
     ORDER BY t.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);
$totalPages = (int)ceil($total / $perPage);

// Estado atual (não é histórico — é "agora"), pro contexto no topo da página
$now             = date('Y-m-d H:i:s');
$countPending    = (int)dbRow("SELECT COUNT(*) AS c FROM companies WHERE status='pending_payment'")['c'];
$countOverdueNow = (int)dbRow("SELECT COUNT(*) AS c FROM subscriptions WHERE status='overdue' AND grace_until >= ?", [$now])['c'];
$countProActive  = (int)dbRow("SELECT COUNT(*) AS c FROM companies WHERE plan='pro' AND status='active'")['c'];

$companies = dbRows("SELECT id, name FROM companies ORDER BY name");

superadminHead('Mudanças de Plano', 'plan-changes.php');
?>
<div class="sa-wrap">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-arrow-right-arrow-left" style="color:var(--yellow)"></i> Mudanças de Plano</h1>
            <div class="sub"><?= $total ?> evento<?= $total !== 1 ? 's' : '' ?> registrado<?= $total !== 1 ? 's' : '' ?></div>
        </div>
    </div>

    <!-- Estado atual -->
    <div class="stat-cards">
        <div class="stat-card">
            <div class="sc-label">Pro ativos agora</div>
            <div class="sc-val" style="color:#86efac"><?= $countProActive ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Em atraso (carência)</div>
            <div class="sc-val" style="color:<?= $countOverdueNow > 0 ? '#fca5a5' : 'var(--gray-400)' ?>"><?= $countOverdueNow ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Aguardando aprovação</div>
            <div class="sc-val" style="color:<?= $countPending > 0 ? '#fcd34d' : 'var(--gray-400)' ?>"><?= $countPending ?></div>
            <?php if ($countPending > 0): ?>
            <div class="sc-sub"><a href="companies.php?status=pending_payment" style="color:#7dd3fc">ver empresas →</a></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:#1a2d45;padding:16px;border-radius:var(--radius)">
        <select name="type" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todos os tipos</option>
            <?php foreach ($typeLabels as $k => $t): ?>
            <option value="<?= $k ?>" <?= $typeFlt===$k?'selected':'' ?>><?= $t['label'] ?></option>
            <?php endforeach; ?>
        </select>
        <select name="company" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todas as empresas</option>
            <?php foreach ($companies as $co): ?>
            <option value="<?= $co['id'] ?>" <?= $companyFlt===(int)$co['id']?'selected':'' ?>><?= htmlspecialchars($co['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-size:13px">
            <i class="fa-solid fa-magnifying-glass"></i> Filtrar
        </button>
        <?php if ($typeFlt || $companyFlt): ?>
        <a href="plan-changes.php" class="btn" style="background:var(--gray-200);color:var(--gray-700);font-size:13px">
            <i class="fa-solid fa-xmark"></i> Limpar
        </a>
        <?php endif; ?>
    </form>

    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <div style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Evento</th>
                    <th>Empresa</th>
                    <th>Plano atual</th>
                    <th>Detalhe</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400)">
                Nenhum evento encontrado.
            </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <?php $t = $typeLabels[$r['event_type']] ?? ['bg'=>'#f3f4f6','col'=>'#6b7280','icon'=>'fa-circle','label'=>$r['event_type']]; ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap;color:var(--gray-500)"><?= $r['created_at'] ?></td>
                <td>
                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:<?= $t['bg'] ?>;color:<?= $t['col'] ?>">
                        <i class="fa-solid <?= $t['icon'] ?>"></i> <?= htmlspecialchars($t['label']) ?>
                    </span>
                </td>
                <td style="font-size:13px"><?= htmlspecialchars($r['company_name'] ?? '—') ?></td>
                <td style="font-size:12px;color:var(--gray-400)">
                    <?= $r['company_plan'] === 'pro' ? '<span style="color:#86efac">Pro</span>' : 'Free' ?>
                </td>
                <td style="font-size:12px;color:var(--gray-500);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($r['detail'] ?? '') ?>">
                    <?= htmlspecialchars($r['detail'] ?? '') ?: '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid var(--gray-100);display:flex;gap:8px;align-items:center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= http_build_query(['type'=>$typeFlt,'company'=>$companyFlt,'p'=>$i]) ?>"
               style="padding:4px 10px;border-radius:6px;font-size:13px;text-decoration:none;
                      background:<?= $i===$page?'var(--pacific)':'var(--gray-100)' ?>;
                      color:<?= $i===$page?'#fff':'var(--gray-600)' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php superadminFoot(); ?>
