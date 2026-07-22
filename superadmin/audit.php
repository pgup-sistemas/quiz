<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

$actionFlt  = trim($_GET['action']  ?? '');
$companyFlt = (int)($_GET['company'] ?? 0);
$page       = max(1, (int)($_GET['p'] ?? 1));
$perPage    = 30;
$offset     = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($actionFlt)  { $where[] = "al.action = ?";           $params[] = $actionFlt; }
if ($companyFlt) { $where[] = "al.target_company_id = ?"; $params[] = $companyFlt; }
$whereSql = implode(' AND ', $where);

$total = (int)dbRow("SELECT COUNT(*) AS c FROM audit_log al WHERE $whereSql", $params)['c'];
$logs  = dbRows(
    "SELECT al.*, c.name AS company_name, sa.name AS actor_name
     FROM audit_log al
     LEFT JOIN companies c ON c.id = al.target_company_id
     LEFT JOIN super_admins sa ON sa.id = al.actor_id
     WHERE $whereSql
     ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$totalPages = (int)ceil($total / $perPage);

$actionBadge = [
    'login'            => ['#dbeafe', '#1e40af', 'Login'],
    'impersonate'      => ['rgba(168,85,247,.18)', '#d8b4fe', 'Impersonation'],
    'suspend'          => ['rgba(239,68,68,.15)', '#fca5a5',  'Suspensão'],
    'activate'         => ['rgba(34,197,94,.15)', '#86efac',  'Reativação'],
    'approve_pro'      => ['rgba(251,191,36,.15)', '#fcd34d',  'Ativar Pro'],
    'downgrade'        => ['#ffedd5', '#9a3412',  'Rebaixamento'],
    'edit_company'     => ['rgba(33,158,188,.18)', '#7dd3fc',  'Edição'],
    'create_company'   => ['rgba(34,197,94,.15)', '#86efac',  'Nova empresa'],
    'update_settings'  => ['#f3e8ff', '#d8b4fe',  'Config'],
];

$companies = dbRows("SELECT id, name FROM companies ORDER BY name");

superadminHead('Auditoria', 'audit.php');
?>
<div class="sa-wrap">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-shield-halved" style="color:var(--yellow)"></i> Auditoria</h1>
            <div class="sub"><?= $total ?> evento<?= $total !== 1 ? 's' : '' ?> registrado<?= $total !== 1 ? 's' : '' ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:#1a2d45;padding:16px;border-radius:var(--radius)">
        <select name="action" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todas as ações</option>
            <?php foreach ($actionBadge as $k => [$bg, $col, $label]): ?>
            <option value="<?= $k ?>" <?= $actionFlt===$k?'selected':'' ?>><?= $label ?></option>
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
        <?php if ($actionFlt || $companyFlt): ?>
        <a href="audit.php" class="btn" style="background:var(--gray-200);color:var(--gray-700);font-size:13px">
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
                    <th>Ator</th>
                    <th>Ação</th>
                    <th>Empresa alvo</th>
                    <th>IP</th>
                    <th>Detalhe</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gray-400)">
                Nenhum evento encontrado.
            </td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
            <?php [$bg, $col, $label] = $actionBadge[$l['action']] ?? ['#f3f4f6', '#6b7280', $l['action']]; ?>
            <tr>
                <td style="font-size:12px;white-space:nowrap;color:var(--gray-500)"><?= $l['created_at'] ?></td>
                <td style="font-size:13px"><?= htmlspecialchars($l['actor_name'] ?: 'Sistema') ?></td>
                <td>
                    <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:<?= $bg ?>;color:<?= $col ?>">
                        <?= htmlspecialchars($label) ?>
                    </span>
                </td>
                <td style="font-size:13px"><?= htmlspecialchars($l['company_name'] ?? '—') ?></td>
                <td style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($l['ip']) ?></td>
                <td style="font-size:12px;color:var(--gray-500);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($l['detail']) ?>">
                    <?= htmlspecialchars($l['detail']) ?: '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid var(--gray-100);display:flex;gap:8px;align-items:center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= http_build_query(['action'=>$actionFlt,'company'=>$companyFlt,'p'=>$i]) ?>"
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
