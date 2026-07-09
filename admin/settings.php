<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$adminId = (int)($_SESSION['admin_id'] ?? 0);

/* ── Change Password ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $current  = $_POST['current_pass'] ?? '';
    $new      = $_POST['new_pass']     ?? '';
    $confirm  = $_POST['confirm_pass'] ?? '';

    $admin = dbRow("SELECT * FROM admins WHERE id = ?", [$adminId]);

    if (!password_verify($current, $admin['password_hash'])) {
        flash('Senha atual incorreta.', 'error');
    } elseif (strlen($new) < 6) {
        flash('A nova senha deve ter ao menos 6 caracteres.', 'error');
    } elseif ($new !== $confirm) {
        flash('As senhas não coincidem.', 'error');
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        dbExec("UPDATE admins SET password_hash = ? WHERE id = ?", [$hash, $adminId]);
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
    $user  = trim($_POST['new_username'] ?? '');
    $pass  = $_POST['new_password']      ?? '';
    $name  = trim($_POST['new_name']     ?? $user);

    $exists = dbRow("SELECT id FROM admins WHERE username = ?", [$user]);
    if (!$user || strlen($pass) < 6) {
        flash('Preencha usuário e senha (mín. 6 chars).', 'error');
    } elseif ($exists) {
        flash('Esse usuário já existe.', 'error');
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        dbExec("INSERT INTO admins (username, password_hash, name) VALUES (?,?,?)", [$user, $hash, $name]);
        flash("Admin «{$user}» criado com sucesso!", 'success');
    }
    redirect('settings.php');
}

/* ── Delete Admin ──────────────────────────────────── */
if (isset($_GET['del_admin']) && (int)$_GET['del_admin'] !== $adminId) {
    dbExec("DELETE FROM admins WHERE id = ? AND id != ?", [(int)$_GET['del_admin'], $adminId]);
    flash('Administrador removido.', 'success');
    redirect('settings.php');
}

$admins  = dbRows("SELECT id, username, name, created_at FROM admins ORDER BY id ASC");
$myAdmin = dbRow("SELECT * FROM admins WHERE id = ?", [$adminId]);

adminHead('Configurações', 'settings.php');
?>
<div class="admin-wrap" style="max-width:800px">



<h1 style="font-size:22px;font-weight:700;color:var(--gray-800);margin-bottom:24px">⚙️ Configurações</h1>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<!-- Change Name -->
<div class="card">
    <div class="card-header"><h2>👤 Meu Perfil</h2></div>
    <form method="post">
        <input type="hidden" name="update_name" value="1"/>
        <div class="form-group">
            <label class="form-label">Usuário</label>
            <input class="form-control" type="text" value="<?= e($myAdmin['username']) ?>" disabled style="background:var(--gray-50)"/>
        </div>
        <div class="form-group">
            <label class="form-label">Nome de Exibição</label>
            <input class="form-control" type="text" name="name" value="<?= e($myAdmin['name']) ?>" required/>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-check"></i> Salvar Nome</button>
    </form>
</div>

<!-- Change Password -->
<div class="card">
    <div class="card-header"><h2>🔐 Alterar Senha</h2></div>
    <form method="post" autocomplete="off">
        <input type="hidden" name="change_pass" value="1"/>
        <div class="form-group">
            <label class="form-label">Senha Atual</label>
            <input class="form-control" type="password" name="current_pass" required autocomplete="current-password"/>
        </div>
        <div class="form-group">
            <label class="form-label">Nova Senha</label>
            <input class="form-control" type="password" name="new_pass" required minlength="6" autocomplete="new-password"/>
        </div>
        <div class="form-group">
            <label class="form-label">Confirmar Nova Senha</label>
            <input class="form-control" type="password" name="confirm_pass" required autocomplete="new-password"/>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock"></i> Alterar Senha</button>
    </form>
</div>

</div>

<!-- Admin Users -->
<div class="card" style="margin-top:20px">
    <div class="card-header"><h2>👥 Administradores</h2></div>
    <div class="table-wrap" style="margin-bottom:24px">
        <table>
            <thead><tr><th>Usuário</th><th>Nome</th><th>Criado em</th><th>Ação</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $adm): ?>
            <tr>
                <td><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px;font-size:13px"><?= e($adm['username']) ?></code></td>
                <td><?= e($adm['name']) ?></td>
                <td style="font-size:12px;color:var(--gray-400)"><?= date('d/m/Y', strtotime($adm['created_at'])) ?></td>
                <td>
                    <?php if ($adm['id'] !== $adminId): ?>
                    <a href="?del_admin=<?= $adm['id'] ?>" class="btn btn-danger btn-sm"
                       onclick="return confirm('Tem certeza que deseja excluir este admin?')"><i class="fa-solid fa-trash"></i></a>
                    <?php else: ?>
                    <span class="badge badge-blue">Você</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <hr style="border:none;border-top:1px solid var(--gray-100);margin-bottom:20px"/>
    <h3 style="font-size:14px;font-weight:700;color:var(--gray-600);margin-bottom:16px">➕ Adicionar Administrador</h3>
    <form method="post">
        <input type="hidden" name="add_admin" value="1"/>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Usuário</label>
                <input class="form-control" type="text" name="new_username" required placeholder="usuario"/>
            </div>
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input class="form-control" type="text" name="new_name" placeholder="Nome completo"/>
            </div>
            <div class="form-group">
                <label class="form-label">Senha (mín. 6 chars)</label>
                <input class="form-control" type="password" name="new_password" required minlength="6"/>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Criar Admin</button>
    </form>
</div>

</div>
<?php adminFoot(); ?>
