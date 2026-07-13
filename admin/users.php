<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid     = adminCompanyId();
$adminId = adminId();

/* ── Toggle active ───────────────────────────────────────── */
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    dbExec("UPDATE users SET active = 1 - active WHERE id = ? AND company_id = ?", [$uid, $cid]);
    flash('Status do usuário atualizado.', 'success');
    redirect('users.php#tab-usuarios');
}

/* ── Delete user ─────────────────────────────────────────── */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    dbExec("DELETE FROM users WHERE id = ? AND company_id = ?", [$uid, $cid]);
    flash('Usuário excluído.', 'success');
    redirect('users.php#tab-usuarios');
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
    } elseif (dbRow("SELECT id FROM users WHERE email = ? AND company_id = ?", [$email, $cid])) {
        flash('Este e-mail já está cadastrado.', 'error');
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        dbExec("INSERT INTO users (name, email, password_hash, sector, active, company_id) VALUES (?,?,?,?,1,?)",
            [$name, $email, $hash, $sector, $cid]);
        flash("Usuário «{$name}» criado com sucesso!", 'success');
    }
    redirect('users.php#tab-usuarios');
}

/* ── Reset password ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pass'])) {
    $uid     = (int)($_POST['uid'] ?? 0);
    $newPass = $_POST['new_pass'] ?? '';
    if ($uid && strlen($newPass) >= 6) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        dbExec("UPDATE users SET password_hash = ? WHERE id = ? AND company_id = ?", [$hash, $uid, $cid]);
        flash('Senha redefinida com sucesso.', 'success');
    } else {
        flash('Senha mínima de 6 caracteres.', 'error');
    }
    redirect('users.php#tab-usuarios');
}

/* ── Convite: Criar ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invite'])) {
    $email  = strtolower(trim($_POST['email']  ?? ''));
    $sector = trim($_POST['sector'] ?? '');
    $ttl    = (int)($_POST['ttl'] ?? 48);
    $ttl    = in_array($ttl, [24, 48, 168]) ? $ttl : 48;

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('E-mail inválido.', 'error');
        redirect('users.php#tab-convites');
    }

    $token     = bin2hex(random_bytes(24));
    $expiresAt = date('Y-m-d H:i:s', time() + $ttl * 3600);
    dbExec("INSERT INTO invites (company_id, email, sector, token, expires_at, created_by) VALUES (?,?,?,?,?,?)",
        [$cid, $email ?: null, $sector, $token, $expiresAt, $adminId]);
    flash('Convite gerado com sucesso!', 'success');
    redirect('users.php?highlight=' . urlencode($token) . '#tab-convites');
}

/* ── Convite: Revogar ────────────────────────────────────── */
if (isset($_GET['revoke']) && is_numeric($_GET['revoke'])) {
    $iid = (int)$_GET['revoke'];
    dbExec("DELETE FROM invites WHERE id = ? AND company_id = ? AND used_at IS NULL", [$iid, $cid]);
    flash('Convite revogado.', 'success');
    redirect('users.php#tab-convites');
}

/* ── CSV Import ──────────────────────────────────────────── */
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modelo-usuarios.csv"');
    echo "\xEF\xBB\xBF";
    echo "nome;email;setor;senha\n";
    echo "Maria Silva;maria@empresa.com;Coleta;senha123\n";
    echo "João Santos;joao@empresa.com;TI;\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('Erro no upload do arquivo.', 'error');
        redirect('users.php#tab-importar');
    }
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash('Não foi possível ler o arquivo.', 'error');
        redirect('users.php#tab-importar');
    }
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle);
    $delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
    fgetcsv($handle, 0, $delim, '"', '');

    $imported = 0; $skipped = []; $generated = [];
    $row = 2; $db = getDB();
    while (($cols = fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        if (count($cols) < 2) { $skipped[] = "Linha $row: colunas insuficientes"; $row++; continue; }
        $name   = trim($cols[0] ?? '');
        $email  = strtolower(trim($cols[1] ?? ''));
        $sector = trim($cols[2] ?? '');
        $pass   = trim($cols[3] ?? '');
        if (!$name || !$email) { $skipped[] = "Linha $row: nome ou e-mail ausente"; $row++; continue; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped[] = "Linha $row: e-mail inválido ($email)"; $row++; continue; }
        if (dbRow("SELECT id FROM users WHERE email = ? AND company_id = ?", [$email, $cid])) { $skipped[] = "Linha $row: e-mail já cadastrado ($email)"; $row++; continue; }
        $tempPass = null;
        if (!$pass || strlen($pass) < 6) {
            $tempPass = substr(str_replace(['+','/','='], '', base64_encode(random_bytes(9))), 0, 10);
            $pass = $tempPass;
        }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        dbExec("INSERT INTO users (name, email, password_hash, sector, active, company_id) VALUES (?,?,?,?,1,?)", [$name, $email, $hash, $sector, $cid]);
        $imported++;
        if ($tempPass) $generated[$email] = $tempPass;
        $row++;
    }
    fclose($handle);

    if ($imported === 0) {
        flash('Nenhum usuário importado. Verifique o formato do arquivo.', 'error');
        redirect('users.php#tab-importar');
    }
    $_SESSION['_import_result'] = ['imported' => $imported, 'skipped' => $skipped, 'generated' => $generated];
    redirect('users.php?import_done=1#tab-importar');
}

/* ── Dados ───────────────────────────────────────────────── */
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$where  = ["u.company_id = ?"];
$params = [$cid];
if ($search) { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter === 'active')   $where[] = "u.active = 1";
if ($filter === 'inactive') $where[] = "u.active = 0";
$whereSql = 'WHERE ' . implode(' AND ', $where);

$users = dbRows("
    SELECT u.*, COUNT(DISTINCT p.id) AS quiz_count,
           SUM(CASE WHEN p.passed = 1 THEN 1 ELSE 0 END) AS pass_count,
           MAX(p.completed_at) AS last_quiz
    FROM users u
    LEFT JOIN participants p ON p.email = u.email
    $whereSql
    GROUP BY u.id
    ORDER BY u.created_at DESC
", $params);

$stats = [
    'total'    => dbRow("SELECT COUNT(*) AS c FROM users WHERE company_id = ?", [$cid])['c'],
    'active'   => dbRow("SELECT COUNT(*) AS c FROM users WHERE active = 1 AND company_id = ?", [$cid])['c'],
    'inactive' => dbRow("SELECT COUNT(*) AS c FROM users WHERE active = 0 AND company_id = ?", [$cid])['c'],
];

$highlight = trim($_GET['highlight'] ?? '');
$sectors   = dbRows("SELECT name FROM sectors WHERE company_id = ? ORDER BY name ASC", [$cid]);
$invites   = dbRows("
    SELECT i.*, a.name AS creator_name
    FROM invites i LEFT JOIN admins a ON a.id = i.created_by
    WHERE i.company_id = ? ORDER BY i.created_at DESC LIMIT 100
", [$cid]);

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$invBaseUrl = $scheme . '://' . $host;

$importResult = null;
if (isset($_GET['import_done']) && !empty($_SESSION['_import_result'])) {
    $importResult = $_SESSION['_import_result'];
    unset($_SESSION['_import_result']);
}

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
.inv-url{font-family:monospace;font-size:12px;background:#f0f7fa;border:1px solid #dce8ef;border-radius:6px;padding:6px 10px;word-break:break-all;color:#023047}
.inv-highlight{animation:inv-pulse 1.5s ease}
@keyframes inv-pulse{0%,100%{box-shadow:none}50%{box-shadow:0 0 0 4px rgba(33,158,188,.35)}}
@media(max-width:640px){.users-stats{grid-template-columns:1fr 1fr}}
</style>

<div class="admin-wrap">

<div class="flex items-center justify-between mb-24">
  <div>
    <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">Usuários &amp; Acesso</h1>
    <p class="text-muted" style="font-size:13px;margin-top:2px"><?= $stats['total'] ?> usuário(s) cadastrado(s)</p>
  </div>
  <button onclick="openModal('create-modal')" class="btn btn-primary">
    <i class="fa-solid fa-user-plus"></i> Novo Usuário
  </button>
</div>

<!-- KPIs -->
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

<!-- Tab nav -->
<div class="page-tabs" role="tablist">
    <button class="page-tab active" data-tab="usuarios" onclick="switchPageTab('usuarios','usr_tab')" role="tab" aria-selected="true">
        <i class="fa-solid fa-users"></i>
        <span class="tab-lbl">Usuários</span>
        <span class="tab-badge"><?= $stats['total'] ?></span>
    </button>
    <button class="page-tab" data-tab="convites" onclick="switchPageTab('convites','usr_tab')" role="tab">
        <i class="fa-solid fa-envelope-open-text"></i>
        <span class="tab-lbl">Convites</span>
        <span class="tab-badge"><?= count(array_filter($invites, fn($i) => !$i['used_at'] && strtotime($i['expires_at']) >= time())) ?></span>
    </button>
    <button class="page-tab" data-tab="importar" onclick="switchPageTab('importar','usr_tab')" role="tab">
        <i class="fa-solid fa-file-arrow-up"></i>
        <span class="tab-lbl">Importar CSV</span>
    </button>
</div>

<!-- ══ TAB: USUÁRIOS ════════════════════════════════════ -->
<div class="page-panel active" id="panel-usuarios" role="tabpanel">

    <!-- Filtros -->
    <div class="card" style="padding:16px 20px">
        <form method="GET" action="users.php">
            <div class="filter-bar">
                <input type="text" name="q" class="search-box" placeholder="Buscar por nome ou e-mail…" value="<?= e($search) ?>"/>
                <input type="hidden" name="filter" value="<?= e($filter) ?>"/>
                <a href="users.php?filter=all<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-btn<?= $filter==='all' ? ' active' : '' ?>">Todos</a>
                <a href="users.php?filter=active<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-btn<?= $filter==='active' ? ' active' : '' ?>">Ativos</a>
                <a href="users.php?filter=inactive<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-btn<?= $filter==='inactive' ? ' active' : '' ?>">Inativos</a>
                <button type="submit" class="btn btn-secondary" style="padding:7px 16px;font-size:13px">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar
                </button>
            </div>
        </form>
    </div>

    <div class="card">
    <?php if (empty($users)): ?>
        <p style="text-align:center;padding:48px;color:var(--gray-400);font-size:14px">Nenhum usuário encontrado.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Usuário</th><th>Setor</th><th>Quizzes</th><th>Aprovações</th><th>Último acesso</th><th>Status</th><th style="text-align:right">Ações</th>
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
                <td style="font-size:12px;color:var(--gray-400)"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?></td>
                <td><?= $u['active'] ? '<span class="badge badge-green">Ativo</span>' : '<span class="badge badge-red">Inativo</span>' ?></td>
                <td>
                    <div class="row-actions">
                        <button onclick="openResetModal(<?= $u['id'] ?>, '<?= e($u['name']) ?>')" class="row-action" title="Redefinir senha"><i class="fa-solid fa-key"></i></button>
                        <a href="users.php?toggle=<?= $u['id'] ?>" class="row-action <?= $u['active'] ? 'row-action--danger' : 'row-action--success' ?>" title="<?= $u['active'] ? 'Desativar' : 'Ativar' ?>">
                            <i class="fa-solid <?= $u['active'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                        </a>
                        <a href="#" onclick="confirmDelete(<?= $u['id'] ?>, '<?= e($u['name']) ?>')" class="row-action row-action--delete" title="Excluir">
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
</div><!-- /panel-usuarios -->

<!-- ══ TAB: CONVITES ════════════════════════════════════ -->
<div class="page-panel" id="panel-convites" role="tabpanel">

    <div class="card" style="margin-bottom:24px">
        <div class="card-header"><h2><i class="fa-solid fa-plus"></i> Novo Convite</h2></div>
        <form method="POST" style="display:grid;grid-template-columns:2fr 1.5fr 1fr auto;gap:12px;align-items:end">
            <input type="hidden" name="create_invite" value="1"/>
            <div>
                <label class="form-label">E-mail <span style="font-weight:400;color:var(--gray-400)">(opcional — deixe vazio para link aberto)</span></label>
                <input class="form-control" type="email" name="email" placeholder="colaborador@empresa.com" maxlength="180"/>
            </div>
            <div>
                <label class="form-label">Setor <span style="font-weight:400;color:var(--gray-400)">(opcional)</span></label>
                <select class="form-control" name="sector">
                    <option value="">— Nenhum —</option>
                    <?php foreach ($sectors as $s): ?>
                    <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Validade</label>
                <select class="form-control" name="ttl">
                    <option value="24">24 horas</option>
                    <option value="48" selected>48 horas</option>
                    <option value="168">7 dias</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="white-space:nowrap">
                    <i class="fa-solid fa-paper-plane"></i> Gerar Link
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h2><i class="fa-solid fa-list"></i> Convites Gerados</h2></div>
        <?php if (empty($invites)): ?>
        <p style="text-align:center;padding:48px;color:var(--gray-400);font-size:14px">Nenhum convite gerado ainda.</p>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Link de Acesso</th><th>E-mail</th><th>Setor</th><th>Expira em</th><th>Status</th><th>Ação</th></tr>
            </thead>
            <tbody>
            <?php
            $now = time();
            foreach ($invites as $inv):
                $expired = strtotime($inv['expires_at']) < $now;
                $used    = !empty($inv['used_at']);
                $isNew   = $highlight && $highlight === $inv['token'];
                $invUrl  = $invBaseUrl . '/user/invite.php?token=' . urlencode($inv['token']);
            ?>
            <tr id="inv-<?= $inv['id'] ?>" class="<?= $isNew ? 'inv-highlight' : '' ?>">
                <td style="max-width:320px">
                    <div class="inv-url"><?= htmlspecialchars($invUrl) ?></div>
                    <button type="button" onclick="copyInv(this,'<?= htmlspecialchars($invUrl) ?>')"
                            class="btn btn-secondary btn-sm" style="margin-top:6px;font-size:11px">
                        <i class="fa-solid fa-copy"></i> Copiar link
                    </button>
                </td>
                <td style="font-size:13px"><?= $inv['email'] ? e($inv['email']) : '<span style="color:var(--gray-300)">Link aberto</span>' ?></td>
                <td><?= $inv['sector'] ? '<span class="badge badge-blue">'.e($inv['sector']).'</span>' : '<span style="color:var(--gray-300);font-size:12px">—</span>' ?></td>
                <td style="font-size:12px;color:var(--gray-400)"><?= date('d/m/Y H:i', strtotime($inv['expires_at'])) ?></td>
                <td>
                    <?php if ($used): ?>
                        <span class="badge badge-green">Usado</span>
                    <?php elseif ($expired): ?>
                        <span class="badge badge-red">Expirado</span>
                    <?php else: ?>
                        <span class="badge badge-blue">Ativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$used && !$expired): ?>
                    <a href="users.php?revoke=<?= $inv['id'] ?>" class="row-action row-action--danger" title="Revogar"
                       onclick="return confirm('Revogar este convite?')">
                        <i class="fa-solid fa-ban"></i>
                    </a>
                    <?php else: ?>
                    <span style="color:var(--gray-200)">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div><!-- /panel-convites -->

<!-- ══ TAB: IMPORTAR CSV ════════════════════════════════ -->
<div class="page-panel" id="panel-importar" role="tabpanel">

    <?php if ($importResult): ?>
    <div class="card" style="border-left:4px solid var(--green);margin-bottom:20px">
        <div class="card-header"><h2 style="color:var(--green)"><i class="fa-solid fa-circle-check"></i> Importação concluída</h2></div>
        <p style="font-size:14px;margin-bottom:16px">
            <strong><?= $importResult['imported'] ?></strong> usuário(s) importado(s).
            <?= count($importResult['skipped']) ? '<strong style="color:var(--orange)">'.count($importResult['skipped']).'</strong> linha(s) ignorada(s).' : '' ?>
        </p>
        <?php if (!empty($importResult['generated'])): ?>
        <div style="margin-bottom:16px">
            <div class="form-label" style="margin-bottom:8px"><i class="fa-solid fa-key"></i> Senhas temporárias geradas:</div>
            <div class="table-wrap">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="background:var(--gray-100)">
                    <th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--gray-200)">E-mail</th>
                    <th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--gray-200)">Senha temporária</th>
                </tr></thead>
                <tbody>
                <?php foreach ($importResult['generated'] as $em => $pw): ?>
                <tr style="border-bottom:1px solid var(--gray-100)">
                    <td style="padding:8px 12px"><?= htmlspecialchars($em) ?></td>
                    <td style="padding:8px 12px"><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?= htmlspecialchars($pw) ?></code></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p style="font-size:12px;color:var(--gray-400);margin-top:8px"><i class="fa-solid fa-triangle-exclamation"></i> Anote as senhas antes de sair — elas não serão exibidas novamente.</p>
        </div>
        <?php endif; ?>
        <?php if (!empty($importResult['skipped'])): ?>
        <details style="margin-top:8px">
            <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--gray-600)">Ver linhas ignoradas (<?= count($importResult['skipped']) ?>)</summary>
            <ul style="margin:10px 0 0 16px;font-size:12px;color:var(--gray-500)">
                <?php foreach ($importResult['skipped'] as $sk): ?><li><?= htmlspecialchars($sk) ?></li><?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h2><i class="fa-solid fa-table-list"></i> Formato do CSV</h2></div>
        <div class="alert alert-info" style="margin-bottom:16px">
            Separador ponto-e-vírgula <code>;</code> (ou vírgula <code>,</code>). Primeira linha = cabeçalho (ignorada). <strong>Senha opcional</strong> — se omitida, gerada automaticamente.
        </div>
        <div class="table-wrap" style="margin-bottom:16px">
            <table>
                <thead><tr><th>Col.</th><th>Campo</th><th>Obrigatório</th><th>Exemplo</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td>Nome completo</td><td><span class="badge badge-red">Sim</span></td><td>Maria Silva</td></tr>
                    <tr><td>2</td><td>E-mail</td><td><span class="badge badge-red">Sim</span></td><td>maria@empresa.com</td></tr>
                    <tr><td>3</td><td>Setor</td><td><span class="badge badge-gray">Não</span></td><td>Coleta</td></tr>
                    <tr><td>4</td><td>Senha</td><td><span class="badge badge-gray">Não</span></td><td>senha123</td></tr>
                </tbody>
            </table>
        </div>
        <a href="users.php?template=1" class="btn btn-outline btn-sm"><i class="fa-solid fa-download"></i> Baixar Modelo CSV</a>
    </div>

    <div class="card">
        <div class="card-header"><h2><i class="fa-solid fa-upload"></i> Enviar Arquivo</h2></div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Arquivo CSV *</label>
                <input class="form-control" type="file" name="csv_file" accept=".csv,.txt" required/>
                <div class="form-hint">Arquivo .csv com separador ponto-e-vírgula ou vírgula. Máx. 2 MB.</div>
            </div>
            <div class="flex gap-8">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import"></i> Importar Usuários</button>
            </div>
        </form>
    </div>
</div><!-- /panel-importar -->

</div><!-- /admin-wrap -->

<!-- Modal: Criar usuário -->
<div class="modal-overlay" id="create-modal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-user-plus"></i> Novo Usuário</h3>
        <form method="POST" action="users.php">
            <input type="hidden" name="create_user" value="1"/>
            <div class="modal-form-group"><label>Nome completo *</label><input type="text" name="name" required placeholder="Nome do usuário" maxlength="120"/></div>
            <div class="modal-form-group"><label>E-mail *</label><input type="email" name="email" required placeholder="email@empresa.com" maxlength="180"/></div>
            <div class="modal-form-group"><label>Setor</label><input type="text" name="sector" placeholder="Ex: Coleta, TI, RH…" maxlength="80"/></div>
            <div class="modal-form-group"><label>Senha inicial *</label><input type="text" name="password" required placeholder="Mínimo 6 caracteres" maxlength="80"/></div>
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
            <div class="modal-form-group"><label>Nova senha *</label><input type="text" name="new_pass" required placeholder="Mínimo 6 caracteres" maxlength="80"/></div>
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
        <div class="confirm-msg">O usuário <strong id="del-name"></strong> será removido permanentemente.</div>
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
document.querySelectorAll('.modal-overlay,.confirm-backdrop').forEach(el => {
    el.addEventListener('click', function(e){ if(e.target === this) this.classList.remove('open'); });
});

function copyInv(btn, url) {
    const ok = () => {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
        btn.style.background = '#dcfce7'; btn.style.color = '#16a34a';
        setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; btn.style.color = ''; }, 2000);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(ok);
    } else {
        const ta = document.createElement('textarea');
        ta.value = url; ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
        document.body.appendChild(ta); ta.focus(); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta); ok();
    }
}

<?php if ($highlight): ?>
setTimeout(function(){
    var el = document.querySelector('.inv-highlight');
    if (el) el.scrollIntoView({behavior:'smooth', block:'center'});
}, 300);
<?php endif; ?>

initPageTabs('usuarios', 'usr_tab');
</script>
<?php adminFoot(); ?>
