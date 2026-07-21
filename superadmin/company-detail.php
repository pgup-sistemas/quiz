<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

$id      = (int)($_GET['id'] ?? 0);
$company = $id ? dbRow("SELECT * FROM companies WHERE id=?", [$id]) : null;
if (!$company) { header('Location: companies.php'); exit; }

// ── Dados consolidados ─────────────────────────────────────────────
$admins = dbRows("SELECT id, username, name, created_at FROM admins WHERE company_id=? ORDER BY id ASC", [$id]);

$quizStats = dbRow(
    "SELECT COUNT(*) AS total,
            SUM(active=1) AS active_q,
            SUM(active=0) AS inactive_q
     FROM quizzes WHERE company_id=?", [$id]
);

$quizzes = dbRows(
    "SELECT id, title, sector, active, created_at,
            (SELECT COUNT(*) FROM participants p WHERE p.quiz_id=quizzes.id) AS participants
     FROM quizzes WHERE company_id=? ORDER BY created_at DESC LIMIT 20", [$id]
);

$userStats = dbRow(
    "SELECT COUNT(*) AS total,
            MAX(created_at) AS last_reg
     FROM users WHERE company_id=?", [$id]
);

$recentUsers = dbRows(
    "SELECT id, name, email, sector, created_at FROM users WHERE company_id=?
     ORDER BY created_at DESC LIMIT 10", [$id]
);

$payments = dbRows(
    "SELECT type, status, amount, created_at FROM subscriptions WHERE company_id=?
     ORDER BY created_at DESC LIMIT 10", [$id]
);

$auditLogs = dbRows(
    "SELECT al.action, al.detail, al.created_at, al.ip, sa.name AS actor
     FROM audit_log al
     LEFT JOIN super_admins sa ON sa.id=al.actor_id
     WHERE al.target_company_id=?
     ORDER BY al.created_at DESC LIMIT 15", [$id]
);

$planLabel  = ['free'=>'Free','pro'=>'Pro'][$company['plan']] ?? $company['plan'];
$planClass  = $company['plan'] === 'pro' ? 'badge-pro' : 'badge-free';
$statusLabels = [
    'active'          => ['Ativa',    'badge-active'],
    'suspended'       => ['Suspensa', 'badge-suspended'],
    'pending_payment' => ['Pendente', 'badge-pending'],
];
[$statusLabel, $statusClass] = $statusLabels[$company['status']] ?? [$company['status'], 'badge-free'];

$typeLabels = [
    'pix'          => 'PIX',
    'card_once'    => 'Cartão único',
    'card_recurring'=> 'Assinatura',
    'payment_link' => 'Link',
    'manual'       => 'Manual',
];
$subStatusLabels = [
    'pending'  => ['Aguardando','#92400e','#fef3c7'],
    'active'   => ['Ativo',     '#166534','#dcfce7'],
    'paid'     => ['Pago',      '#166534','#dcfce7'],
    'overdue'  => ['Inadim.',   '#991b1b','#fee2e2'],
    'cancelled'=> ['Cancelado', '#6b7280','#f3f4f6'],
];

$actionBadge = [
    'login'               => ['#dbeafe','#1e40af','Login'],
    'impersonate'         => ['#e9d5ff','#6b21a8','Impersonation'],
    'suspend'             => ['#fee2e2','#991b1b','Suspensão'],
    'activate'            => ['#dcfce7','#166534','Reativação'],
    'approve_pro'         => ['#fef3c7','#92400e','Ativar Pro'],
    'downgrade'           => ['#ffedd5','#9a3412','Rebaixamento'],
    'edit_company'        => ['#e0f2fe','#0369a1','Edição'],
    'create_company'      => ['#dcfce7','#166534','Nova empresa'],
    'reset_admin_password'=> ['#fef9c3','#854d0e','Reset senha'],
    'auto_downgrade'      => ['#ffedd5','#9a3412','Auto-downgrade'],
];

superadminHead('Empresa: ' . $company['name'], 'companies.php');
?>
<style>
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
@media(max-width:700px){ .detail-grid { grid-template-columns:1fr; } }
.section-card { background:#fff; border-radius:var(--radius); box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:20px; overflow:hidden; }
.section-card-head { padding:16px 20px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; justify-content:space-between; }
.section-card-head h3 { font-size:13px; font-weight:700; color:var(--gray-700); margin:0; text-transform:uppercase; letter-spacing:.5px; }
.section-card-body { padding:0; }
.mini-tbl { width:100%; border-collapse:collapse; }
.mini-tbl th { font-size:11px; font-weight:700; color:var(--gray-400); text-transform:uppercase; padding:8px 16px; border-bottom:1px solid var(--gray-100); background:var(--gray-50); text-align:left; }
.mini-tbl td { padding:9px 16px; border-bottom:1px solid var(--gray-100); font-size:13px; vertical-align:middle; }
.mini-tbl tr:last-child td { border-bottom:none; }
.mini-tbl tr:hover td { background:var(--gray-50); }
.stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:0; border-bottom:1px solid var(--gray-100); }
.stat-cell { padding:20px 16px; text-align:center; border-right:1px solid var(--gray-100); }
.stat-cell:last-child { border-right:none; }
.stat-cell .num { font-size:28px; font-weight:700; color:var(--prussian); line-height:1; }
.stat-cell .lbl { font-size:11px; color:var(--gray-400); margin-top:4px; text-transform:uppercase; letter-spacing:.5px; }
@media(max-width:600px){ .stat-row { grid-template-columns:1fr 1fr; } }
</style>

<div class="sa-wrap">

    <!-- Header da empresa -->
    <div class="page-header">
        <div>
            <div style="font-size:12px;color:var(--gray-400);margin-bottom:4px">
                <a href="companies.php" style="color:var(--gray-400)">← Empresas</a>
            </div>
            <h1>
                <i class="fa-solid fa-building" style="color:var(--yellow)"></i>
                <?= htmlspecialchars($company['name']) ?>
                <span class="badge-plan <?= $planClass ?>" style="font-size:12px;margin-left:8px"><?= $planLabel ?></span>
                <span class="badge-plan <?= $statusClass ?>" style="font-size:12px;margin-left:4px"><?= $statusLabel ?></span>
            </h1>
            <div class="sub">
                Slug: <code style="background:rgba(255,255,255,.1);padding:1px 6px;border-radius:4px"><?= htmlspecialchars($company['slug']) ?></code>
                &nbsp;·&nbsp; <?= htmlspecialchars($company['email']) ?>
                <?php if ($company['cnpj']): ?>
                &nbsp;·&nbsp; <?= htmlspecialchars($company['cnpj']) ?>
                <?php endif; ?>
                &nbsp;·&nbsp; Desde <?= date('d/m/Y', strtotime($company['created_at'])) ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="company-edit.php?id=<?= $id ?>" class="btn btn-xs ghost" style="font-size:13px;padding:7px 14px">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="impersonate.php?company_id=<?= $id ?>" class="btn btn-xs" style="background:#e9d5ff;color:#6b21a8;font-size:13px;padding:7px 14px">
                <i class="fa-solid fa-user-secret"></i> Impersonar
            </a>
            <?php if ($company['status'] === 'active'): ?>
            <a href="companies.php?action=suspend&id=<?= $id ?>" onclick="return confirm('Suspender esta empresa?')"
               class="btn btn-xs danger" style="font-size:13px;padding:7px 14px">
                <i class="fa-solid fa-ban"></i> Suspender
            </a>
            <?php else: ?>
            <a href="companies.php?action=activate&id=<?= $id ?>"
               class="btn btn-xs success" style="font-size:13px;padding:7px 14px">
                <i class="fa-solid fa-circle-check"></i> Reativar
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="section-card" style="margin-bottom:20px">
        <div class="stat-row">
            <div class="stat-cell">
                <div class="num"><?= (int)$quizStats['total'] ?></div>
                <div class="lbl">Quizzes</div>
            </div>
            <div class="stat-cell">
                <div class="num" style="color:#166534"><?= (int)$quizStats['active_q'] ?></div>
                <div class="lbl">Ativos</div>
            </div>
            <div class="stat-cell">
                <div class="num"><?= (int)$userStats['total'] ?></div>
                <div class="lbl">Colaboradores</div>
            </div>
            <div class="stat-cell">
                <div class="num"><?= count($admins) ?></div>
                <div class="lbl">Admins</div>
            </div>
        </div>
    </div>

    <div class="detail-grid">

        <!-- Admins -->
        <div class="section-card">
            <div class="section-card-head">
                <h3><i class="fa-solid fa-user-tie" style="color:var(--pacific)"></i> &nbsp;Administradores</h3>
                <a href="company-edit.php?id=<?= $id ?>" class="btn btn-xs ghost" style="font-size:11px">
                    <i class="fa-solid fa-key"></i> Reset senha
                </a>
            </div>
            <div class="section-card-body">
                <?php if (empty($admins)): ?>
                <div style="padding:24px;text-align:center;color:var(--gray-400);font-size:13px">Nenhum admin cadastrado.</div>
                <?php else: ?>
                <table class="mini-tbl">
                    <thead><tr><th>Nome</th><th>E-mail / login</th><th>Desde</th></tr></thead>
                    <tbody>
                    <?php foreach ($admins as $a): ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($a['name']) ?></td>
                        <td style="color:var(--gray-500)"><?= htmlspecialchars($a['username']) ?></td>
                        <td style="color:var(--gray-400);font-size:12px"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagamentos -->
        <div class="section-card">
            <div class="section-card-head">
                <h3><i class="fa-solid fa-money-bill-wave" style="color:var(--pacific)"></i> &nbsp;Pagamentos recentes</h3>
                <a href="payment-link.php?company_id=<?= $id ?>" class="btn btn-xs primary" style="font-size:11px">
                    <i class="fa-solid fa-link"></i> Gerar link
                </a>
            </div>
            <div class="section-card-body">
                <?php if (empty($payments)): ?>
                <div style="padding:24px;text-align:center;color:var(--gray-400);font-size:13px">Nenhum pagamento registrado.</div>
                <?php else: ?>
                <table class="mini-tbl">
                    <thead><tr><th>Data</th><th>Método</th><th>Valor</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                    <?php [$sl,$sc,$sb] = $subStatusLabels[$p['status']] ?? [$p['status'],'#6b7280','#f3f4f6']; ?>
                    <tr>
                        <td style="font-size:12px;color:var(--gray-400)"><?= substr($p['created_at'],0,10) ?></td>
                        <td><?= htmlspecialchars($typeLabels[$p['type']] ?? $p['type']) ?></td>
                        <td style="font-weight:600">R$&nbsp;<?= number_format(($p['amount']??0)/100,2,',','.') ?></td>
                        <td><span style="padding:2px 7px;border-radius:20px;font-size:10px;font-weight:700;background:<?= $sb ?>;color:<?= $sc ?>"><?= htmlspecialchars($sl) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quizzes -->
    <div class="section-card">
        <div class="section-card-head">
            <h3><i class="fa-solid fa-list-check" style="color:var(--pacific)"></i> &nbsp;Quizzes (<?= (int)$quizStats['total'] ?>)</h3>
        </div>
        <div class="section-card-body" style="overflow-x:auto">
            <?php if (empty($quizzes)): ?>
            <div style="padding:24px;text-align:center;color:var(--gray-400);font-size:13px">Nenhum quiz criado.</div>
            <?php else: ?>
            <table class="mini-tbl">
                <thead><tr><th>Título</th><th>Setor</th><th>Participantes</th><th>Status</th><th>Criado em</th></tr></thead>
                <tbody>
                <?php foreach ($quizzes as $q): ?>
                <tr>
                    <td style="font-weight:600;max-width:220px"><?= htmlspecialchars($q['title']) ?></td>
                    <td style="color:var(--gray-500)"><?= htmlspecialchars($q['sector'] ?: '—') ?></td>
                    <td style="font-variant-numeric:tabular-nums"><?= (int)$q['participants'] ?></td>
                    <td>
                        <?php if ($q['active']): ?>
                        <span style="padding:2px 7px;border-radius:20px;font-size:10px;font-weight:700;background:#dcfce7;color:#166534">Ativo</span>
                        <?php else: ?>
                        <span style="padding:2px 7px;border-radius:20px;font-size:10px;font-weight:700;background:#fee2e2;color:#991b1b">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--gray-400)"><?= date('d/m/Y', strtotime($q['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ((int)$quizStats['total'] > 20): ?>
            <div style="padding:10px 16px;font-size:12px;color:var(--gray-400)">Exibindo 20 de <?= (int)$quizStats['total'] ?> quizzes.</div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-grid">

        <!-- Colaboradores recentes -->
        <div class="section-card">
            <div class="section-card-head">
                <h3><i class="fa-solid fa-users" style="color:var(--pacific)"></i> &nbsp;Colaboradores recentes</h3>
                <span style="font-size:12px;color:var(--gray-400)"><?= (int)$userStats['total'] ?> total</span>
            </div>
            <div class="section-card-body">
                <?php if (empty($recentUsers)): ?>
                <div style="padding:24px;text-align:center;color:var(--gray-400);font-size:13px">Nenhum colaborador cadastrado.</div>
                <?php else: ?>
                <table class="mini-tbl">
                    <thead><tr><th>Nome</th><th>E-mail</th><th>Setor</th><th>Cadastro</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td style="font-weight:600"><?= htmlspecialchars($u['name']) ?></td>
                        <td style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($u['email']) ?></td>
                        <td style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($u['sector'] ?: '—') ?></td>
                        <td style="font-size:11px;color:var(--gray-400)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Auditoria -->
        <div class="section-card">
            <div class="section-card-head">
                <h3><i class="fa-solid fa-shield-halved" style="color:var(--pacific)"></i> &nbsp;Auditoria recente</h3>
                <a href="audit.php?company=<?= $id ?>" class="btn btn-xs ghost" style="font-size:11px">Ver tudo</a>
            </div>
            <div class="section-card-body">
                <?php if (empty($auditLogs)): ?>
                <div style="padding:24px;text-align:center;color:var(--gray-400);font-size:13px">Nenhum evento registrado.</div>
                <?php else: ?>
                <table class="mini-tbl">
                    <thead><tr><th>Data</th><th>Ação</th><th>Ator</th></tr></thead>
                    <tbody>
                    <?php foreach ($auditLogs as $l): ?>
                    <?php [$bg,$col,$lbl] = $actionBadge[$l['action']] ?? ['#f3f4f6','#6b7280',$l['action']]; ?>
                    <tr>
                        <td style="font-size:11px;color:var(--gray-400);white-space:nowrap"><?= substr($l['created_at'],0,16) ?></td>
                        <td><span style="padding:2px 7px;border-radius:20px;font-size:10px;font-weight:700;background:<?= $bg ?>;color:<?= $col ?>"><?= htmlspecialchars($lbl) ?></span></td>
                        <td style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($l['actor'] ?: 'Sistema') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?php superadminFoot(); ?>
