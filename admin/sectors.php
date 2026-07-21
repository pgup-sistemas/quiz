<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid = adminCompanyId();

/* ── Add Sector ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sector'])) {
    requireCsrf();
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
    requireCsrf();
    $id      = (int)$_POST['sector_id'];
    $newName = trim($_POST['new_name'] ?? '');
    if (!$newName) {
        flash('O novo nome não pode ser vazio.', 'error');
    } else {
        $old = dbRow("SELECT name FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
        if ($old) {
            dbExec("UPDATE quizzes SET sector = ? WHERE sector = ? AND company_id = ?", [$newName, $old['name'], $cid]);
            dbExec("UPDATE sectors SET name = ? WHERE id = ? AND company_id = ?", [$newName, $id, $cid]);
            flash("Setor renomeado de «{$old['name']}» para «{$newName}». Todos os quizzes foram atualizados.", 'success');
        }
    }
    redirect('sectors.php');
}

/* ── Delete Sector ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    requireCsrf();
    $id = (int)$_POST['delete'];
    $sector = dbRow("SELECT * FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
    if ($sector) {
        $quizCount = dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE sector = ? AND company_id = ?", [$sector['name'], $cid])['c'];
        if ($quizCount > 0) {
            flash("Não é possível excluir «{$sector['name']}» pois há {$quizCount} quiz(es) associado(s). Reatribua-os primeiro.", 'error');
        } else {
            dbExec("DELETE FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
            flash("Setor «{$sector['name']}» excluído.", 'success');
        }
    }
    redirect('sectors.php');
}

/* ── Force Delete ────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_delete']) && is_numeric($_POST['force_delete'])) {
    requireCsrf();
    $id = (int)$_POST['force_delete'];
    $sector = dbRow("SELECT * FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
    if ($sector && $sector['name'] !== 'Geral') {
        dbExec("UPDATE quizzes SET sector = 'Geral' WHERE sector = ? AND company_id = ?", [$sector['name'], $cid]);
        dbExec("DELETE FROM sectors WHERE id = ? AND company_id = ?", [$id, $cid]);
        dbExec("INSERT IGNORE INTO sectors (name, company_id) VALUES ('Geral', ?)", [$cid]);
        flash("Setor «{$sector['name']}» excluído. Quizzes movidos para «Geral».", 'success');
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

$totalSectors  = count($sectors);
$withQuizzes   = count(array_filter($sectors, fn($s) => $s['quiz_count'] > 0));
$emptysectors  = $totalSectors - $withQuizzes;

adminHead('Setores', 'sectors.php');
?>
<style>
.sec-kpis {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.sec-kpi {
    background: #fff;
    border: 1px solid var(--gray-100);
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.sec-kpi-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.sec-kpi-num  { font-size: 22px; font-weight: 800; color: var(--gray-800); line-height: 1; }
.sec-kpi-lbl  { font-size: 12px; color: var(--gray-400); margin-top: 2px; }
.sector-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid var(--gray-100);
}
.sector-row:last-child { border-bottom: none; }
.sector-avatar {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: #e0f2fe;
    color: var(--pacific);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.sector-avatar.default { background: #f3f6f9; color: var(--gray-400); }
.sector-name  { font-weight: 700; font-size: 14px; color: var(--navy); }
.sector-meta  { font-size: 12px; color: var(--gray-400); margin-top: 2px; }
.sector-actions { margin-left: auto; display: flex; gap: 6px; align-items: center; flex-shrink: 0; }

.rename-panel {
    display: none;
    background: #f8fafc;
    border: 1.5px solid var(--gray-200);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    animation: slideDown .2s ease;
}
.rename-panel.open { display: block; }
@keyframes slideDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }

@media (max-width: 640px) {
    .sec-kpis { grid-template-columns: 1fr 1fr; }
    .sec-kpis .sec-kpi:last-child { grid-column: span 2; }
}
</style>

<div class="admin-wrap">

<!-- Header -->
<div class="flex items-center justify-between mb-24">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">
            <i class="fa-solid fa-sitemap" style="color:var(--pacific)"></i> Setores
        </h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px">
            Organize seus quizzes por área ou departamento
        </p>
    </div>
</div>

<!-- KPIs -->
<div class="sec-kpis">
    <div class="sec-kpi">
        <div class="sec-kpi-icon" style="background:#e0f2fe;color:var(--pacific)">
            <i class="fa-solid fa-sitemap"></i>
        </div>
        <div>
            <div class="sec-kpi-num"><?= $totalSectors ?></div>
            <div class="sec-kpi-lbl">Total de Setores</div>
        </div>
    </div>
    <div class="sec-kpi">
        <div class="sec-kpi-icon" style="background:#dcfce7;color:#16a34a">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div class="sec-kpi-num"><?= $withQuizzes ?></div>
            <div class="sec-kpi-lbl">Com Quizzes</div>
        </div>
    </div>
    <div class="sec-kpi">
        <div class="sec-kpi-icon" style="background:#f3f6f9;color:var(--gray-400)">
            <i class="fa-solid fa-circle-xmark"></i>
        </div>
        <div>
            <div class="sec-kpi-num"><?= $emptysectors ?></div>
            <div class="sec-kpi-lbl">Sem Quizzes</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start">

<!-- Coluna esquerda: Formulário novo setor -->
<div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-plus" style="color:var(--green)"></i> Novo Setor</h2>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="add_sector" value="1"/>
            <div class="form-group">
                <label class="form-label">Nome do Setor</label>
                <input class="form-control" type="text" name="name" required
                       placeholder="Ex: Laboratório, Coleta, TI…" autofocus/>
                <div class="form-hint">Será usado para agrupar os quizzes na plataforma.</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
                <i class="fa-solid fa-plus"></i> Criar Setor
            </button>
        </form>
    </div>

    <div class="card" style="background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);border-color:#bae6fd">
        <div style="font-size:13px;color:var(--prussian);line-height:1.6">
            <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                <i class="fa-solid fa-circle-info" style="color:var(--pacific)"></i> Como funcionam os setores
            </div>
            <ul style="margin:0;padding-left:16px;color:var(--gray-600);font-size:12px;line-height:1.8">
                <li>Cada quiz pertence a um setor</li>
                <li>Participantes são registrados pelo seu setor</li>
                <li>Use para análise de performance por área</li>
                <li>O setor <strong>Geral</strong> é o padrão e não pode ser excluído</li>
            </ul>
        </div>
    </div>
</div>

<!-- Coluna direita: Lista de setores -->
<div>
    <!-- Painel de renomear (inline) -->
    <div class="rename-panel" id="rename-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <div style="font-size:14px;font-weight:700;color:var(--navy)">
                <i class="fa-solid fa-pen-to-square" style="color:var(--pacific)"></i>
                Renomear: <span id="rename-current" style="color:var(--pacific)"></span>
            </div>
            <button type="button" onclick="closeRename()" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:18px;line-height:1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="post" style="display:flex;gap:10px;align-items:flex-end">
            <?= csrfField() ?>
            <input type="hidden" name="rename_sector" value="1"/>
            <input type="hidden" name="sector_id" id="rename-id"/>
            <div class="form-group" style="margin-bottom:0;flex:1">
                <label class="form-label">Novo nome <span style="color:var(--pacific);font-size:11px;font-weight:400">(todos os quizzes serão atualizados)</span></label>
                <input class="form-control" type="text" name="new_name" id="rename-new" required placeholder="Novo nome do setor"/>
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap">
                <i class="fa-solid fa-check"></i> Confirmar
            </button>
            <button type="button" onclick="closeRename()" class="btn btn-outline" style="white-space:nowrap">Cancelar</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h2><i class="fa-solid fa-list" style="color:var(--blue)"></i> Setores Cadastrados</h2>
            <span class="badge badge-blue"><?= $totalSectors ?></span>
        </div>

        <?php if (empty($sectors)): ?>
        <div style="text-align:center;padding:48px 20px;color:var(--gray-400)">
            <i class="fa-solid fa-sitemap" style="font-size:36px;margin-bottom:12px;opacity:.3;display:block"></i>
            <div style="font-size:14px;font-weight:600;margin-bottom:6px">Nenhum setor cadastrado</div>
            <div style="font-size:12px">Crie o primeiro setor usando o formulário ao lado.</div>
        </div>
        <?php else: ?>
        <div>
        <?php foreach ($sectors as $s):
            $isDefault = $s['name'] === 'Geral';
        ?>
        <div class="sector-row">
            <div class="sector-avatar <?= $isDefault ? 'default' : '' ?>">
                <i class="fa-solid <?= $isDefault ? 'fa-folder-open' : 'fa-tag' ?>"></i>
            </div>
            <div style="min-width:0;flex:1">
                <div class="sector-name">
                    <?= e($s['name']) ?>
                    <?php if ($isDefault): ?>
                    <span class="badge badge-blue" style="font-size:10px;margin-left:6px;vertical-align:middle">Padrão</span>
                    <?php endif; ?>
                </div>
                <div class="sector-meta">
                    <?php if ($s['quiz_count'] > 0): ?>
                        <a href="quizzes.php" style="color:var(--pacific);font-weight:600;text-decoration:none">
                            <?= $s['quiz_count'] ?> quiz(es)
                        </a>
                        · criado em <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                    <?php else: ?>
                        Nenhum quiz · criado em <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sector-actions">
                <button type="button" class="btn btn-outline btn-sm"
                        onclick="openRename(<?= $s['id'] ?>, '<?= e(addslashes($s['name'])) ?>')">
                    <i class="fa-solid fa-pen-to-square"></i> <span style="display:none;"> Renomear</span>
                </button>

                <?php if (!$isDefault): ?>
                    <?php if ($s['quiz_count'] == 0): ?>
                    <form method="post" style="display:inline" onsubmit="return confirmAction('Excluir o setor «<?= e($s['name']) ?>»? Esta ação não pode ser desfeita.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete" value="<?= $s['id'] ?>"/>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                    </form>
                    <?php else: ?>
                    <form method="post" style="display:inline" onsubmit="return confirmAction('Excluir «<?= e($s['name']) ?>» e mover os <?= $s['quiz_count'] ?> quiz(es) para «Geral»?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="force_delete" value="<?= $s['id'] ?>"/>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-triangle-exclamation"></i> Forçar</button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="font-size:11px;color:var(--gray-300);padding:0 4px">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /grid -->
</div><!-- /admin-wrap -->

<script>
function openRename(id, currentName) {
    document.getElementById('rename-id').value   = id;
    document.getElementById('rename-current').textContent = currentName;
    document.getElementById('rename-new').value  = currentName;
    const panel = document.getElementById('rename-panel');
    panel.classList.add('open');
    document.getElementById('rename-new').focus();
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function closeRename() {
    document.getElementById('rename-panel').classList.remove('open');
}
</script>
<?php adminFoot(); ?>
