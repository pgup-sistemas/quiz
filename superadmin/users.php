<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

$search  = trim($_GET['q']       ?? '');
$coFlt   = (int)($_GET['company'] ?? 0);
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 40;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.sector LIKE ?)";
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like]);
}
if ($coFlt) {
    $where[] = "u.company_id=?";
    $params[] = $coFlt;
}
$whereSql = implode(' AND ', $where);

$total = (int)dbRow("SELECT COUNT(*) AS c FROM users u WHERE $whereSql", $params)['c'];
$users = dbRows(
    "SELECT u.id, u.name, u.email, u.sector, u.created_at, u.company_id,
            c.name AS company_name, c.plan AS company_plan,
            (SELECT COUNT(*) FROM participants p WHERE p.user_id=u.id) AS quiz_count,
            (SELECT MAX(p2.finished_at) FROM participants p2 WHERE p2.user_id=u.id AND p2.finished_at IS NOT NULL) AS last_activity
     FROM users u
     LEFT JOIN companies c ON c.id=u.company_id
     WHERE $whereSql
     ORDER BY u.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);
$totalPages = (int)ceil($total / $perPage);

$totalUsers    = (int)dbRow("SELECT COUNT(*) AS c FROM users")['c'];
$activeUsers30 = (int)dbRow(
    "SELECT COUNT(DISTINCT user_id) AS c FROM participants
     WHERE finished_at >= datetime('now','localtime','-30 days')"
)['c'];
$companies = dbRows("SELECT id, name FROM companies ORDER BY name");

superadminHead('Colaboradores', '');
?>
<div class="sa-wrap">

    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-users" style="color:var(--yellow)"></i> Colaboradores</h1>
            <div class="sub">Busca cross-tenant em todos os <?= $totalUsers ?> colaboradores cadastrados</div>
        </div>
    </div>

    <div class="stat-cards">
        <div class="stat-card">
            <div class="sc-label">Total de colaboradores</div>
            <div class="sc-val"><?= $totalUsers ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Ativos (últimos 30d)</div>
            <div class="sc-val" style="color:#166534"><?= $activeUsers30 ?></div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Resultado da busca</div>
            <div class="sc-val" style="color:var(--pacific)"><?= $total ?></div>
            <div class="sc-sub"><?= $search || $coFlt ? 'filtrado' : 'todos' ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:#1a2d45;padding:16px;border-radius:var(--radius)">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Buscar por nome, e-mail ou setor…"
               style="flex:1;min-width:220px;padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px"/>
        <select name="company" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todas as empresas</option>
            <?php foreach ($companies as $co): ?>
            <option value="<?= $co['id'] ?>" <?= $coFlt==$co['id']?'selected':'' ?>><?= htmlspecialchars($co['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-size:13px">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
        <?php if ($search||$coFlt): ?>
        <a href="users.php" class="btn" style="background:var(--gray-200);color:var(--gray-700);font-size:13px">
            <i class="fa-solid fa-xmark"></i> Limpar
        </a>
        <?php endif; ?>
    </form>

    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <div style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Empresa</th>
                    <th>Setor</th>
                    <th>Quizzes</th>
                    <th>Última atividade</th>
                    <th>Cadastro</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400)">
                <?= $search||$coFlt ? 'Nenhum colaborador encontrado com esses filtros.' : 'Nenhum colaborador cadastrado.' ?>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="font-weight:600;color:var(--prussian)"><?= htmlspecialchars($u['name']) ?></td>
                <td style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <a href="company-detail.php?id=<?= (int)$u['company_id'] ?>"
                       style="font-size:13px;color:var(--pacific);font-weight:600;text-decoration:none">
                        <?= htmlspecialchars($u['company_name'] ?? '—') ?>
                    </a>
                    <?php if ($u['company_plan'] === 'pro'): ?>
                    <span class="badge-plan badge-pro" style="margin-left:4px">Pro</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($u['sector'] ?: '—') ?></td>
                <td style="font-variant-numeric:tabular-nums;text-align:center"><?= (int)$u['quiz_count'] ?></td>
                <td style="font-size:12px;color:var(--gray-400)">
                    <?= $u['last_activity'] ? date('d/m/Y', strtotime($u['last_activity'])) : '<span style="color:var(--gray-300)">—</span>' ?>
                </td>
                <td style="font-size:12px;color:var(--gray-400)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid var(--gray-100);display:flex;gap:8px;flex-wrap:wrap">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
            <a href="?<?= http_build_query(['q'=>$search,'company'=>$coFlt,'p'=>$i]) ?>"
               style="padding:4px 10px;border-radius:6px;font-size:13px;text-decoration:none;
                      background:<?= $i===$page?'var(--pacific)':'var(--gray-100)' ?>;
                      color:<?= $i===$page?'#fff':'var(--gray-600)' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php superadminFoot(); ?>
