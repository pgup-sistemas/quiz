<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$adminId = adminId();
$cid     = adminCompanyId();

/* ── Change Password ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $current = $_POST['current_pass'] ?? '';
    $new     = $_POST['new_pass']     ?? '';
    $confirm = $_POST['confirm_pass'] ?? '';
    $admin   = dbRow("SELECT * FROM admins WHERE id = ? AND company_id = ?", [$adminId, $cid]);

    if (!password_verify($current, $admin['password_hash'])) {
        flash('Senha atual incorreta.', 'error');
    } elseif (strlen($new) < 6) {
        flash('A nova senha deve ter ao menos 6 caracteres.', 'error');
    } elseif ($new !== $confirm) {
        flash('As senhas não coincidem.', 'error');
    } else {
        dbExec("UPDATE admins SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $adminId]);
        flash('Senha alterada com sucesso!', 'success');
    }
    redirect('settings.php');
}

/* ── Update Name ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_name'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        dbExec("UPDATE admins SET name = ? WHERE id = ?", [$name, $adminId]);
        $_SESSION['admin_name'] = $name;
        flash('Nome atualizado!', 'success');
    }
    redirect('settings.php');
}

/* ── Add Admin ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $user = trim($_POST['new_username'] ?? '');
    $pass = $_POST['new_password']      ?? '';
    $name = trim($_POST['new_name']     ?? $user);

    if (!$user || strlen($pass) < 6) {
        flash('Preencha usuário e senha (mín. 6 chars).', 'error');
    } elseif (dbRow("SELECT id FROM admins WHERE username = ? AND company_id = ?", [$user, $cid])) {
        flash("Esse usuário já existe.", 'error');
    } else {
        dbExec("INSERT INTO admins (username, password_hash, name, company_id) VALUES (?,?,?,?)",
            [$user, password_hash($pass, PASSWORD_DEFAULT), $name, $cid]);
        flash("Admin «{$user}» criado com sucesso!", 'success');
    }
    redirect('settings.php');
}

/* ── Toggle allow_self_register ────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_self_register'])) {
    $val = (int)($_POST['allow_self_register'] ?? 0);
    dbExec("UPDATE companies SET allow_self_register = ? WHERE id = ?", [$val, $cid]);
    flash($val ? 'Auto-cadastro habilitado.' : 'Auto-cadastro desabilitado.', 'success');
    redirect('settings.php');
}

/* ── Delete Admin ──────────────────────────────────── */
if (isset($_GET['del_admin']) && (int)$_GET['del_admin'] !== $adminId) {
    dbExec("DELETE FROM admins WHERE id = ? AND id != ? AND company_id = ?",
        [(int)$_GET['del_admin'], $adminId, $cid]);
    flash('Administrador removido.', 'success');
    redirect('settings.php');
}

$admins    = dbRows("SELECT id, username, name, created_at FROM admins WHERE company_id = ? ORDER BY id ASC", [$cid]);
$myAdmin   = dbRow("SELECT * FROM admins WHERE id = ? AND company_id = ?", [$adminId, $cid]);
$myCompany = dbRow("SELECT * FROM companies WHERE id = ?", [$cid]);
$selfReg   = (int)($myCompany['allow_self_register'] ?? 1);

adminHead('Configurações', 'settings.php');
?>
<style>
/* ── Section divider ──────────────────────────────── */
.cfg-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--gray-400);
    margin: 32px 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--gray-100);
}
.cfg-section-title:first-of-type { margin-top: 0; }
.cfg-section-title i { color: var(--pacific); font-size: 14px; }

/* ── Toggle row ───────────────────────────────────── */
.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    padding: 18px 20px;
    background: #fff;
    border: 1px solid var(--gray-100);
    border-radius: 12px;
}
.toggle-row:hover { border-color: var(--gray-200); }

/* ── Admin list ───────────────────────────────────── */
.admin-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-100);
}
.admin-row:last-child { border-bottom: none; }
.admin-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--pacific);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.admin-avatar.me { background: var(--prussian); }

@media (max-width: 720px) {
    .cfg-two-col { grid-template-columns: 1fr !important; }
}
</style>

<div class="admin-wrap">

<!-- Header -->
<div class="flex items-center justify-between mb-24">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">
            <i class="fa-solid fa-sliders" style="color:var(--pacific)"></i> Configurações
        </h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px">
            Gerencie sua conta, empresa e administradores do sistema
        </p>
    </div>
</div>

<!-- ══ SEÇÃO 1: MINHA CONTA ═════════════════════════════ -->
<div class="cfg-section-title">
    <i class="fa-solid fa-user"></i> Minha Conta
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="cfg-two-col">

    <!-- Perfil -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <h2><i class="fa-solid fa-id-card" style="color:var(--pacific)"></i> Dados de Perfil</h2>
        </div>
        <form method="post">
            <input type="hidden" name="update_name" value="1"/>
            <div class="form-group">
                <label class="form-label">Login de acesso</label>
                <input class="form-control" type="text" value="<?= e($myAdmin['username']) ?>" disabled
                       style="background:var(--gray-50);color:var(--gray-400)"/>
                <div class="form-hint">O login não pode ser alterado.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Nome de exibição</label>
                <input class="form-control" type="text" name="name"
                       value="<?= e($myAdmin['name']) ?>" required placeholder="Seu nome"/>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-circle-check"></i> Salvar
            </button>
        </form>
    </div>

    <!-- Senha -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <h2><i class="fa-solid fa-lock" style="color:var(--pacific)"></i> Alterar Senha</h2>
        </div>
        <form method="post" autocomplete="off">
            <input type="hidden" name="change_pass" value="1"/>
            <div class="form-group">
                <label class="form-label">Senha atual</label>
                <input class="form-control" type="password" name="current_pass"
                       required autocomplete="current-password" placeholder="••••••"/>
            </div>
            <div class="form-group">
                <label class="form-label">Nova senha</label>
                <input class="form-control" type="password" name="new_pass"
                       required minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres"/>
            </div>
            <div class="form-group">
                <label class="form-label">Confirmar nova senha</label>
                <input class="form-control" type="password" name="confirm_pass"
                       required autocomplete="new-password" placeholder="Repita a nova senha"/>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-lock"></i> Alterar Senha
            </button>
        </form>
    </div>

</div>

<!-- ══ SEÇÃO 2: EMPRESA ══════════════════════════════════ -->
<div class="cfg-section-title">
    <i class="fa-solid fa-building"></i> Empresa
</div>

<div class="toggle-row">
    <div style="min-width:0;flex:1">
        <div style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:4px;display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-user-plus" style="color:var(--pacific)"></i>
            Auto-cadastro de colaboradores
            <span class="badge <?= $selfReg ? 'badge-green' : 'badge-red' ?>" style="font-size:11px">
                <?= $selfReg ? 'Ativo' : 'Inativo' ?>
            </span>
        </div>
        <p style="font-size:12px;color:var(--gray-400);margin:0;line-height:1.5">
            Permite que colaboradores criem conta diretamente em <code style="font-size:11px">/user/register.php</code> sem precisar de convite.
            Desabilite para controlar o acesso exclusivamente via links de convite.
        </p>
    </div>
    <form method="POST" style="flex-shrink:0">
        <input type="hidden" name="toggle_self_register" value="1"/>
        <input type="hidden" name="allow_self_register" value="<?= $selfReg ? 0 : 1 ?>"/>
        <button type="submit" class="btn <?= $selfReg ? 'btn-danger' : 'btn-primary' ?> btn-sm">
            <?= $selfReg
                ? '<i class="fa-solid fa-ban"></i> Desabilitar'
                : '<i class="fa-solid fa-circle-check"></i> Habilitar' ?>
        </button>
    </form>
</div>

<!-- ══ SEÇÃO 3: ADMINISTRADORES ══════════════════════════ -->
<div class="cfg-section-title">
    <i class="fa-solid fa-user-shield"></i> Administradores
    <span class="badge badge-blue" style="font-size:11px;text-transform:none;letter-spacing:0"><?= count($admins) ?></span>
</div>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start" class="cfg-two-col">

    <!-- Formulário adicionar admin -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <h2><i class="fa-solid fa-user-plus" style="color:var(--green)"></i> Adicionar Admin</h2>
        </div>
        <form method="post">
            <input type="hidden" name="add_admin" value="1"/>
            <div class="form-group">
                <label class="form-label">Usuário *</label>
                <input class="form-control" type="text" name="new_username" required placeholder="login"/>
            </div>
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input class="form-control" type="text" name="new_name" placeholder="Nome completo"/>
            </div>
            <div class="form-group">
                <label class="form-label">Senha * <span style="color:var(--gray-400);font-weight:400">(mín. 6 chars)</span></label>
                <input class="form-control" type="password" name="new_password" required minlength="6"/>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
                <i class="fa-solid fa-plus"></i> Criar Administrador
            </button>
        </form>
    </div>

    <!-- Lista de admins -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <h2><i class="fa-solid fa-users" style="color:var(--pacific)"></i> Usuários com acesso admin</h2>
        </div>
        <?php if (empty($admins)): ?>
        <p style="text-align:center;padding:32px;color:var(--gray-400);font-size:13px">Nenhum administrador cadastrado.</p>
        <?php else: ?>
        <div>
        <?php foreach ($admins as $adm):
            $isMe = $adm['id'] === $adminId;
        ?>
        <div class="admin-row">
            <div class="admin-avatar <?= $isMe ? 'me' : '' ?>">
                <?= strtoupper(substr($adm['name'] ?: $adm['username'], 0, 2)) ?>
            </div>
            <div style="min-width:0;flex:1">
                <div style="font-weight:700;font-size:13px;color:var(--gray-800);display:flex;align-items:center;gap:6px">
                    <?= e($adm['name'] ?: $adm['username']) ?>
                    <?php if ($isMe): ?>
                    <span class="badge badge-blue" style="font-size:10px">Você</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--gray-400);margin-top:1px">
                    <code style="background:var(--gray-100);padding:1px 6px;border-radius:4px"><?= e($adm['username']) ?></code>
                    · desde <?= date('d/m/Y', strtotime($adm['created_at'])) ?>
                </div>
            </div>
            <?php if (!$isMe): ?>
            <a href="?del_admin=<?= $adm['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Remover o admin «<?= e($adm['username']) ?>»?')">
                <i class="fa-solid fa-trash"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

</div><!-- /admin-wrap -->
<?php adminFoot(); ?>
