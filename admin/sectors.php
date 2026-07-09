<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid = adminCompanyId();

/* ── Add Sector ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sector'])) {
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        flash('O nome do setor é obrigatório.', 'error');
    } else {
        $exists = dbRow("SELECT id FROM sectors WHERE name = ? AND company_id = ?", [$name, $cid]);
        if ($exists) {
            flash("O setor «{$name}» já existe.", 'error');
        } else {
            dbExec("INSERT INTO sectors (name, company_id) VALUES (?,?)", [$name, $cid]);
            flash("Setor «{$name}» criado com sucesso!", 'success');
        }
    }
    redirect('sectors.php');
}

/* ── Rename Sector ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_sector'])) {
    $id      = (int)$_POST['sector_id'];
    $newName = trim($_POST['new_name'] ?? '');
    if (!$newName) {
        flash('O novo nome não pode ser vazio.', 'error');
    } else {
        $old = dbRow("SELECT name FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
        if ($old) {
            dbExec("UPDATE quizzes SET sector = ? WHERE sector = ? AND company_id = ?", [$newName, $old['name'], $cid]);
            dbExec("UPDATE sectors SET name = ? WHERE id = ? AND company_id = ?", [$newName, $id, $cid]);
            flash("Setor renomeado de «{$old['name']}» para «{$newName}». Todos os quizzes associados foram atualizados.", 'success');
        }
    }
    redirect('sectors.php');
}

/* ── Delete Sector ───────────────────────────────────────── */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $sector = dbRow("SELECT * FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
    if ($sector) {
        $quizCount = dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE sector = ? AND company_id = ?", [$sector['name'], $cid])['c'];
        if ($quizCount > 0) {
            flash("Não é possível excluir o setor «{$sector['name']}» pois há {$quizCount} quiz(es) associado(s). Reatribua os quizzes primeiro.", 'error');
        } else {
            dbExec("DELETE FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
            flash("Setor «{$sector['name']}» excluído.", 'success');
        }
    }
    redirect('sectors.php');
}

/* ── Force Delete Sector (reassign quizzes to Geral) ──────── */
if (isset($_GET['force_delete']) && is_numeric($_GET['force_delete'])) {
    $id = (int)$_GET['force_delete'];
    $sector = dbRow("SELECT * FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
    if ($sector && $sector['name'] !== 'Geral') {
        dbExec("UPDATE quizzes SET sector = 'Geral' WHERE sector = ? AND company_id = ?", [$sector['name'], $cid]);
        dbExec("DELETE FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
        dbExec("INSERT OR IGNORE INTO sectors (name, company_id) VALUES ('Geral', ?)", [$cid]);
        flash("Setor «{$sector['name']}» excluído. Quizzes reassociados para «Geral».", 'success');
    }
    redirect('sectors.php');
}

$sectors = dbRows("
    SELECT s.*, COUNT(q.id) AS quiz_count
    FROM sectors s
    LEFT JOIN quizzes q ON q.sector = s.name AND q.company_id = s.company_id
    WHERE s.company_id = ?
    GROUP BY s.id
    ORDER BY s.name ASC
", [$cid]);

adminHead('Setores', 'sectors.php');
?>
<div class="admin-wrap" style="max-width:900px">



<div class="flex items-center justify-between mb-24">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">
            <i class="fa-solid fa-sitemap" style="color:var(--blue);margin-right:8px"></i>Setores
        </h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px"><?= count($sectors) ?> setor(es) cadastrado(s)</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<!-- Add Sector -->
<div class="card">
    <div class="card-header"><h2><i class="fa-solid fa-plus" style="color:var(--green)"></i> Novo Setor</h2></div>
    <form method="post">
        <input type="hidden" name="add_sector" value="1"/>
        <div class="form-group">
            <label class="form-label">Nome do Setor</label>
            <input class="form-control" type="text" name="name" required placeholder="Ex: Laboratório, Coleta, TI…" autofocus/>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Criar Setor</button>
    </form>
</div>

<!-- Rename Form (shown via JS) -->
<div class="card" id="rename-card" style="display:none">
    <div class="card-header">
        <h2><i class="fa-solid fa-pen-to-square" style="color:var(--blue)"></i> Renomear Setor</h2>
    </div>
    <form method="post">
        <input type="hidden" name="rename_sector" value="1"/>
        <input type="hidden" name="sector_id" id="rename-id"/>
        <div class="form-group">
            <label class="form-label">Nome Atual</label>
            <input class="form-control" type="text" id="rename-old" disabled style="background:var(--gray-50)"/>
        </div>
        <div class="form-group">
            <label class="form-label">Novo Nome <span style="color:var(--blue);font-size:11px">(todos os quizzes serão atualizados)</span></label>
            <input class="form-control" type="text" name="new_name" id="rename-new" required placeholder="Novo nome do setor"/>
        </div>
        <div class="flex gap-8">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirmar Renomeação</button>
            <button type="button" class="btn btn-outline" onclick="closeRename()">Cancelar</button>
        </div>
    </form>
</div>

</div><!-- /grid -->

<!-- Sectors Table -->
<div class="card" style="margin-top:20px">
    <div class="card-header"><h2><i class="fa-solid fa-list" style="color:var(--blue)"></i> Setores Cadastrados</h2></div>
    <?php if (empty($sectors)): ?>
    <p style="text-align:center;padding:40px;color:var(--gray-400)">Nenhum setor cadastrado ainda.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Setor</th>
                    <th>Quizzes</th>
                    <th>Criado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sectors as $s): ?>
            <tr>
                <td>
                    <div style="font-weight:700;font-size:14px">
                        <i class="fa-solid fa-tag" style="color:var(--blue);margin-right:6px;font-size:12px"></i>
                        <?= e($s['name']) ?>
                        <?php if ($s['name'] === 'Geral'): ?>
                        <span class="badge badge-blue" style="margin-left:6px;font-size:10px">Padrão</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php if ($s['quiz_count'] > 0): ?>
                    <a href="quizzes.php" style="color:var(--blue);font-weight:700"><?= $s['quiz_count'] ?> quiz(es)</a>
                    <?php else: ?>
                    <span style="color:var(--gray-300)">—</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--gray-400)"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                <td>
                    <div class="flex gap-8">
                        <button type="button" class="btn btn-outline btn-sm"
                            onclick="openRename(<?= $s['id'] ?>, '<?= e(addslashes($s['name'])) ?>')">
                            <i class="fa-solid fa-pen-to-square"></i> Renomear
                        </button>
                        <?php if ($s['name'] !== 'Geral'): ?>
                            <?php if ($s['quiz_count'] == 0): ?>
                            <a href="?delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Excluir setor «<?= e($s['name']) ?>»?')">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                            <?php else: ?>
                            <a href="?force_delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Excluir setor «<?= e($s['name']) ?>» e mover os <?= $s['quiz_count'] ?> quiz(es) para «Geral»?')">
                                <i class="fa-solid fa-triangle-exclamation"></i> Forçar
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                        <span style="font-size:12px;color:var(--gray-300)">Protegido</span>
                        <?php endif; ?>
                    </div>
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
function openRename(id, currentName) {
    document.getElementById('rename-id').value  = id;
    document.getElementById('rename-old').value = currentName;
    document.getElementById('rename-new').value = currentName;
    document.getElementById('rename-card').style.display = 'block';
    document.getElementById('rename-new').focus();
    document.getElementById('rename-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function closeRename() {
    document.getElementById('rename-card').style.display = 'none';
}
</script>
<?php adminFoot(); ?>
