<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid     = adminCompanyId();
$adminId = adminId();

// ── Criar convite ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invite'])) {
    $email  = strtolower(trim($_POST['email']  ?? ''));
    $sector = trim($_POST['sector'] ?? '');
    $ttl    = (int)($_POST['ttl'] ?? 48); // horas
    $ttl    = in_array($ttl, [24, 48, 168]) ? $ttl : 48;

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('E-mail inválido.', 'error');
        redirect('invites.php');
    }

    $token     = bin2hex(random_bytes(24));
    $expiresAt = date('Y-m-d H:i:s', time() + $ttl * 3600);

    dbExec(
        "INSERT INTO invites (company_id, email, sector, token, expires_at, created_by) VALUES (?,?,?,?,?,?)",
        [$cid, $email ?: null, $sector, $token, $expiresAt, $adminId]
    );
    flash('Convite gerado com sucesso!', 'success');
    redirect('users.php?highlight=' . urlencode($token) . '#tab-convites');
}

// ── Revogar convite ───────────────────────────────────────────────────────────
if (isset($_GET['revoke']) && is_numeric($_GET['revoke'])) {
    $iid = (int)$_GET['revoke'];
    dbExec("DELETE FROM invites WHERE id = ? AND company_id = ? AND used_at IS NULL", [$iid, $cid]);
    flash('Convite revogado.', 'success');
    redirect('users.php#tab-convites');
}

$highlight = trim($_GET['highlight'] ?? '');

$sectors = dbRows("SELECT name FROM sectors WHERE company_id = ? ORDER BY name ASC", [$cid]);
$invites = dbRows("
    SELECT i.*, a.name AS creator_name
    FROM invites i
    LEFT JOIN admins a ON a.id = i.created_by
    WHERE i.company_id = ?
    ORDER BY i.created_at DESC
    LIMIT 100
", [$cid]);

// Monta URL base
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

adminHead('Convites', 'users.php');
?>
<style>
.inv-url{font-family:monospace;font-size:12px;background:#f0f7fa;border:1px solid #dce8ef;border-radius:6px;padding:6px 10px;word-break:break-all;color:#023047}
.inv-highlight{animation:inv-pulse 1.5s ease}
@keyframes inv-pulse{0%,100%{box-shadow:none}50%{box-shadow:0 0 0 4px rgba(33,158,188,.35)}}
.ttl-select{padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;background:#fff;outline:none}
.ttl-select:focus{border-color:var(--pacific)}
</style>

<div class="admin-wrap">

<div class="flex items-center justify-between mb-24">
  <div>
    <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">
      <i class="fa-solid fa-envelope-open-text"></i> Convites
    </h1>
    <p class="text-muted" style="font-size:13px;margin-top:2px">
      Gere links de convite para colaboradores criarem suas contas
    </p>
  </div>
  <a href="users.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Usuários</a>
</div>

<!-- Formulário de novo convite -->
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
      <select class="ttl-select" name="ttl" style="width:100%">
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

<!-- Lista de convites -->
<div class="card">
  <div class="card-header"><h2><i class="fa-solid fa-list"></i> Convites Gerados</h2></div>

  <?php if (empty($invites)): ?>
  <p style="text-align:center;padding:48px;color:var(--gray-400);font-size:14px">
    Nenhum convite gerado ainda.
  </p>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Link de Acesso</th>
        <th>E-mail</th>
        <th>Setor</th>
        <th>Expira em</th>
        <th>Status</th>
        <th>Ação</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $now = time();
    foreach ($invites as $inv):
        $expired = strtotime($inv['expires_at']) < $now;
        $used    = !empty($inv['used_at']);
        $isNew   = $highlight && $highlight === $inv['token'];
        $url     = $baseUrl . '/user/invite.php?token=' . urlencode($inv['token']);
    ?>
    <tr id="inv-<?= $inv['id'] ?>" class="<?= $isNew ? 'inv-highlight' : '' ?>">
      <td style="max-width:340px">
        <div class="inv-url"><?= htmlspecialchars($url) ?></div>
        <button type="button" onclick="copyInv(this,'<?= htmlspecialchars($url) ?>')"
                class="btn btn-secondary btn-sm" style="margin-top:6px;font-size:11px">
          <i class="fa-solid fa-copy"></i> Copiar link
        </button>
      </td>
      <td style="font-size:13px"><?= $inv['email'] ? e($inv['email']) : '<span style="color:var(--gray-300)">Link aberto</span>' ?></td>
      <td><?= $inv['sector'] ? '<span class="badge badge-blue">'.e($inv['sector']).'</span>' : '<span style="color:var(--gray-300);font-size:12px">—</span>' ?></td>
      <td style="font-size:12px;color:var(--gray-400)">
        <?= date('d/m/Y H:i', strtotime($inv['expires_at'])) ?>
      </td>
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
        <a href="invites.php?revoke=<?= $inv['id'] ?>"
           class="row-action row-action--danger"
           title="Revogar"
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

</div>

<script>
function copyInv(btn, url) {
    navigator.clipboard.writeText(url).then(function() {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
        btn.style.background = '#dcfce7';
        btn.style.color = '#16a34a';
        setTimeout(function() {
            btn.innerHTML = orig;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    });
}
<?php if ($highlight): ?>
setTimeout(function(){
    var el = document.querySelector('.inv-highlight');
    if (el) el.scrollIntoView({behavior:'smooth', block:'center'});
}, 200);
<?php endif; ?>
</script>

<?php adminFoot(); ?>
