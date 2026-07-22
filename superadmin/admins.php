<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

$meId = superAdminId();
$msg    = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $saName  = trim($_POST['name']     ?? '');
        $saEmail = strtolower(trim($_POST['username'] ?? ''));
        $saPass  = $_POST['password'] ?? '';

        if (!$saName)                                 $errors[] = 'Nome obrigatório.';
        if (!filter_var($saEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
        if (strlen($saPass) < 8)                      $errors[] = 'Senha deve ter mínimo 8 caracteres.';
        if (!$errors && dbRow("SELECT id FROM super_admins WHERE username=?", [$saEmail])) {
            $errors[] = 'Este e-mail já está em uso.';
        }
        if (!$errors) {
            $hash = password_hash($saPass, PASSWORD_DEFAULT);
            dbExec("INSERT INTO super_admins (username, password_hash, name) VALUES (?,?,?)", [$saEmail, $hash, $saName]);
            logAudit('create_super_admin', 0, json_encode(['username' => $saEmail]));
            $msg = "Super-admin <strong>" . htmlspecialchars($saName) . "</strong> criado com sucesso.";
        }

    } elseif ($action === 'deactivate') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $total    = (int)dbRow("SELECT COUNT(*) AS c FROM super_admins WHERE active=1")['c'];
        if ($targetId === $meId) {
            $errors[] = 'Você não pode desativar sua própria conta.';
        } elseif ($total <= 1) {
            $errors[] = 'Não é possível desativar o único super-admin ativo.';
        } elseif ($targetId) {
            $target = dbRow("SELECT name FROM super_admins WHERE id=?", [$targetId]);
            dbExec("UPDATE super_admins SET active=0 WHERE id=?", [$targetId]);
            logAudit('deactivate_super_admin', 0, json_encode(['target_id' => $targetId]));
            $msg = "Super-admin <strong>" . htmlspecialchars($target['name'] ?? '') . "</strong> desativado.";
        }

    } elseif ($action === 'reactivate') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $target = dbRow("SELECT name FROM super_admins WHERE id=?", [$targetId]);
            dbExec("UPDATE super_admins SET active=1 WHERE id=?", [$targetId]);
            logAudit('reactivate_super_admin', 0, json_encode(['target_id' => $targetId]));
            $msg = "Super-admin <strong>" . htmlspecialchars($target['name'] ?? '') . "</strong> reativado.";
        }
    }
}

$admins = dbRows("SELECT * FROM super_admins ORDER BY active DESC, created_at ASC");

superadminHead('Super-Admins', 'admins.php');
?>
<div class="sa-wrap" style="max-width:860px">

    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-shield-halved" style="color:var(--yellow)"></i> Super-Admins</h1>
            <div class="sub">Contas com acesso total à plataforma PageQuiz</div>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:16px">
        <i class="fa-solid fa-circle-check"></i> <?= $msg ?>
    </div>
    <?php endif; ?>
    <?php if ($errors): ?>
    <div class="alert" style="background:rgba(239,68,68,.15);color:#fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <?php foreach ($errors as $e): ?><div><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Lista de super-admins -->
    <div class="card" style="border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:24px">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gray-100);font-size:13px;font-weight:700;color:var(--gray-700);text-transform:uppercase;letter-spacing:.5px">
            Contas existentes (<?= count($admins) ?>)
        </div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail / login</th>
                    <th>Status</th>
                    <th>Criado em</th>
                    <th style="text-align:right">Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $sa): ?>
            <tr>
                <td style="font-weight:600;color:var(--prussian)">
                    <?= htmlspecialchars($sa['name']) ?>
                    <?php if ((int)$sa['id'] === $meId): ?>
                    <span style="font-size:11px;background:rgba(168,85,247,.18);color:#d8b4fe;padding:1px 6px;border-radius:20px;margin-left:6px;font-weight:700">você</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;color:var(--gray-500)"><?= htmlspecialchars($sa['username']) ?></td>
                <td>
                    <?php if ($sa['active']): ?>
                    <span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:rgba(34,197,94,.15);color:#86efac">Ativo</span>
                    <?php else: ?>
                    <span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:rgba(239,68,68,.15);color:#fca5a5">Inativo</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--gray-400)"><?= date('d/m/Y', strtotime($sa['created_at'])) ?></td>
                <td style="text-align:right">
                    <?php if ((int)$sa['id'] !== $meId): ?>
                        <?php if ($sa['active']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Desativar <?= htmlspecialchars(addslashes($sa['name'])) ?>?')">
                            <input type="hidden" name="action" value="deactivate"/>
                            <input type="hidden" name="target_id" value="<?= $sa['id'] ?>"/>
                            <button type="submit" class="btn btn-xs danger" style="font-size:12px">
                                <i class="fa-solid fa-ban"></i> Desativar
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="reactivate"/>
                            <input type="hidden" name="target_id" value="<?= $sa['id'] ?>"/>
                            <button type="submit" class="btn btn-xs success" style="font-size:12px;background:rgba(34,197,94,.15);color:#86efac">
                                <i class="fa-solid fa-circle-check"></i> Reativar
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--gray-300)">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Formulário de criação -->
    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08);border-top:3px solid var(--yellow)">
        <h3 style="font-size:15px;color:var(--prussian);margin:0 0 20px">
            <i class="fa-solid fa-plus" style="color:var(--pacific)"></i> Adicionar super-admin
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="create"/>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Nome completo *</label>
                    <input type="text" name="name" required maxlength="100"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">E-mail (login) *</label>
                    <input type="email" name="username" required maxlength="120"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Senha *</label>
                    <input type="password" name="password" required minlength="8"
                           autocomplete="new-password"
                           placeholder="Mínimo 8 caracteres"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Use letras, números e símbolos para uma senha segura.</div>
                </div>
            </div>
            <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
                <i class="fa-solid fa-plus"></i> Criar super-admin
            </button>
        </form>
    </div>

</div>
<?php superadminFoot(); ?>
