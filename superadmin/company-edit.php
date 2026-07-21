<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/layout.php';

$id      = (int)($_GET['id'] ?? 0);
$company = $id ? dbRow("SELECT * FROM companies WHERE id=?", [$id]) : null;
$isEdit  = (bool)$company;
$errors   = [];
$tempPass = '';
$resetPass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Reset de senha do admin ────────────────────────────────
    if ($action === 'reset_password' && $isEdit) {
        $admin = dbRow("SELECT id, username FROM admins WHERE company_id=? ORDER BY id ASC LIMIT 1", [$id]);
        if ($admin) {
            $resetPass = bin2hex(random_bytes(6));
            $hash = password_hash($resetPass, PASSWORD_DEFAULT);
            dbExec("UPDATE admins SET password_hash=?, first_login=1 WHERE id=?", [$hash, $admin['id']]);
            logAudit('reset_admin_password', $id, json_encode(['admin_id' => $admin['id'], 'admin_email' => $admin['username']]));
        }

    // ── Salvar dados da empresa ────────────────────────────────
    } else {
        $name   = trim($_POST['name']   ?? '');
        $email  = trim($_POST['email']  ?? '');
        $cnpj   = trim($_POST['cnpj']   ?? '');
        $plan   = in_array($_POST['plan'] ?? '', ['free','pro']) ? $_POST['plan'] : 'free';
        $status = in_array($_POST['status'] ?? '', ['active','suspended','pending_payment']) ? $_POST['status'] : 'active';

        if (!$name)  $errors[] = 'Nome da empresa obrigatório.';
        if (!$email) $errors[] = 'E-mail obrigatório.';

        if ($isEdit) {
            $slug = $company['slug']; // imutável
        } else {
            $slug = slugUnico($name);
            if ($email && dbRow("SELECT id FROM admins WHERE username=?", [$email])) {
                $errors[] = 'Este e-mail já está em uso como admin de outra empresa.';
            }
        }

        if (empty($errors)) {
            if ($isEdit) {
                dbExec("UPDATE companies SET name=?, email=?, cnpj=?, plan=?, status=?, updated_at=datetime('now','localtime') WHERE id=?",
                       [$name, $email, $cnpj ?: null, $plan, $status, $id]);
                logAudit('edit_company', $id, json_encode(['plan'=>$plan,'status'=>$status]));
                header('Location: companies.php?_msg=' . urlencode('Empresa atualizada.')); exit;
            } else {
                dbExec("INSERT INTO companies (name, slug, email, cnpj, plan, status) VALUES (?,?,?,?,?,?)",
                       [$name, $slug, $email, $cnpj ?: null, $plan, $status]);
                $newId = (int)dbLastId();

                $tempPass = bin2hex(random_bytes(6));
                $hash = password_hash($tempPass, PASSWORD_DEFAULT);
                dbExec("INSERT INTO admins (company_id, username, password_hash, name, first_login) VALUES (?,?,?,?,1)",
                       [$newId, $email, $hash, $name]);

                logAudit('create_company', $newId, json_encode(['plan'=>$plan,'slug'=>$slug]));
            }
        }
    }
}

superadminHead($isEdit ? 'Editar Empresa' : 'Nova Empresa', 'companies.php');
?>
<div class="sa-wrap" style="max-width:680px">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-building" style="color:var(--yellow)"></i>
                <?= $isEdit ? 'Editar Empresa' : 'Nova Empresa' ?>
            </h1>
            <div class="sub"><a href="companies.php" style="color:var(--gray-400)">← Voltar para empresas</a></div>
        </div>
    </div>

    <?php if ($tempPass): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:20px">
        <i class="fa-solid fa-circle-check"></i>
        Empresa criada com sucesso! &nbsp;<strong>Senha temporária do admin:</strong>
        <code style="background:rgba(0,0,0,.1);padding:2px 8px;border-radius:4px;font-size:15px"><?= htmlspecialchars($tempPass) ?></code>
        <br><small style="opacity:.8">Anote e repasse para o responsável. Esta senha não será exibida novamente.</small>
    </div>
    <?php endif; ?>

    <?php if ($resetPass): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:20px;background:#fefce8;border:1.5px solid #fbbf24;color:#78350f">
        <i class="fa-solid fa-key"></i>
        Nova senha temporária do admin: &nbsp;
        <code style="background:rgba(0,0,0,.08);padding:2px 10px;border-radius:4px;font-size:15px;font-weight:700"><?= htmlspecialchars($resetPass) ?></code>
        <br><small style="opacity:.75">O admin será forçado a trocar a senha no próximo login. Anote — não será exibida novamente.</small>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="alert" style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <?php foreach ($errors as $e): ?><div><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($isEdit): ?>
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <a href="company-detail.php?id=<?= $id ?>" class="btn" style="background:var(--gray-100);color:var(--gray-700);font-size:13px">
            <i class="fa-solid fa-chart-bar"></i> Ver detalhes
        </a>
        <a href="impersonate.php?company_id=<?= $id ?>" class="btn" style="background:#e9d5ff;color:#6b21a8;font-size:13px">
            <i class="fa-solid fa-user-secret"></i> Impersonar
        </a>
    </div>
    <?php endif; ?>

    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div style="grid-column:1/-1">
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Nome da empresa *</label>
                    <input type="text" name="name" required maxlength="120"
                           value="<?= htmlspecialchars($company['name'] ?? $_POST['name'] ?? '') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">E-mail do responsável/admin *</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($company['email'] ?? $_POST['email'] ?? '') ?>"
                           <?= $isEdit ? 'readonly style="background:var(--gray-100)"' : '' ?>
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                    <?php if ($isEdit): ?><div style="font-size:11px;color:var(--gray-400);margin-top:4px">E-mail do admin não pode ser alterado aqui.</div><?php endif; ?>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">CNPJ/CPF <span style="font-weight:400;color:var(--gray-400)">(opcional)</span></label>
                    <input type="text" name="cnpj" maxlength="18"
                           value="<?= htmlspecialchars($company['cnpj'] ?? $_POST['cnpj'] ?? '') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
                <?php if ($isEdit): ?>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Slug (subdomínio)</label>
                    <input type="text" value="<?= htmlspecialchars($company['slug']) ?>" readonly
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px;background:var(--gray-100)"/>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Slug é imutável após criação.</div>
                </div>
                <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Plano</label>
                    <select name="plan" style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px;background:#fff">
                        <option value="free" <?= ($company['plan'] ?? 'free')==='free'?'selected':'' ?>>Free</option>
                        <option value="pro"  <?= ($company['plan'] ?? '')==='pro'?'selected':'' ?>>Pro</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Status</label>
                    <select name="status" style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px;background:#fff">
                        <option value="active"          <?= ($company['status'] ?? 'active')==='active'?'selected':'' ?>>Ativa</option>
                        <option value="pending_payment" <?= ($company['status'] ?? '')==='pending_payment'?'selected':'' ?>>Pro Solicitado (pendente)</option>
                        <option value="suspended"       <?= ($company['status'] ?? '')==='suspended'?'selected':'' ?>>Suspensa</option>
                    </select>
                </div>
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
                    <i class="fa-solid fa-<?= $isEdit ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $isEdit ? 'Salvar alterações' : 'Criar empresa' ?>
                </button>
                <a href="companies.php" class="btn" style="background:var(--gray-100);color:var(--gray-700)">Cancelar</a>
            </div>
        </form>
    </div>

    <?php if ($isEdit): ?>
    <?php $adminRow = dbRow("SELECT id, username, name FROM admins WHERE company_id=? ORDER BY id ASC LIMIT 1", [$id]); ?>
    <div class="card" style="border-radius:var(--radius);padding:24px 28px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:0;border-top:3px solid #fbbf24">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:4px">
                    <i class="fa-solid fa-key" style="color:#d97706"></i> &nbsp;Redefinir senha do administrador
                </div>
                <?php if ($adminRow): ?>
                <div style="font-size:12px;color:var(--gray-500)">
                    Admin: <strong><?= htmlspecialchars($adminRow['name']) ?></strong>
                    &nbsp;·&nbsp; <?= htmlspecialchars($adminRow['username']) ?>
                </div>
                <?php else: ?>
                <div style="font-size:12px;color:#991b1b">Nenhum admin cadastrado para esta empresa.</div>
                <?php endif; ?>
            </div>
            <?php if ($adminRow): ?>
            <form method="POST" onsubmit="return confirm('Gerar nova senha temporária para <?= htmlspecialchars(addslashes($adminRow['name'])) ?>?')">
                <input type="hidden" name="action" value="reset_password"/>
                <button type="submit" class="btn" style="background:#fef3c7;color:#92400e;font-weight:700;border:1.5px solid #fbbf24">
                    <i class="fa-solid fa-rotate-right"></i> Gerar nova senha temporária
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php superadminFoot(); ?>
