<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid = adminCompanyId();

// Toggle active
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $qid = (int)$_GET['toggle'];
    dbExec("UPDATE quizzes SET active = 1 - active WHERE id = ? AND company_id = ?", [$qid, $cid]);
    flash('Status do quiz atualizado.', 'success');
    redirect('quizzes.php');
}

// Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $qid = (int)$_GET['delete'];
    dbExec("DELETE FROM quizzes WHERE id = ? AND company_id = ?", [$qid, $cid]);
    flash('Quiz excluído.', 'success');
    redirect('quizzes.php');
}

// Force Delete
if (isset($_GET['force_delete']) && is_numeric($_GET['force_delete'])) {
    $qid = (int)$_GET['force_delete'];
    if (!dbRow("SELECT id FROM quizzes WHERE id = ? AND company_id = ?", [$qid, $cid])) redirect('quizzes.php');
    dbExec("DELETE FROM answers WHERE participant_id IN (SELECT id FROM participants WHERE quiz_id = ?)", [$qid]);
    dbExec("DELETE FROM participants WHERE quiz_id = ?", [$qid]);
    dbExec("DELETE FROM quizzes WHERE id = ? AND company_id = ?", [$qid, $cid]);
    flash('Quiz e todos os seus resultados foram excluídos permanentemente.', 'success');
    redirect('quizzes.php');
}

// Clone
if (isset($_GET['clone']) && is_numeric($_GET['clone'])) {
    $qid = (int)$_GET['clone'];
    $old = dbRow("SELECT * FROM quizzes WHERE id = ? AND company_id = ?", [$qid, $cid]);
    if ($old) {
        unset($old['id']);
        $old['title'] .= ' (Cópia)';
        $old['active'] = 0;
        $old['created_at'] = date('Y-m-d H:i:s');
        $old['updated_at'] = date('Y-m-d H:i:s');
        $cols = implode(',', array_keys($old));
        $placeholders = implode(',', array_fill(0, count($old), '?'));
        dbExec("INSERT INTO quizzes ($cols) VALUES ($placeholders)", array_values($old));
        $newId = dbLastId();
        $questions = dbRows("SELECT * FROM questions WHERE quiz_id = ?", [$qid]);
        foreach ($questions as $q) {
            unset($q['id']); $q['quiz_id'] = $newId;
            $qCols = implode(',', array_keys($q));
            $qPlace = implode(',', array_fill(0, count($q), '?'));
            dbExec("INSERT INTO questions ($qCols) VALUES ($qPlace)", array_values($q));
        }
        flash('Quiz clonado com sucesso! A cópia está inativa.', 'success');
    }
    redirect('quizzes.php');
}

$quizzes = dbRows("
    SELECT q.*, COUNT(DISTINCT p.id) AS part_count, COUNT(DISTINCT qs.id) AS q_count
    FROM quizzes q
    LEFT JOIN participants p ON p.quiz_id = q.id
    LEFT JOIN questions qs   ON qs.quiz_id = q.id
    WHERE q.company_id = ?
    GROUP BY q.id
    ORDER BY q.created_at DESC
", [$cid]);

$totalAll      = count($quizzes);
$totalAtivos   = count(array_filter($quizzes, fn($q) => $q['active'] && !($q['expires_at'] && strtotime($q['expires_at']) < time())));
$totalInativos = $totalAll - $totalAtivos;

adminHead('Quizzes', 'quizzes.php');
?>
<div class="admin-wrap">

<div class="flex items-center justify-between mb-24">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">Quizzes</h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px"><?= $totalAll ?> quiz(es) no sistema</p>
    </div>
    <a href="quiz-edit.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Novo Quiz</a>
</div>

<!-- Tab nav -->
<div class="page-tabs" role="tablist">
    <button class="page-tab" data-tab="ativos" onclick="quizTab('ativos')" role="tab">
        <i class="fa-solid fa-circle" style="color:var(--green);font-size:9px"></i>
        <span class="tab-lbl">Ativos</span>
        <span class="tab-badge" id="badge-ativos"><?= $totalAtivos ?></span>
    </button>
    <button class="page-tab" data-tab="inativos" onclick="quizTab('inativos')" role="tab">
        <i class="fa-regular fa-circle" style="color:var(--gray-400);font-size:9px"></i>
        <span class="tab-lbl">Inativos / Expirados</span>
        <span class="tab-badge" id="badge-inativos"><?= $totalInativos ?></span>
    </button>
    <button class="page-tab" data-tab="todos" onclick="quizTab('todos')" role="tab">
        <i class="fa-solid fa-list-check"></i>
        <span class="tab-lbl">Todos</span>
        <span class="tab-badge"><?= $totalAll ?></span>
    </button>
</div>

<div class="card">
    <?php if (empty($quizzes)): ?>
    <p style="text-align:center;padding:48px;color:var(--gray-400);font-size:14px">
        Nenhum quiz criado ainda.<br/>
        <a href="quiz-edit.php" class="btn btn-primary" style="margin-top:16px;display:inline-flex"><i class="fa-solid fa-plus"></i> Criar Primeiro Quiz</a>
    </p>
    <?php else: ?>
    <div class="table-wrap">
        <table id="quiz-table">
            <thead>
                <tr>
                    <th>Quiz</th>
                    <th>Setor</th>
                    <th>Questões</th>
                    <th>Participantes</th>
                    <th>Timer</th>
                    <th>Aprovação</th>
                    <th>Status</th>
                    <th style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($quizzes as $q):
                $expired = $q['expires_at'] && strtotime($q['expires_at']) < time();
                $isAtivo = $q['active'] && !$expired;
            ?>
            <tr data-status="<?= $isAtivo ? 'ativo' : 'inativo' ?>">
                <td>
                    <div style="font-weight:700;font-size:14px"><?= e($q['title']) ?></div>
                    <?php if ($q['description']): ?>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:2px"><?= e(mb_strimwidth($q['description'],0,60,'…')) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-blue"><?= e($q['sector']) ?></span></td>
                <td style="font-weight:700;color:var(--blue)"><?= $q['q_count'] ?></td>
                <td><?= $q['part_count'] ?></td>
                <td><?= $q['time_per_question'] ?>s</td>
                <td><?= $q['pass_percentage'] ?>%</td>
                <td>
                    <?php if ($expired): ?>
                        <span class="badge badge-red"><i class="fa-solid fa-hourglass-end"></i> Expirado</span>
                    <?php elseif ($q['active']): ?>
                        <span class="badge badge-green"><i class="fa-solid fa-circle"></i> Ativo</span>
                    <?php else: ?>
                        <span class="badge badge-gray"><i class="fa-regular fa-circle"></i> Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a href="quiz-questions.php?id=<?= $q['id'] ?>" class="row-action" title="Questões (<?= $q['q_count'] ?>)"><i class="fa-solid fa-list-ol"></i></a>
                        <a href="quiz-edit.php?id=<?= $q['id'] ?>" class="row-action" title="Configurações"><i class="fa-solid fa-sliders"></i></a>
                        <a href="?clone=<?= $q['id'] ?>" class="row-action" title="Duplicar" onclick="return confirmAction('Duplicar este quiz?')"><i class="fa-solid fa-copy"></i></a>
                        <a href="results.php?quiz=<?= $q['id'] ?>" class="row-action" title="Resultados"><i class="fa-solid fa-chart-bar"></i></a>
                        <a href="?toggle=<?= $q['id'] ?>" class="row-action <?= $q['active'] ? 'row-action--danger' : 'row-action--success' ?>"
                           title="<?= $q['active'] ? 'Desativar' : 'Ativar' ?>"
                           onclick="return confirmAction('<?= $q['active'] ? 'Desativar' : 'Ativar' ?> este quiz?')">
                            <i class="fa-solid <?= $q['active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                        </a>
                        <?php if ($q['part_count'] == 0): ?>
                        <a href="?delete=<?= $q['id'] ?>" class="row-action row-action--delete" title="Excluir"
                           onclick="return confirmAction('Excluir este quiz permanentemente?')">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                        <?php else: ?>
                        <a href="?force_delete=<?= $q['id'] ?>" class="row-action row-action--delete" title="Forçar Exclusão"
                           onclick="return confirmAction('ATENÇÃO: Este quiz possui <?= $q['part_count'] ?> participações. Todos os resultados serão apagados permanentemente. Continuar?')">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Empty state por aba -->
    <p id="quiz-empty" style="display:none;text-align:center;padding:40px;color:var(--gray-400);font-size:14px">
        Nenhum quiz nesta categoria.
    </p>
    <?php endif; ?>
</div>

</div>
<script>
function quizTab(tab) {
    // Atualiza tabs
    document.querySelectorAll('.page-tab').forEach(t => {
        const active = t.dataset.tab === tab;
        t.classList.toggle('active', active);
        t.setAttribute('aria-selected', active);
    });
    // Filtra linhas
    const rows = document.querySelectorAll('#quiz-table tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const status = row.dataset.status;
        const show = tab === 'todos' || (tab === 'ativos' && status === 'ativo') || (tab === 'inativos' && status === 'inativo');
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const empty = document.getElementById('quiz-empty');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
    try { sessionStorage.setItem('quiz_tab', tab); } catch(e) {}
}

// Restaura aba salva
(function(){
    const saved = sessionStorage.getItem('quiz_tab') || 'ativos';
    quizTab(saved);
})();
</script>
<?php adminFoot(); ?>
