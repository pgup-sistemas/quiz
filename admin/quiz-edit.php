<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$quizId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz    = $quizId ? dbRow("SELECT * FROM quizzes WHERE id = ?", [$quizId]) : null;
$isNew   = !$quiz;

/* ── Save Quiz ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quiz'])) {
    $title    = trim($_POST['title']    ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $sector   = trim($_POST['sector']   ?? 'Geral');
    $timer    = (int)($_POST['timer']   ?? 30);
    $passPct  = (int)($_POST['pass_pct']?? 70);
    $maxQ     = (int)($_POST['max_questions'] ?? 0);
    $expiry   = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $feedback = isset($_POST['feedback']) ? 1 : 0;
    $hasCert  = isset($_POST['has_certificate']) ? 1 : 0;
    $randomize= isset($_POST['randomize']) ? 1 : 0;
    $retake   = isset($_POST['retake']) ? 1 : 0;
    $active   = isset($_POST['active']) ? 1 : 0;
    $createdBy= trim($_POST['created_by'] ?? adminName());

    if (!$title) { flash('O título é obrigatório.', 'error'); redirect("quiz-edit.php?id=$quizId"); }

    if ($isNew) {
        dbExec("INSERT INTO quizzes (title,description,sector,created_by,time_per_question,pass_percentage,max_questions,expires_at,show_feedback,has_certificate,randomize,allow_retake,active)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$title,$desc,$sector,$createdBy,$timer,$passPct,$maxQ,$expiry,$feedback,$hasCert,$randomize,$retake,$active]);
        $quizId = (int)dbLastId();
        flash('Quiz criado com sucesso!', 'success');
    } else {
        dbExec("UPDATE quizzes SET title=?,description=?,sector=?,created_by=?,time_per_question=?,
                pass_percentage=?,max_questions=?,expires_at=?,show_feedback=?,has_certificate=?,randomize=?,allow_retake=?,active=?,
                updated_at=datetime('now','localtime')
                WHERE id=?",
            [$title,$desc,$sector,$createdBy,$timer,$passPct,$maxQ,$expiry,$feedback,$hasCert,$randomize,$retake,$active,$quizId]);
        flash('Quiz atualizado com sucesso!', 'success');
    }
    redirect("quiz-edit.php?id=$quizId");
}

/* ── Save Question ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_question'])) {
    $qId      = (int)($_POST['q_id']     ?? 0);
    $qText    = trim($_POST['q_text']    ?? '');
    $qCat     = trim($_POST['q_cat']     ?? '');
    $optA     = trim($_POST['opt_a']     ?? '');
    $optB     = trim($_POST['opt_b']     ?? '');
    $optC     = trim($_POST['opt_c']     ?? '');
    $optD     = trim($_POST['opt_d']     ?? '');
    $correct  = (int)($_POST['correct']  ?? 0);
    $exp      = trim($_POST['explanation'] ?? '');
    $order    = (int)($_POST['sort_order'] ?? 999);

    if (!$qText || !$optA || !$optB) {
        flash('Pergunta, opção A e opção B são obrigatórias.', 'error');
        redirect("quiz-edit.php?id=$quizId#questions");
    }

    if ($qId) {
        dbExec("UPDATE questions SET question_text=?,category=?,option_a=?,option_b=?,option_c=?,option_d=?,
                correct_answer=?,explanation=?,sort_order=? WHERE id=? AND quiz_id=?",
            [$qText,$qCat,$optA,$optB,$optC,$optD,$correct,$exp,$order,$qId,$quizId]);
        flash('Questão atualizada!', 'success');
    } else {
        $maxOrder = dbRow("SELECT MAX(sort_order) AS m FROM questions WHERE quiz_id=?", [$quizId])['m'] ?? 0;
        dbExec("INSERT INTO questions (quiz_id,question_text,category,option_a,option_b,option_c,option_d,correct_answer,explanation,sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$quizId,$qText,$qCat,$optA,$optB,$optC,$optD,$correct,$exp,$maxOrder+1]);
        flash('Questão adicionada!', 'success');
    }
    redirect("quiz-edit.php?id=$quizId#questions");
}

/* ── Delete Question ───────────────────────────────────────── */
if (isset($_GET['del_q']) && $quizId) {
    $dq = (int)$_GET['del_q'];
    dbExec("DELETE FROM questions WHERE id=? AND quiz_id=?", [$dq,$quizId]);
    flash('Questão excluída.', 'success');
    redirect("quiz-edit.php?id=$quizId#questions");
}

/* ── Load ─────────────────────────────────────────────────── */
$quiz = $quizId ? dbRow("SELECT * FROM quizzes WHERE id=?", [$quizId]) : null;
$questions = $quizId ? dbRows("SELECT * FROM questions WHERE quiz_id=? ORDER BY sort_order ASC, id ASC", [$quizId]) : [];

// Edit question?
$editQ = null;
if (isset($_GET['edit_q']) && $quizId) {
    $editQ = dbRow("SELECT * FROM questions WHERE id=? AND quiz_id=?", [(int)$_GET['edit_q'], $quizId]);
}

adminHead($isNew ? 'Novo Quiz' : 'Editar: ' . ($quiz['title'] ?? ''), 'quizzes.php');
?>
<style>
.q-table td { vertical-align:top; }
.q-num-cell { font-size:22px; font-weight:700; color:var(--gray-200); width:40px; text-align:center; }
.correct-mark { display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:var(--green); }
.options-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.opt-row { display:flex; align-items:center; gap:8px; }
.opt-letter {
    width:30px;height:30px;
    background:var(--blue-light);
    color:var(--blue);
    border-radius:6px;
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:13px;
    flex-shrink:0;
}
.opt-letter.correct-opt { background:var(--green); color:#fff; }
.correct-radio-group { display:flex;gap:12px;flex-wrap:wrap;margin-top:6px; }
.correct-radio-group label {
    display:flex;align-items:center;gap:6px;
    font-size:13px;color:var(--gray-600);
    cursor:pointer;font-weight:600;
    text-transform:none;letter-spacing:0;
    padding:6px 12px;
    background:var(--gray-50);
    border:2px solid var(--gray-200);
    border-radius:8px;
    transition:.15s;
}
.correct-radio-group label:has(input:checked) { background:var(--blue-light);border-color:var(--blue);color:var(--blue); }
.correct-radio-group input[type=radio] { display:none; }
</style>

<div class="admin-wrap">



<!-- Breadcrumb -->
<div class="flex items-center gap-8 mb-24" style="font-size:13px">
    <a href="quizzes.php" style="color:var(--blue);text-decoration:none">← Quizzes</a>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)"><?= $isNew ? 'Novo Quiz' : e($quiz['title']) ?></span>
</div>

<!-- ─── Quiz Form ──────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h2><?= $isNew ? '➕ Criar Novo Quiz' : '✏️ Editar Quiz' ?></h2>
    </div>
    <form method="post">
        <input type="hidden" name="save_quiz" value="1"/>
        <div class="form-row">
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Título do Quiz *</label>
                <input class="form-control" type="text" name="title" required
                       value="<?= e($quiz['title'] ?? '') ?>" placeholder="Ex: Quiz de Segurança 2025"/>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Descrição / Instruções</label>
            <textarea class="form-textarea" name="description" placeholder="Descreva o objetivo do quiz e instruções para o participante..."><?= e($quiz['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Setor / Área</label>
                <?php $sectors = dbRows("SELECT name FROM sectors ORDER BY name ASC"); ?>
                <select class="form-control" name="sector">
                    <?php if (empty($sectors)): ?>
                        <option value="Geral">Geral</option>
                    <?php else: ?>
                        <?php foreach ($sectors as $s): ?>
                            <option value="<?= e($s['name']) ?>" <?= ($quiz['sector'] ?? 'Geral') == $s['name'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Criado Por</label>
                <input class="form-control" type="text" name="created_by" value="<?= e($quiz['created_by'] ?? adminName()) ?>"/>
            </div>
            <div class="form-group">
                <label class="form-label">Tempo por Questão (seg)</label>
                <input class="form-control" type="number" name="timer" min="5" max="300" value="<?= $quiz['time_per_question'] ?? 30 ?>"/>
            </div>
            <div class="form-group">
                <label class="form-label">% Mínima para Aprovação</label>
                <input class="form-control" type="number" name="pass_pct" min="0" max="100" value="<?= $quiz['pass_percentage'] ?? 70 ?>"/>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Máx. Questões por Sessão <i class="fa-solid fa-circle-info" title="0 = usa todas as questões"></i></label>
                <input class="form-control" type="number" name="max_questions" min="0" value="<?= $quiz['max_questions'] ?? 0 ?>"/>
            </div>
            <div class="form-group">
                <label class="form-label">Data de Expiração <span style="font-size:11px;color:var(--gray-400)">(opcional)</span></label>
                <input class="form-control" type="date" name="expires_at" value="<?= $quiz['expires_at'] ? date('Y-m-d', strtotime($quiz['expires_at'])) : '' ?>"/>
            </div>
        </div>
        <div class="flex flex-wrap gap-12" style="margin-bottom:20px">
            <?php
            $opts = [
                'feedback'  => ['Mostrar feedback após resposta', $quiz['show_feedback'] ?? 1],
                'has_certificate' => ['Emitir Certificado ao concluir', $quiz['has_certificate'] ?? 1],
                'randomize' => ['Aleatório (embaralhar questões)', $quiz['randomize']     ?? 0],
                'retake'    => ['Permitir repetir o quiz',         $quiz['allow_retake']  ?? 1],
                'active'    => ['Quiz ativo (visível no site)',     $quiz['active']        ?? 1],
            ];
            foreach ($opts as $name => [$label, $checked]): ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--gray-600);cursor:pointer">
                <input type="checkbox" name="<?= $name ?>" <?= $checked ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--blue)"/>
                <?= $label ?>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-check"></i> <?= $isNew ? 'Criar Quiz' : 'Salvar Alterações' ?></button>
        <?php if (!$isNew): ?>
        <a href="../quiz.php?id=<?= $quizId ?>" target="_blank" class="btn btn-outline" style="margin-left:10px"><i class="fa-solid fa-eye"></i> Pré-visualizar</a>
        <a href="import.php?quiz=<?= $quizId ?>" class="btn btn-dark" style="margin-left:10px"><i class="fa-solid fa-file-import"></i> Importar CSV</a>
        <?php endif; ?>
    </form>
</div>

<!-- ─── Questions ─────────────────────────────────────────── -->
<?php if (!$isNew): ?>
<div id="questions">

<!-- Add / Edit Question Form -->
<div class="card" id="q-form-card">
    <div class="card-header">
        <h2><?= $editQ ? '<i class="fa-solid fa-pen-to-square"></i> Editar Questão' : '<i class="fa-solid fa-plus"></i> Adicionar Questão' ?></h2>
    </div>
    <form method="post">
        <input type="hidden" name="save_question" value="1"/>
        <input type="hidden" name="q_id" value="<?= $editQ['id'] ?? '' ?>"/>

        <div class="form-row">
            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Pergunta *</label>
                <textarea class="form-textarea" name="q_text" required placeholder="Digite a pergunta aqui..."><?= e($editQ['question_text'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Categoria / Tema</label>
                <input class="form-control" type="text" name="q_cat" value="<?= e($editQ['category'] ?? '') ?>" placeholder="Ex: Biossegurança, LGPD, Qualidade…"/>
            </div>
            <div class="form-group">
                <label class="form-label">Ordem</label>
                <input class="form-control" type="number" name="sort_order" value="<?= $editQ['sort_order'] ?? count($questions)+1 ?>"/>
            </div>
        </div>

        <label class="form-label">Opções de Resposta *</label>
        <div class="options-grid" style="margin-bottom:10px">
            <?php $letters = ['a'=>'A','b'=>'B','c'=>'C','d'=>'D']; ?>
            <?php foreach ($letters as $key => $letter): ?>
            <div class="opt-row">
                <span class="opt-letter <?= (($editQ['correct_answer'] ?? 0) === array_search($letter,['A','B','C','D'])) ? 'correct-opt' : '' ?>"><?= $letter ?></span>
                <input class="form-control" type="text" name="opt_<?= $key ?>"
                       value="<?= e($editQ['option_'.strtolower($key)] ?? '') ?>"
                       placeholder="Opção <?= $letter ?><?= $key === 'a' || $key === 'b' ? ' (obrigatório)' : ' (opcional)' ?>"
                       <?= $key === 'a' || $key === 'b' ? 'required' : '' ?>/>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label class="form-label">Resposta Correta *</label>
            <div class="correct-radio-group">
                <?php foreach (['A'=>0,'B'=>1,'C'=>2,'D'=>3] as $lbl => $val): ?>
                <label>
                    <input type="radio" name="correct" value="<?= $val ?>" <?= ($editQ['correct_answer'] ?? 0) == $val ? 'checked' : '' ?> required/>
                    Opção <?= $lbl ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Explicação (exibida após responder)</label>
            <textarea class="form-textarea" name="explanation" style="min-height:70px" placeholder="Explique o motivo da resposta correta…"><?= e($editQ['explanation'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-8">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-circle-check"></i> <?= $editQ ? 'Atualizar Questão' : 'Adicionar Questão' ?></button>
            <?php if ($editQ): ?>
            <a href="quiz-edit.php?id=<?= $quizId ?>#questions" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Questions List -->
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h2>📋 Questões (<?= count($questions) ?>)</h2>
        <?php if (!empty($questions)): ?>
        <span class="text-muted" style="font-size:12px">Clique em <i class="fa-solid fa-pen-to-square"></i> para editar</span>
        <?php endif; ?>
    </div>
    <?php if (empty($questions)): ?>
    <p style="text-align:center;padding:32px;color:var(--gray-400);font-size:14px">
        Nenhuma questão adicionada ainda.<br/>Use o formulário acima para adicionar a primeira.
    </p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Pergunta</th>
                    <th>Categoria</th>
                    <th>Opções</th>
                    <th>Correta</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($questions as $i => $q): ?>
            <tr id="q-row-<?= $q['id'] ?>">
                <td class="q-num-cell"><?= $i+1 ?></td>
                <td>
                    <div style="font-size:13px;font-weight:600;color:var(--gray-800);max-width:320px;line-height:1.4"><?= e($q['question_text']) ?></div>
                    <?php if ($q['explanation']): ?>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:3px"><i class="fa-solid fa-lightbulb"></i> <?= e(mb_strimwidth($q['explanation'],0,60,'…')) ?></div>
                    <?php endif; ?>
                </td>
                <td><?php if($q['category']): ?><span class="badge badge-blue"><?= e($q['category']) ?></span><?php endif; ?></td>
                <td>
                    <?php
                    $opts = array_filter([$q['option_a'],$q['option_b'],$q['option_c'],$q['option_d']]);
                    echo count($opts) . ' opções';
                    ?>
                </td>
                <td>
                    <?php
                    $letters = ['A','B','C','D'];
                    $ci = (int)$q['correct_answer'];
                    $opts2 = [$q['option_a'],$q['option_b'],$q['option_c'],$q['option_d']];
                    echo "<span class='correct-mark'>✓ {$letters[$ci]}) " . e(mb_strimwidth($opts2[$ci],0,30,'…')) . "</span>";
                    ?>
                </td>
                <td>
                    <div class="flex gap-8">
                        <a href="quiz-edit.php?id=<?= $quizId ?>&edit_q=<?= $q['id'] ?>#q-form-card" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                        <a href="quiz-edit.php?id=<?= $quizId ?>&del_q=<?= $q['id'] ?>" class="btn btn-danger btn-sm"
                           onclick="return confirm('Excluir esta questão?')"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- #questions -->
<?php endif; ?>

</div><!-- admin-wrap -->
<?php adminFoot(); ?>
