<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/layout.php';

function parseIds(string $raw): array {
    $ids = array_filter(array_map('intval', explode(',', $raw)));
    return array_values(array_unique($ids));
}

$error = '';
$ids   = parseIds($_GET['ids'] ?? $_POST['ids'] ?? '');

if (empty($ids)) { header('Location: companies.php'); exit; }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$companies    = dbRows("SELECT * FROM companies WHERE id IN ($placeholders) ORDER BY name", $ids);

if (empty($companies)) { header('Location: companies.php'); exit; }

// Reconcilia ids validos (podem ter sido excluidos por outra aba, etc.)
$validIds = array_map(fn($c) => (int)$c['id'], $companies);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $confirmWord = trim($_POST['confirm_word'] ?? '');

    if (strtoupper($confirmWord) !== 'EXCLUIR') {
        $error = 'Você precisa digitar exatamente "EXCLUIR" para confirmar. Nada foi apagado.';
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
            foreach ($validIds as $cid) {
                dbExec("DELETE FROM answers         WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM participants     WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM quizzes          WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM sectors          WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM users            WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM admins           WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM invites          WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM contact_messages WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM subscriptions    WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM payment_events   WHERE company_id=?", [$cid]);
                dbExec("DELETE FROM companies        WHERE id=?", [$cid]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Erro ao excluir em massa — nenhuma empresa foi apagada. ' . $e->getMessage();
        }

        if (!$error) {
            foreach ($companies as $c) {
                logAudit('delete_company', (int)$c['id'], json_encode(['name' => $c['name'], 'slug' => $c['slug'], 'bulk' => true]));
            }
            $n = count($companies);
            header('Location: companies.php?_msg=' . urlencode($n . ' empresa' . ($n > 1 ? 's' : '') . ' excluída' . ($n > 1 ? 's' : '') . ' permanentemente.'));
            exit;
        }
    }
}

// Contagens agregadas para exibir o impacto
$idsForCount = $validIds;
$ph          = implode(',', array_fill(0, count($idsForCount), '?'));
$counts = [
    'quizzes'       => (int)dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE company_id IN ($ph)", $idsForCount)['c'],
    'users'         => (int)dbRow("SELECT COUNT(*) AS c FROM users WHERE company_id IN ($ph)", $idsForCount)['c'],
    'participants'  => (int)dbRow("SELECT COUNT(*) AS c FROM participants WHERE company_id IN ($ph)", $idsForCount)['c'],
    'admins'        => (int)dbRow("SELECT COUNT(*) AS c FROM admins WHERE company_id IN ($ph)", $idsForCount)['c'],
    'subscriptions' => (int)dbRow("SELECT COUNT(*) AS c FROM subscriptions WHERE company_id IN ($ph)", $idsForCount)['c'],
    'sectors'       => (int)dbRow("SELECT COUNT(*) AS c FROM sectors WHERE company_id IN ($ph)", $idsForCount)['c'],
    'invites'       => (int)dbRow("SELECT COUNT(*) AS c FROM invites WHERE company_id IN ($ph)", $idsForCount)['c'],
];

superadminHead('Excluir Empresas em Massa', 'companies.php');
?>
<div class="sa-wrap" style="max-width:680px">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-trash-can" style="color:#ef4444"></i> Excluir Empresas em Massa</h1>
            <div class="sub"><a href="companies.php" style="color:var(--gray-400)">← Voltar para empresas</a></div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert" style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="border-radius:var(--radius);padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:2px solid #fecaca;background:#fff5f5;margin-bottom:20px">
        <div style="font-weight:800;color:#991b1b;font-size:16px;margin-bottom:6px">
            <i class="fa-solid fa-triangle-exclamation"></i> Esta ação é IRREVERSÍVEL
        </div>
        <p style="font-size:13px;color:#7f1d1d;margin:0 0 14px">
            Você está prestes a excluir permanentemente <strong><?= count($companies) ?> empresa<?= count($companies) > 1 ? 's' : '' ?></strong>:
        </p>
        <ul style="margin:0 0 16px;padding-left:20px;font-size:13px;color:#7f1d1d;line-height:1.8;max-height:180px;overflow-y:auto">
            <?php foreach ($companies as $c): ?>
            <li><strong><?= htmlspecialchars($c['name']) ?></strong> <span style="opacity:.7">(<?= htmlspecialchars($c['slug']) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
        <div style="font-size:13px;color:#7f1d1d;margin-bottom:6px;font-weight:700">No total, serão apagados:</div>
        <ul style="margin:0;padding-left:20px;font-size:14px;color:#7f1d1d;line-height:1.9">
            <li><strong><?= $counts['quizzes'] ?></strong> quiz(zes) e todas as suas questões</li>
            <li><strong><?= $counts['participants'] ?></strong> participação(ões)/resultado(s) e certificados emitidos</li>
            <li><strong><?= $counts['users'] ?></strong> colaborador(es) cadastrado(s)</li>
            <li><strong><?= $counts['admins'] ?></strong> administrador(es)</li>
            <li><strong><?= $counts['sectors'] ?></strong> setor(es)</li>
            <li><strong><?= $counts['invites'] ?></strong> convite(s) gerado(s)</li>
            <li><strong><?= $counts['subscriptions'] ?></strong> registro(s) de pagamento/assinatura</li>
        </ul>
    </div>

    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="ids" value="<?= htmlspecialchars(implode(',', $validIds)) ?>"/>
            <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:8px">
                Para confirmar, digite <code style="background:var(--gray-100);padding:2px 8px;border-radius:4px">EXCLUIR</code>
            </label>
            <input type="text" name="confirm_word" required autocomplete="off"
                   style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px;margin-bottom:16px;text-transform:uppercase"/>
            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" style="background:#ef4444;color:#fff;font-weight:700"
                        onclick="return confirm('Tem certeza ABSOLUTA? <?= count($companies) ?> empresa(s) serão excluídas para sempre.')">
                    <i class="fa-solid fa-trash-can"></i> Excluir <?= count($companies) ?> empresa<?= count($companies) > 1 ? 's' : '' ?> permanentemente
                </button>
                <a href="companies.php" class="btn" style="background:var(--gray-100);color:var(--gray-700)">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php superadminFoot(); ?>
