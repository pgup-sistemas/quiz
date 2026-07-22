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

$id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$company = $id ? dbRow("SELECT * FROM companies WHERE id=?", [$id]) : null;
if (!$company) { header('Location: companies.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $confirmName = trim($_POST['confirm_name'] ?? '');

    if ($confirmName !== $company['name']) {
        $error = 'O nome digitado não confere. Exclusão cancelada por segurança.';
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
            // Ordem importa: filhos antes de pais (FKs sem ON DELETE CASCADE entre tabelas de dominio e companies)
            dbExec("DELETE FROM answers       WHERE company_id=?", [$id]);
            dbExec("DELETE FROM participants   WHERE company_id=?", [$id]);
            dbExec("DELETE FROM quizzes        WHERE company_id=?", [$id]); // cascade: questions, quiz_assignments
            dbExec("DELETE FROM sectors        WHERE company_id=?", [$id]); // cascade: quiz_assignments restantes
            dbExec("DELETE FROM users          WHERE company_id=?", [$id]);
            dbExec("DELETE FROM admins         WHERE company_id=?", [$id]);
            dbExec("DELETE FROM invites        WHERE company_id=?", [$id]);
            dbExec("DELETE FROM contact_messages WHERE company_id=?", [$id]);
            dbExec("DELETE FROM subscriptions  WHERE company_id=?", [$id]);
            dbExec("DELETE FROM payment_events WHERE company_id=?", [$id]);
            dbExec("DELETE FROM companies      WHERE id=?", [$id]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Erro ao excluir: ' . $e->getMessage();
        }

        if (!$error) {
            // Log apos a exclusao (target_company_id nao tem FK, entao o registro historico sobrevive)
            logAudit('delete_company', $id, json_encode(['name' => $company['name'], 'slug' => $company['slug']]));
            header('Location: companies.php?_msg=' . urlencode('Empresa "' . $company['name'] . '" excluída permanentemente.'));
            exit;
        }
    }
}

// Contagens para exibir o impacto da exclusao
$counts = [
    'quizzes'       => (int)dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE company_id=?", [$id])['c'],
    'users'         => (int)dbRow("SELECT COUNT(*) AS c FROM users WHERE company_id=?", [$id])['c'],
    'participants'  => (int)dbRow("SELECT COUNT(*) AS c FROM participants WHERE company_id=?", [$id])['c'],
    'admins'        => (int)dbRow("SELECT COUNT(*) AS c FROM admins WHERE company_id=?", [$id])['c'],
    'subscriptions' => (int)dbRow("SELECT COUNT(*) AS c FROM subscriptions WHERE company_id=?", [$id])['c'],
    'sectors'       => (int)dbRow("SELECT COUNT(*) AS c FROM sectors WHERE company_id=?", [$id])['c'],
    'invites'       => (int)dbRow("SELECT COUNT(*) AS c FROM invites WHERE company_id=?", [$id])['c'],
];

superadminHead('Excluir Empresa', 'companies.php');
?>
<div class="sa-wrap" style="max-width:640px">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-trash-can" style="color:#ef4444"></i> Excluir Empresa</h1>
            <div class="sub"><a href="companies.php" style="color:var(--gray-400)">← Voltar para empresas</a></div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert" style="background:rgba(239,68,68,.15);color:#fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="border-radius:var(--radius);padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:2px solid rgba(239,68,68,.4);background:rgba(239,68,68,.10);margin-bottom:20px">
        <div style="font-weight:800;color:#fca5a5;font-size:16px;margin-bottom:6px">
            <i class="fa-solid fa-triangle-exclamation"></i> Esta ação é IRREVERSÍVEL
        </div>
        <p style="font-size:13px;color:#fca5a5;margin:0 0 16px">
            Ao excluir <strong><?= htmlspecialchars($company['name']) ?></strong>, todos os dados abaixo serão
            permanentemente apagados do banco de dados — não há como recuperar depois.
        </p>
        <ul style="margin:0;padding-left:20px;font-size:14px;color:#fca5a5;line-height:1.9">
            <li><strong><?= $counts['quizzes'] ?></strong> quiz(zes) e todas as suas questões</li>
            <li><strong><?= $counts['participants'] ?></strong> participação(ões)/resultado(s) e certificados emitidos</li>
            <li><strong><?= $counts['users'] ?></strong> colaborador(es) cadastrado(s)</li>
            <li><strong><?= $counts['admins'] ?></strong> administrador(es) da empresa</li>
            <li><strong><?= $counts['sectors'] ?></strong> setor(es)</li>
            <li><strong><?= $counts['invites'] ?></strong> convite(s) gerado(s)</li>
            <li><strong><?= $counts['subscriptions'] ?></strong> registro(s) de pagamento/assinatura</li>
        </ul>
        <div style="margin-top:14px;font-size:12px;color:#fca5a5">
            <i class="fa-solid fa-circle-info"></i> Se o objetivo é apenas bloquear o acesso, considere
            <a href="companies.php" style="color:#fca5a5;font-weight:700">Suspender</a> a empresa em vez de excluir —
            é reversível e preserva o histórico.
        </div>
    </div>

    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>"/>
            <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:8px">
                Para confirmar, digite o nome exato da empresa: <code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?= htmlspecialchars($company['name']) ?></code>
            </label>
            <input type="text" name="confirm_name" required autocomplete="off"
                   style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px;margin-bottom:16px"/>
            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" style="background:#ef4444;color:#fff;font-weight:700"
                        onclick="return confirm('Tem certeza ABSOLUTA? Esta ação não pode ser desfeita.')">
                    <i class="fa-solid fa-trash-can"></i> Excluir permanentemente
                </button>
                <a href="companies.php" class="btn" style="background:var(--gray-100);color:var(--gray-700)">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php superadminFoot(); ?>
