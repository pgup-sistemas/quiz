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

// Force Delete (including participants)
if (isset($_GET['force_delete']) && is_numeric($_GET['force_delete'])) {
    $qid = (int)$_GET['force_delete'];
    if (!dbRow("SELECT id FROM quizzes WHERE id = ? AND company_id = ?", [$qid, $cid])) {
        redirect('quizzes.php');
    }
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
        $old['active'] = 0; // Clone inactive by default
        $old['created_at'] = date('Y-m-d H:i:s');
        $old['updated_at'] = date('Y-m-d H:i:s');

        $cols = implode(',', array_keys($old));
        $placeholders = implode(',', array_fill(0, count($old), '?'));
        dbExec("INSERT INTO quizzes ($cols) VALUES ($placeholders)", array_values($old));
        $newId = dbLastId();

        // Clone questions
        $questions = dbRows("SELECT * FROM questions WHERE quiz_id = ?", [$qid]);
        foreach ($questions as $q) {
            unset($q['id']);
            $q['quiz_id'] = $newId;
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
    GROUP BY q.id
    ORDER BY q.created_at DESC
");

adminHead('Quizzes', 'quizzes.php');
?>
<div class="admin-wrap">



<div class="flex items-center justify-between mb-24">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">Quizzes</h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px"><?= count($quizzes) ?> quiz(es) no sistema</p>
    </div>
    <a href="quiz-edit.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Novo Quiz</a>
</div>

<div class="card">
    <?php if (empty($quizzes)): ?>
    <p style="text-align:center;padding:48px;color:var(--gray-400);font-size:14px">
        Nenhum quiz criado ainda.<br/>
        <a href="quiz-edit.php" class="btn btn-primary" style="margin-top:16px;display:inline-flex"><i class="fa-solid fa-plus"></i> Criar Primeiro Quiz</a>
    </p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
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
            <?php foreach ($quizzes as $q): ?>
            <tr>
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
                    <?php 
                    $expired = $q['expires_at'] && strtotime($q['expires_at']) < time();
                    if ($expired): ?>
                        <span class="badge badge-red"><i class="fa-solid fa-hourglass-end" aria-hidden="true"></i> Expirado</span>
                    <?php elseif ($q['active']): ?>
                        <span class="badge badge-green"><i class="fa-solid fa-circle" aria-hidden="true"></i> Ativo</span>
                    <?php else: ?>
                        <span class="badge badge-gray"><i class="fa-regular fa-circle" aria-hidden="true"></i> Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a href="quiz-edit.php?id=<?= $q['id'] ?>" class="row-action" title="Editar" aria-label="Editar quiz">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        </a>
                        <a href="?clone=<?= $q['id'] ?>" class="row-action" title="Duplicar" aria-label="Duplicar quiz"
                           onclick="return confirmAction('Duplicar este quiz?')">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i>
                        </a>
                        <a href="results.php?quiz=<?= $q['id'] ?>" class="row-action" title="Resultados" aria-label="Ver resultados">
                            <i class="fa-solid fa-chart-bar" aria-hidden="true"></i>
                        </a>
                        <a href="?toggle=<?= $q['id'] ?>" class="row-action <?= $q['active'] ? 'row-action--danger' : 'row-action--success' ?>"
                           title="<?= $q['active'] ? 'Desativar' : 'Ativar' ?>"
                           aria-label="<?= $q['active'] ? 'Desativar quiz' : 'Ativar quiz' ?>"
                           onclick="return confirmAction('<?= $q['active'] ? 'Desativar' : 'Ativar' ?> este quiz?')">
                            <i class="fa-solid <?= $q['active'] ? 'fa-pause' : 'fa-play' ?>" aria-hidden="true"></i>
                        </a>
                        <?php if ($q['part_count'] == 0): ?>
                        <a href="?delete=<?= $q['id'] ?>" class="row-action row-action--delete"
                           title="Excluir" aria-label="Excluir quiz"
                           onclick="return confirmAction('Excluir este quiz permanentemente?')">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        </a>
                        <?php else: ?>
                        <a href="?force_delete=<?= $q['id'] ?>" class="row-action row-action--delete"
                           title="Forçar Exclusão" aria-label="Forçar exclusão do quiz"
                           onclick="return confirmAction('ATENÇÃO: Este quiz possui <?= $q['part_count'] ?> participações. Todos os resultados serão apagados permanentemente. Continuar?')">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        </a>
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
<?php adminFoot(); ?>
