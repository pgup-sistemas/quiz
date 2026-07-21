<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/layout.php';

$id      = (int)($_GET['id'] ?? 0);
$company = $id ? dbRow("SELECT * FROM companies WHERE id=?", [$id]) : null;
$isEdit  = (bool)$company;
$errors   = [];
$tempPass = '';
$resetPass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Trocar e-mail/login do admin ─────────────────────────
    if ($action === 'change_email' && $isEdit) {
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        } elseif (dbRow("SELECT id FROM admins WHERE username=? AND company_id!=?", [$newEmail, $id])) {
            $errors[] = 'Este e-mail já está em uso por outro admin.';
        } else {
            $adminRow2 = dbRow("SELECT id FROM admins WHERE company_id=? ORDER BY id ASC LIMIT 1", [$id]);
            if ($adminRow2) {
                dbExec("UPDATE admins SET username=? WHERE id=?",    [$newEmail, $adminRow2['id']]);
                dbExec("UPDATE companies SET email=? WHERE id=?",    [$newEmail, $id]);
                logAudit('change_admin_email', $id, json_encode(['new_email' => $newEmail]));
                header('Location: company-edit.php?id=' . $id . '&_email_changed=1'); exit;
            }
        }

    // ── Reset de senha do admin ────────────────────────────────
    } elseif ($action === 'reset_password' && $isEdit) {
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
        if ($cnpj && !validarCnpj($cnpj)) $errors[] = 'CNPJ inválido.';

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
                dbExec("UPDATE companies SET name=?, email=?, cnpj=?, plan=?, status=?, updated_at=NOW() WHERE id=?",
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

                // E-mail de boas-vindas
                $loginUrl = rtrim(BASE_URL, '/') . '/admin/login.php';
                $appName  = htmlspecialchars(dbRow("SELECT value FROM system_settings WHERE `key`='app_name'")['value'] ?? 'PageQuiz');
                $html = mailTemplate(
                    "Bem-vindo ao $appName!",
                    "<p>Olá, <strong>" . htmlspecialchars($name) . "</strong>!</p>"
                    . "<p>Sua conta de administrador foi criada na plataforma <strong>$appName</strong>.</p>"
                    . "<p><strong>Login:</strong> " . htmlspecialchars($email) . "<br/>"
                    . "<strong>Senha temporária:</strong> <code style='background:#f0f4f8;padding:2px 8px;border-radius:4px;font-family:monospace'>$tempPass</code></p>"
                    . "<p style='color:#64748b;font-size:13px'>Você será solicitado a alterar sua senha no primeiro acesso.</p>"
                    . mailBtnHtml($loginUrl, 'Acessar o painel →')
                );
                sendMail($email, "Bem-vindo ao $appName — suas credenciais de acesso", $html, $name);
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

    <?php if (isset($_GET['_email_changed'])): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:20px">
        <i class="fa-solid fa-circle-check"></i>
        E-mail/login do administrador atualizado com sucesso.
    </div>
    <?php endif; ?>

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
                    <input type="text" name="cnpj" id="cnpj-input" maxlength="18"
                           value="<?= htmlspecialchars($company['cnpj'] ?? $_POST['cnpj'] ?? '') ?>"
                           placeholder="00.000.000/0001-00"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                    <div id="cnpj-feedback" style="font-size:11px;margin-top:4px"></div>
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
    <!-- Trocar e-mail do admin -->
    <?php if ($adminRow): ?>
    <div class="card" style="border-radius:var(--radius);padding:24px 28px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:0;border-top:3px solid var(--pacific)">
        <div style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:12px">
            <i class="fa-solid fa-at" style="color:var(--pacific)"></i> &nbsp;Alterar e-mail / login do administrador
        </div>
        <div style="font-size:12px;color:var(--gray-500);margin-bottom:14px">
            E-mail atual: <strong><?= htmlspecialchars($adminRow['username']) ?></strong>
        </div>
        <?php if (!empty($errors)): ?>
        <div style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px">
            <?php foreach ($errors as $e): ?><div><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Alterar o e-mail/login do administrador?')">
            <input type="hidden" name="action" value="change_email"/>
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <label style="font-size:12px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:5px">Novo e-mail</label>
                    <input type="email" name="new_email" required
                           placeholder="novo@email.com.br"
                           style="width:100%;box-sizing:border-box;padding:9px 12px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
                <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-weight:700;white-space:nowrap">
                    <i class="fa-solid fa-rotate"></i> Alterar e-mail
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
<script>
(function(){
    const input = document.getElementById('cnpj-input');
    const fb    = document.getElementById('cnpj-feedback');
    if (!input) return;

    function onlyDigits(s){ return s.replace(/\D/g,''); }

    function maskCnpj(v){
        v = onlyDigits(v).slice(0,14);
        return v
            .replace(/^(\d{2})(\d)/,       '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3')
            .replace(/\.(\d{3})(\d)/,       '.$1/$2')
            .replace(/(\d{4})(\d)/,         '$1-$2');
    }

    function validCnpj(s){
        s = onlyDigits(s);
        if (s.length !== 14 || /^(\d)\1+$/.test(s)) return false;
        function calc(s, n){
            let sum=0, pos=n-7;
            for(let i=n;i>=1;i--){
                sum += parseInt(s.charAt(n-i)) * pos--;
                if(pos<2) pos=9;
            }
            let r = sum%11;
            return r<2 ? 0 : 11-r;
        }
        return calc(s,12)===parseInt(s[12]) && calc(s,13)===parseInt(s[13]);
    }

    input.addEventListener('input', function(){
        const raw = onlyDigits(this.value);
        this.value = maskCnpj(this.value);
        if (raw.length === 0) {
            fb.textContent = ''; fb.style.color='';
        } else if (raw.length < 14) {
            fb.textContent = 'Digite todos os 14 dígitos.'; fb.style.color='#92400e';
        } else if (validCnpj(raw)) {
            fb.textContent = '✓ CNPJ válido'; fb.style.color='#166534';
        } else {
            fb.textContent = '✗ CNPJ inválido'; fb.style.color='#991b1b';
        }
    });

    // Aciona máscara no carregamento se já houver valor
    if (input.value) input.dispatchEvent(new Event('input'));
})();
</script>
<?php superadminFoot(); ?>
