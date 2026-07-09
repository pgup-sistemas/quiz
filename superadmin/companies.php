<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

// Filtros
$search    = trim($_GET['q']      ?? '');
$planFlt   = trim($_GET['plan']   ?? '');
$statusFlt = trim($_GET['status'] ?? '');
$page      = max(1, (int)($_GET['p'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(c.name LIKE ? OR c.slug LIKE ? OR c.email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($planFlt)   { $where[] = "c.plan = ?";   $params[] = $planFlt; }
if ($statusFlt) { $where[] = "c.status = ?"; $params[] = $statusFlt; }
$whereSql = implode(' AND ', $where);

$total   = (int)dbRow("SELECT COUNT(*) AS c FROM companies c WHERE $whereSql", $params)['c'];
$companies = dbRows(
    "SELECT c.*,
        (SELECT COUNT(*) FROM quizzes q WHERE q.company_id=c.id AND q.active=1) AS quiz_count,
        (SELECT COUNT(*) FROM users u WHERE u.company_id=c.id) AS user_count
     FROM companies c WHERE $whereSql ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$totalPages = (int)ceil($total / $perPage);

// Ações POST
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $cid = (int)($_POST['company_id'] ?? 0);

    if ($act === 'suspend') {
        dbExec("UPDATE companies SET status='suspended', updated_at=datetime('now','localtime') WHERE id=?", [$cid]);
        logAudit('suspend', $cid);
        $msg = 'Empresa suspensa.';
    } elseif ($act === 'activate') {
        dbExec("UPDATE companies SET status='active', updated_at=datetime('now','localtime') WHERE id=?", [$cid]);
        logAudit('activate', $cid);
        $msg = 'Empresa reativada.';
    } elseif ($act === 'approve_pro') {
        $co = dbRow("SELECT * FROM companies WHERE id=?", [$cid]);
        if ($co) {
            dbExec("UPDATE companies SET plan='pro', status='active', updated_at=datetime('now','localtime') WHERE id=?", [$cid]);
            logAudit('approve_pro', $cid, json_encode(['prev_status' => $co['status']]));
            $msg = 'Plano Pro ativado com sucesso!';
        }
    } elseif ($act === 'downgrade_free') {
        $freeLimit = (int)(dbRow("SELECT value FROM system_settings WHERE key='free_quiz_limit'")['value'] ?? 12);
        $quizzesAtivos = dbRows("SELECT id FROM quizzes WHERE company_id=? AND active=1 ORDER BY created_at ASC", [$cid]);
        $excess = array_slice($quizzesAtivos, $freeLimit);
        $inactivatedIds = [];
        foreach ($excess as $q) {
            dbExec("UPDATE quizzes SET active=0 WHERE id=?", [$q['id']]);
            $inactivatedIds[] = $q['id'];
        }
        dbExec("UPDATE companies SET plan='free', status='active', updated_at=datetime('now','localtime') WHERE id=?", [$cid]);
        logAudit('downgrade', $cid, json_encode(['inactivated_ids' => $inactivatedIds, 'limit' => $freeLimit]));
        $msg = 'Plano rebaixado para Free. ' . count($inactivatedIds) . ' quizze(s) inativado(s).';
    }
    header('Location: companies.php?' . http_build_query(['q' => $search, 'plan' => $planFlt, 'status' => $statusFlt, 'p' => $page, '_msg' => urlencode($msg)]));
    exit;
}

if (isset($_GET['_msg'])) $msg = $_GET['_msg'];

$freeLimit = (int)(dbRow("SELECT value FROM system_settings WHERE key='free_quiz_limit'")['value'] ?? 12);

superadminHead('Empresas', 'companies.php');
?>
<div class="sa-wrap">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-building" style="color:var(--yellow)"></i> Empresas</h1>
            <div class="sub"><?= $total ?> empresa<?= $total !== 1 ? 's' : '' ?> encontrada<?= $total !== 1 ? 's' : '' ?></div>
        </div>
        <a href="company-edit.php" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
            <i class="fa-solid fa-plus"></i> Nova Empresa
        </a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:16px">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:#1a2d45;padding:16px;border-radius:var(--radius)">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por nome, slug ou e-mail…"
               style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px"/>
        <select name="plan" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todos os planos</option>
            <option value="free" <?= $planFlt==='free'?'selected':'' ?>>Free</option>
            <option value="pro"  <?= $planFlt==='pro'?'selected':'' ?>>Pro</option>
        </select>
        <select name="status" style="padding:8px 12px;border:1px solid #2d4a6a;border-radius:6px;background:#0f1f35;color:#e2e8f0;font-size:13px">
            <option value="">Todos os status</option>
            <option value="active"          <?= $statusFlt==='active'?'selected':'' ?>>Ativa</option>
            <option value="pending_payment" <?= $statusFlt==='pending_payment'?'selected':'' ?>>Pro Solicitado</option>
            <option value="suspended"       <?= $statusFlt==='suspended'?'selected':'' ?>>Suspensa</option>
        </select>
        <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-size:13px">
            <i class="fa-solid fa-magnifying-glass"></i> Filtrar
        </button>
        <?php if ($search || $planFlt || $statusFlt): ?>
        <a href="companies.php" class="btn" style="background:var(--gray-200);color:var(--gray-700);font-size:13px">
            <i class="fa-solid fa-xmark"></i> Limpar
        </a>
        <?php endif; ?>
    </form>

    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08)">
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
            <?php if (empty($companies)): ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400)">
                <i class="fa-solid fa-building" style="font-size:32px;margin-bottom:8px;display:block;opacity:.3"></i>
                Nenhuma empresa encontrada.
            </td></tr>
            <?php endif; ?>
            <?php foreach ($companies as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:600;color:var(--prussian)"><?= htmlspecialchars($c['name']) ?></div>
                    <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($c['slug']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($c['email']) ?></div>
                </td>
                <td>
                    <?php if ($c['status'] === 'pending_payment'): ?>
                    <span class="badge-plan badge-pending">⏳ Pro Solicitado</span>
                    <?php elseif ($c['plan'] === 'pro'): ?>
                    <span class="badge-plan badge-pro"><i class="fa-solid fa-star"></i> Pro</span>
                    <?php else: ?>
                    <span class="badge-plan badge-free">Free</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['status'] === 'suspended'): ?>
                    <span class="badge-plan badge-suspended"><i class="fa-solid fa-ban"></i> Suspensa</span>
                    <?php elseif ($c['status'] === 'pending_payment'): ?>
                    <span class="badge-plan badge-pending">Pendente</span>
                    <?php else: ?>
                    <span class="badge-plan badge-active"><i class="fa-solid fa-circle-check"></i> Ativa</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['plan'] === 'free' && $c['status'] !== 'pending_payment'): ?>
                        <?= $c['quiz_count'] ?>/<?= $freeLimit ?>
                    <?php else: ?>
                        <?= $c['quiz_count'] ?>
                    <?php endif; ?>
                </td>
                <td><?= $c['user_count'] ?></td>
                <td style="color:var(--gray-500);font-size:12px"><?= substr($c['created_at'], 0, 10) ?></td>
                <td>
                    <div class="actions">
                        <a href="company-edit.php?id=<?= $c['id'] ?>" class="btn-xs ghost" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <a href="impersonate.php?company_id=<?= $c['id'] ?>" class="btn-xs primary" title="Entrar como admin"><i class="fa-solid fa-user-secret"></i></a>

                        <?php if ($c['status'] === 'pending_payment'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve_pro"/>
                            <input type="hidden" name="company_id" value="<?= $c['id'] ?>"/>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"/>
                            <button type="submit" class="btn-xs success" title="Ativar Pro"
                                    onclick="return confirm('Ativar plano Pro para <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
                                <i class="fa-solid fa-star"></i> Ativar Pro
                            </button>
                        </form>
                        <?php elseif ($c['plan'] === 'pro'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="downgrade_free"/>
                            <input type="hidden" name="company_id" value="<?= $c['id'] ?>"/>
                            <button type="submit" class="btn-xs ghost" title="Rebaixar para Free"
                                    onclick="return confirm('Rebaixar para Free? Quizzes além do limite serão desativados.')">
                                <i class="fa-solid fa-arrow-down"></i> Free
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if ($c['status'] !== 'suspended'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="suspend"/>
                            <input type="hidden" name="company_id" value="<?= $c['id'] ?>"/>
                            <button type="submit" class="btn-xs danger" title="Suspender"
                                    onclick="return confirm('Suspender a empresa <?= htmlspecialchars(addslashes($c['name'])) ?>? Os acessos serão bloqueados imediatamente.')">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="activate"/>
                            <input type="hidden" name="company_id" value="<?= $c['id'] ?>"/>
                            <button type="submit" class="btn-xs success" title="Reativar"
                                    onclick="return confirm('Reativar empresa <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
                                <i class="fa-solid fa-circle-check"></i> Reativar
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid var(--gray-100);display:flex;gap:8px;align-items:center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?<?= http_build_query(['q'=>$search,'plan'=>$planFlt,'status'=>$statusFlt,'p'=>$i]) ?>"
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
