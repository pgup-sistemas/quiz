<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

// Stats
$totalCompanies  = (int)dbRow("SELECT COUNT(*) AS c FROM companies")['c'];
$totalFree       = (int)dbRow("SELECT COUNT(*) AS c FROM companies WHERE plan='free' AND status='active'")['c'];
$totalPro        = (int)dbRow("SELECT COUNT(*) AS c FROM companies WHERE plan='pro'")['c'];
$totalPending    = (int)dbRow("SELECT COUNT(*) AS c FROM companies WHERE status='pending_payment'")['c'];
$totalQuizzes    = (int)dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE active=1")['c'];
$totalUsers      = (int)dbRow("SELECT COUNT(*) AS c FROM users")['c'];

// Empresas recentes
$recentes = dbRows("SELECT c.*,
    (SELECT COUNT(*) FROM quizzes q WHERE q.company_id=c.id AND q.active=1) AS quiz_count,
    (SELECT COUNT(*) FROM users u WHERE u.company_id=c.id) AS user_count
    FROM companies c ORDER BY c.created_at DESC LIMIT 10");

superadminHead('Dashboard', 'index.php');
?>
<div class="sa-wrap">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-table-columns" style="color:var(--yellow)"></i> Dashboard</h1>
            <div class="sub">Visão geral de todas as empresas</div>
        </div>
        <a href="companies.php?status=pending_payment" class="btn btn-sm" style="background:var(--yellow);color:#023047;font-weight:700">
            <?php if ($totalPending > 0): ?>
            <i class="fa-solid fa-bell"></i> <?= $totalPending ?> Pro Solicitado<?= $totalPending > 1 ? 's' : '' ?>
            <?php else: ?>
            <i class="fa-solid fa-building-circle-check"></i> Nenhum pedido pendente
            <?php endif; ?>
        </a>
    </div>

    <div class="stat-cards">
        <div class="stat-card">
            <div class="sc-label">Total de Empresas</div>
            <div class="sc-val"><?= $totalCompanies ?></div>
            <div class="sc-sub">cadastradas</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Plano Free</div>
            <div class="sc-val" style="color:var(--pacific)"><?= $totalFree ?></div>
            <div class="sc-sub">ativas</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Plano Pro</div>
            <div class="sc-val" style="color:#92400e"><?= $totalPro ?></div>
            <div class="sc-sub">ativas</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Pro Solicitados</div>
            <div class="sc-val" style="color:var(--orange)"><?= $totalPending ?></div>
            <div class="sc-sub">aguardando ativação</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Quizzes Ativos</div>
            <div class="sc-val"><?= $totalQuizzes ?></div>
            <div class="sc-sub">em todas as empresas</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Usuários</div>
            <div class="sc-val"><?= $totalUsers ?></div>
            <div class="sc-sub">participantes cadastrados</div>
        </div>
    </div>

    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between">
            <strong style="color:var(--prussian)"><i class="fa-solid fa-building"></i> Empresas Recentes</strong>
            <a href="companies.php" class="btn btn-sm" style="font-size:13px">Ver todas <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Plano</th>
                    <th>Status</th>
                    <th>Quizzes</th>
                    <th>Usuários</th>
                    <th>Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentes as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:600;color:var(--prussian)"><?= htmlspecialchars($c['name']) ?></div>
                    <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($c['slug']) ?></div>
                </td>
                <td>
                    <?php if ($c['status'] === 'pending_payment'): ?>
                    <span class="badge-plan badge-pending"><i class="fa-solid fa-hourglass-half"></i> Pro Solicitado</span>
                    <?php elseif ($c['plan'] === 'pro'): ?>
                    <span class="badge-plan badge-pro">Pro</span>
                    <?php else: ?>
                    <span class="badge-plan badge-free">Free</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['status'] === 'suspended'): ?>
                    <span class="badge-plan badge-suspended">Suspensa</span>
                    <?php elseif ($c['status'] === 'pending_payment'): ?>
                    <span class="badge-plan badge-pending">Pendente</span>
                    <?php else: ?>
                    <span class="badge-plan badge-active">Ativa</span>
                    <?php endif; ?>
                </td>
                <td><?= $c['quiz_count'] ?></td>
                <td><?= $c['user_count'] ?></td>
                <td style="color:var(--gray-500);font-size:12px"><?= substr($c['created_at'], 0, 10) ?></td>
                <td>
                    <div class="actions">
                        <a href="company-edit.php?id=<?= $c['id'] ?>" class="btn-xs ghost" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <a href="impersonate.php?company_id=<?= $c['id'] ?>" class="btn-xs primary" title="Entrar como admin"><i class="fa-solid fa-user-secret"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php superadminFoot(); ?>
