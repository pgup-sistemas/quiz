<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

/* ── Toggle active ───────────────────────────────────────── */
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    dbExec("UPDATE users SET active = 1 - active WHERE id = ?", [$uid]);
    flash('Status do usuário atualizado.', 'success');
    redirect('users.php');
}

/* ── Delete user ─────────────────────────────────────────── */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    dbExec("DELETE FROM users WHERE id = ?", [$uid]);
    flash('Usuário excluído.', 'success');
    redirect('users.php');
}

/* ── Create user ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name   = trim($_POST['name']   ?? '');
    $email  = strtolower(trim($_POST['email']  ?? ''));
    $sector = trim($_POST['sector'] ?? '');
    $pass   = $_POST['password']    ?? '';

    if (!$name || !$email || !$pass) {
        flash('Preencha nome, e-mail e senha.', 'error');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('E-mail inválido.', 'error');
    } elseif (strlen($pass) < 6) {
        flash('Senha mínima de 6 caracteres.', 'error');
    } elseif (dbRow("SELECT id FROM users WHERE email = ?", [$email])) {
        flash('Este e-mail já está cadastrado.', 'error');
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        dbExec("INSERT INTO users (name, email, password_hash, sector, active) VALUES (?,?,?,?,1)",
            [$name, $email, $hash, $sector]);
        flash("Usuário «{$name}» criado com sucesso!", 'success');
    }
    redirect('users.php');
}

/* ── Reset password ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pass'])) {
    $uid     = (int)($_POST['uid'] ?? 0);
    $newPass = $_POST['new_pass'] ?? '';
    if ($uid && strlen($newPass) >= 6) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        dbExec("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $uid]);
        flash('Senha redefinida com sucesso.', 'success');
    } else {
        flash('Senha mínima de 6 caracteres.', 'error');
    }
    redirect('users.php');
}

/* ── Search / Filter ─────────────────────────────────────── */
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$where  = [];
$params = [];
if ($search) {
    $where[]  = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter === 'active')   { $where[] = "u.active = 1"; }
if ($filter === 'inactive') { $where[] = "u.active = 0"; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = dbRows("
    SELECT u.*,
           COUNT(DISTINCT p.id)                          AS quiz_count,
           SUM(CASE WHEN p.passed = 1 THEN 1 ELSE 0 END) AS pass_count,
           MAX(p.completed_at)                           AS last_quiz
    FROM users u
    LEFT JOIN participants p ON p.email = u.email
    $whereSql
    GROUP BY u.id
    ORDER BY u.created_at DESC
", $params);

$stats = [
    'total'    => dbRow("SELECT COUNT(*) AS c FROM users")['c'],
    'active'   => dbRow("SELECT COUNT(*) AS c FROM users WHERE active = 1")['c'],
    'inactive' => dbRow("SELECT COUNT(*) AS c FROM users WHERE active = 0")['c'],
];

adminHead('Usuários', 'users.php');
?>
<style>
.users-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
.u-stat{background:#fff;border:1px solid var(--gray-100);border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:14px}
.u-stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.u-stat-num{font-size:22px;font-weight:800;color:var(--gray-800);line-height:1}
.u-stat-lbl{font-size:12px;color:var(--gray-400);margin-top:2px}
.filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.filter-btn{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1.5px solid var(--gray-200);background:#fff;color:var(--gray-500);cursor:pointer;text-decoration:none;transition:.15s}
.filter-btn.active,.filter-btn:hover{border-color:var(--pacific);color:var(--pacific);background:#f0f7fa}
.search-box{flex:1;min-width:200px;padding:8px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:.2s}
.search-box:focus{border-color:var(--pacific)}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(2,48,71,.5);z-index:600;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(2,48,71,.2)}
.modal-box h3{font-size:16px;font-weight:700;color:var(--gray-800);margin:0 0 20px;display:flex;align-items:center;gap:8px}
.modal-box h3 i{color:var(--pacific)}
.modal-form-group{margin-bottom:14px}
.modal-form-group label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-500);margin-bottom:5px}
.modal-form-group input,.modal-form-group select{width:100%;padding:10px 12px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:.2s}
.modal-form-group input:focus,.modal-form-group select:focus{border-color:var(--pacific)}
.modal-actions{display:flex;gap:10px;margin-top:20px}
.modal-actions .btn{flex:1}
@media(max-width:640px){.users-stats{grid-template-columns:1fr 1fr}}
</style>

<div class="admin-wrap">

<div class="flex items-center justify-between mb-24">
  <div>
    <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">Usuários</h1>
    <p class="text-muted" style="font-size:13px;margin-top:2px"><?= $stats['total'] ?> usuário(s) cadastrados</p>
  </div>
  <button onclick="openModal('create-modal')" class="btn btn-primary">
    <i class="fa-solid fa-user-plus"></i> Novo Usuário
  </button>
</div>

<!-- Stats -->
<div class="users-stats">
  <div class="u-stat">
    <div class="u-stat-icon" style="background:#e0f2fe;color:var(--pacific)"><i class="fa-solid fa-users"></i></div>
    <div><div class="u-stat-num"><?= $stats['total'] ?></div><div class="u-stat-lbl">Total</div></div>
  </div>
  <div class="u-stat">
    <div class="u-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fa-solid fa-circle-check"></i></div>
    <div><div class="u-stat-num"><?= $stats['active'] ?></div><div class="u-stat-lbl">Ativos</div></div>
  </div>
  <div class="u-stat">
    <div class="u-stat-icon" style="background:#fef3c7;color:#d97706"><i class="fa-solid fa-circle-xmark"></i></div>
    <div><div class="u-stat-num"><?= $stats['inactive'] ?></div><div class="u-stat-lbl">Inativos</div></div>
  </div>
</div>

<!-- Filtros e busca -->
<div class="card" style="padding:16px 20px">
  <form method="GET" action="users.php">
    <div class="filter-bar">
      <input type="text" name="q" class="search-box" placeholder="Buscar por nome ou e-mail…"
             value="<?= e($search) ?>"/>
      <input type="hidden" name="filter" value="<?= e($filter) ?>"/>
      <a href="users.php?filter=all<?= $search ? '&q='.urlencode($search) : '' ?>"
         class="filter-btn<?= $filter === 'all' ? ' active' : '' ?>">Todos</a>
      <a href="users.php?filter=active<?= $search ? '&q='.urlencode($search) : '' ?>"
         class="filter-btn<?= $filter === 'active' ? ' active' : '' ?>">Ativos</a>
      <a href="users.php?filter=inactive<?= $search ? '&q='.urlencode($search) : '' ?>"
         class="filter-btn<?= $filter === 'inactive' ? ' active' : '' ?>">Inativos</a>
      <button type="submit" class="btn btn-secondary" style="padding:7px 16px;font-size:13px">
        <i class="fa-solid fa-magnifying-glass"></i> Buscar
      </button>
    </div>
  </form>
</div>

<!-- Tabela -->
<div class="card">
<?php if (empty($users)): ?>
  <p style="text-align:center;padding:48px;color:var(--gray-400);font-size:14px">
    Nenhum usuário encontrado.
  </p>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Usuário</th>
        <th>Setor</th>
        <th>Quizzes</th>
        <th>Aprovações</th>
        <th>Último acesso</th>
        <th>Status</th>
        <th style="text-align:right">Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:34px;height:34px;background:var(--pacific);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0">
            <?= strtoupper(substr($u['name'],0,2)) ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;color:var(--gray-800)"><?= e($u['name']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= e($u['email']) ?></div>
          </div>
        </div>
      </td>
      <td><?= $u['sector'] ? '<span class="badge badge-blue">'.e($u['sector']).'</span>' : '<span style="color:var(--gray-300);font-size:12px">—</span>' ?></td>
      <td style="font-weight:600"><?= $u['quiz_count'] ?></td>
      <td><?= $u['quiz_count'] > 0 ? '<span style="color:#16a34a;font-weight:600">'.$u['pass_count'].'</span> / '.$u['quiz_count'] : '<span style="color:var(--gray-300)">—</span>' ?></td>
      <td style="font-size:12px;color:var(--gray-400)">
        <?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?>
      </td>
      <td>
        <?php if ($u['active']): ?>
          <span class="badge badge-green">Ativo</span>
        <?php else: ?>
          <span class="badge badge-red">Inativo</span>
        <?php endif; ?>
      </td>
      <td>
        <div class="row-actions">
          <button onclick="openResetModal(<?= $u['id'] ?>, '<?= e($u['name']) ?>')"
                  class="row-action" title="Redefinir senha"><i class="fa-solid fa-key"></i></button>
          <a href="users.php?toggle=<?= $u['id'] ?>" class="row-action <?= $u['active'] ? 'row-action--danger' : 'row-action--success' ?>"
             title="<?= $u['active'] ? 'Desativar' : 'Ativar' ?>">
            <i class="fa-solid <?= $u['active'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
          </a>
          <a href="#" onclick="confirmDelete(<?= $u['id'] ?>, '<?= e($u['name']) ?>')"
             class="row-action row-action--delete" title="Excluir">
            <i class="fa-solid fa-trash"></i>
          </a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
</div>

</div><!-- /admin-wrap -->

<!-- Modal: Criar usuário -->
<div class="modal-overlay" id="create-modal">
  <div class="modal-box">
    <h3><i class="fa-solid fa-user-plus"></i> Novo Usuário</h3>
    <form method="POST" action="users.php">
      <input type="hidden" name="create_user" value="1"/>
      <div class="modal-form-group">
        <label>Nome completo *</label>
        <input type="text" name="name" required placeholder="Nome do usuário" maxlength="120"/>
      </div>
      <div class="modal-form-group">
        <label>E-mail *</label>
        <input type="email" name="email" required placeholder="email@empresa.com" maxlength="180"/>
      </div>
      <div class="modal-form-group">
        <label>Setor</label>
        <input type="text" name="sector" placeholder="Ex: Coleta, TI, RH…" maxlength="80"/>
      </div>
      <div class="modal-form-group">
        <label>Senha inicial *</label>
        <input type="text" name="password" required placeholder="Mínimo 6 caracteres" maxlength="80"/>
      </div>
      <div class="modal-actions">
        <button type="button" onclick="closeModal('create-modal')" class="btn btn-secondary">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Criar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Redefinir senha -->
<div class="modal-overlay" id="reset-modal">
  <div class="modal-box">
    <h3><i class="fa-solid fa-key"></i> Redefinir Senha — <span id="reset-name"></span></h3>
    <form method="POST" action="users.php">
      <input type="hidden" name="reset_pass" value="1"/>
      <input type="hidden" name="uid" id="reset-uid"/>
      <div class="modal-form-group">
        <label>Nova senha *</label>
        <input type="text" name="new_pass" required placeholder="Mínimo 6 caracteres" maxlength="80"/>
      </div>
      <div class="modal-actions">
        <button type="button" onclick="closeModal('reset-modal')" class="btn btn-secondary">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Redefinir</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Confirmar exclusão -->
<div class="confirm-backdrop" id="del-modal">
  <div class="confirm-modal">
    <div class="confirm-icon"><i class="fa-solid fa-trash" style="color:var(--red)"></i></div>
    <div class="confirm-title">Excluir usuário?</div>
    <div class="confirm-msg">O usuário <strong id="del-name"></strong> será removido permanentemente. O histórico de participações permanece no sistema.</div>
    <div class="confirm-actions">
      <button class="btn btn-secondary" onclick="closeDelModal()">Cancelar</button>
      <a id="del-link" href="#" class="btn" style="background:var(--red);color:#fff">Excluir</a>
    </div>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }

function openResetModal(uid, name){
  document.getElementById('reset-uid').value = uid;
  document.getElementById('reset-name').textContent = name;
  openModal('reset-modal');
}

function confirmDelete(uid, name){
  document.getElementById('del-name').textContent = name;
  document.getElementById('del-link').href = 'users.php?delete=' + uid;
  document.getElementById('del-modal').classList.add('open');
}
function closeDelModal(){ document.getElementById('del-modal').classList.remove('open'); }

// Fechar modais clicando fora
document.querySelectorAll('.modal-overlay,.confirm-backdrop').forEach(el => {
  el.addEventListener('click', function(e){ if(e.target === this) this.classList.remove('open'); });
});
</script>

<?php adminFoot(); ?>
