<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$adminId = adminId();
$cid     = adminCompanyId();

/* ── Change Password ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    requireCsrf();
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
    requireCsrf();
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
    requireCsrf();
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

/* ── Branding (logo + cor) ───────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_branding'])) {
    requireCsrf();
    $color = trim($_POST['primary_color'] ?? '');
    if ($color && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        flash('Cor inválida. Use formato hexadecimal (#RRGGBB).', 'error');
        redirect('settings.php');
    }

    $logoPath = null;
    if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $extByMime = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp'    => 'webp',
        ];
        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        if (!isset($extByMime[$mime])) {
            flash('Tipo de arquivo inválido. Use PNG, JPG, SVG ou WebP.', 'error');
            redirect('settings.php');
        }
        // Extensão derivada do MIME real detectado no servidor — nunca do nome de arquivo enviado pelo usuário
        $ext = $extByMime[$mime];
        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            flash('Logo muito grande. Limite: 2 MB.', 'error');
            redirect('settings.php');
        }
        $dir = __DIR__ . '/../uploads/logos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'co_' . $cid . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $filename)) {
            // Remove logo anterior
            $old = dbRow("SELECT logo_path FROM companies WHERE id = ?", [$cid]);
            if (!empty($old['logo_path'])) {
                $oldFile = __DIR__ . '/../' . $old['logo_path'];
                if (file_exists($oldFile)) @unlink($oldFile);
            }
            $logoPath = 'uploads/logos/' . $filename;
        }
    }

    if ($color) {
        dbExec("UPDATE companies SET primary_color = ?, updated_at = datetime('now','localtime') WHERE id = ?", [$color, $cid]);
    }
    if ($logoPath !== null) {
        dbExec("UPDATE companies SET logo_path = ?, updated_at = datetime('now','localtime') WHERE id = ?", [$logoPath, $cid]);
    }

    flash('Identidade visual atualizada!', 'success');
    redirect('settings.php');
}

/* ── Remove logo ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_logo'])) {
    requireCsrf();
    $co = dbRow("SELECT logo_path FROM companies WHERE id = ?", [$cid]);
    if (!empty($co['logo_path'])) {
        $f = __DIR__ . '/../' . $co['logo_path'];
        if (file_exists($f)) @unlink($f);
    }
    dbExec("UPDATE companies SET logo_path = NULL WHERE id = ?", [$cid]);
    flash('Logo removida.', 'success');
    redirect('settings.php');
}

/* ── Toggle allow_self_register ────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_self_register'])) {
    requireCsrf();
    $val = (int)($_POST['allow_self_register'] ?? 0);
    dbExec("UPDATE companies SET allow_self_register = ? WHERE id = ?", [$val, $cid]);
    flash($val ? 'Auto-cadastro habilitado.' : 'Auto-cadastro desabilitado.', 'success');
    redirect('settings.php');
}

/* ── Delete Admin ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_admin']) && (int)$_POST['del_admin'] !== $adminId) {
    requireCsrf();
    dbExec("DELETE FROM admins WHERE id = ? AND id != ? AND company_id = ?",
        [(int)$_POST['del_admin'], $adminId, $cid]);
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
            <?= csrfField() ?>
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
            <?= csrfField() ?>
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

<!-- ══ SEÇÃO 2: IDENTIDADE VISUAL ════════════════════════ -->
<div class="cfg-section-title">
    <i class="fa-solid fa-palette"></i> Identidade Visual
    <?php if ($myCompany['plan'] !== 'pro'): ?>
    <span class="badge badge-yellow" style="font-size:10px;text-transform:none;letter-spacing:0">Pro</span>
    <?php endif; ?>
</div>

<?php if ($myCompany['plan'] === 'pro'): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <h2><i class="fa-solid fa-image" style="color:var(--pacific)"></i> Logo &amp; Cor da Empresa</h2>
        <p style="font-size:12px;color:var(--gray-400);margin:4px 0 0">Aplicados no portal do colaborador e quizzes.</p>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="update_branding" value="1"/>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="cfg-two-col">
            <!-- Logo -->
            <div>
                <div class="form-group">
                    <label class="form-label">Logo da empresa</label>
                    <?php if (!empty($myCompany['logo_path']) && file_exists(__DIR__.'/../'.$myCompany['logo_path'])): ?>
                    <div style="margin-bottom:10px;padding:12px;background:var(--gray-50);border-radius:8px;display:inline-flex;align-items:center;gap:12px">
                        <img src="../<?= htmlspecialchars($myCompany['logo_path']) ?>" alt="Logo atual" style="height:40px;max-width:120px;object-fit:contain"/>
                        <form method="post" onsubmit="return confirm('Remover a logo atual?')" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="remove_logo" value="1"/>
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp"/>
                    <div class="form-hint">PNG, JPG, SVG ou WebP · máx. 2 MB</div>
                </div>
            </div>
            <!-- Cor primária -->
            <div>
                <div class="form-group">
                    <label class="form-label">Cor primária</label>
                    <div style="display:flex;align-items:center;gap:10px">
                        <input type="color" name="primary_color"
                               value="<?= htmlspecialchars($myCompany['primary_color'] ?? '#219EBC') ?>"
                               style="width:48px;height:38px;padding:2px 4px;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer"/>
                        <input class="form-control" type="text" id="color-hex" placeholder="#219EBC"
                               value="<?= htmlspecialchars($myCompany['primary_color'] ?? '') ?>"
                               pattern="^#[0-9a-fA-F]{6}$" style="font-family:monospace;width:110px"/>
                    </div>
                    <div class="form-hint">Aplicada nos botões e destaques do portal</div>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-circle-check"></i> Salvar Identidade Visual
        </button>
    </form>
</div>
<script>
(function(){
    var picker = document.querySelector('input[type="color"][name="primary_color"]');
    var hexIn  = document.getElementById('color-hex');
    if (!picker || !hexIn) return;
    picker.addEventListener('input', function(){ hexIn.value = picker.value; });
    hexIn.addEventListener('input', function(){
        if (/^#[0-9a-fA-F]{6}$/.test(hexIn.value)) picker.value = hexIn.value;
    });
})();
</script>
<?php else: ?>
<div style="background:var(--gray-50);border:1px dashed var(--gray-200);border-radius:12px;padding:24px;text-align:center;margin-bottom:20px">
    <i class="fa-solid fa-palette" style="font-size:28px;color:var(--gray-300);display:block;margin-bottom:8px"></i>
    <div style="font-weight:600;color:var(--gray-600);margin-bottom:4px">Disponível no Plano Pro</div>
    <div style="font-size:12px;color:var(--gray-400)">Personalize o logo e as cores do portal com o plano Pro.</div>
    <a href="upgrade.php" class="btn btn-sm" style="margin-top:12px;background:var(--yellow);color:var(--prussian);font-weight:700">
        <i class="fa-solid fa-star"></i> Fazer Upgrade
    </a>
</div>
<?php endif; ?>

<!-- ══ SEÇÃO 4: EMPRESA ══════════════════════════════════ -->
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
        <?= csrfField() ?>
        <input type="hidden" name="toggle_self_register" value="1"/>
        <input type="hidden" name="allow_self_register" value="<?= $selfReg ? 0 : 1 ?>"/>
        <button type="submit" class="btn <?= $selfReg ? 'btn-danger' : 'btn-primary' ?> btn-sm">
            <?= $selfReg
                ? '<i class="fa-solid fa-ban"></i> Desabilitar'
                : '<i class="fa-solid fa-circle-check"></i> Habilitar' ?>
        </button>
    </form>
</div>

<!-- ══ SEÇÃO 5: ADMINISTRADORES ══════════════════════════ -->
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
            <?= csrfField() ?>
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
            <form method="post" onsubmit="return confirm('Remover o admin «<?= e($adm['username']) ?>»?')" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="del_admin" value="<?= $adm['id'] ?>"/>
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

</div><!-- /admin-wrap -->
<?php adminFoot(); ?>
