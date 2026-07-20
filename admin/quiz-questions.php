<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid    = adminCompanyId();
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz   = $quizId ? dbRow("SELECT * FROM quizzes WHERE id=? AND company_id=?", [$quizId,$cid]) : null;

if (!$quiz) { flash('Quiz não encontrado.', 'error'); redirect('quizzes.php'); }

/* ── Save Question ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_question'])) {
    $qId     = (int)($_POST['q_id']      ?? 0);
    $qText   = trim($_POST['q_text']     ?? '');
    $qCat    = trim($_POST['q_cat']      ?? '');
    $optA    = trim($_POST['opt_a']      ?? '');
    $optB    = trim($_POST['opt_b']      ?? '');
    $optC    = trim($_POST['opt_c']      ?? '');
    $optD    = trim($_POST['opt_d']      ?? '');
    $correct = (int)($_POST['correct']   ?? 0);
    $exp     = trim($_POST['explanation']?? '');
    $order   = (int)($_POST['sort_order']?? 999);

    if (!$qText || !$optA || !$optB) {
        flash('Pergunta, opção A e opção B são obrigatórias.', 'error');
        redirect("quiz-questions.php?id=$quizId");
    }

    if ($qId) {
        dbExec("UPDATE questions SET
                    question_text=?,category=?,option_a=?,option_b=?,option_c=?,option_d=?,
                    correct_answer=?,explanation=?,sort_order=?
                WHERE id=? AND quiz_id=?",
            [$qText,$qCat,$optA,$optB,$optC,$optD,$correct,$exp,$order,$qId,$quizId]);
        flash('Questão atualizada!', 'success');
    } else {
        $maxOrder = dbRow("SELECT MAX(sort_order) AS m FROM questions WHERE quiz_id=?", [$quizId])['m'] ?? 0;
        dbExec("INSERT INTO questions
                    (quiz_id,company_id,question_text,category,option_a,option_b,option_c,option_d,
                     correct_answer,explanation,sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$quizId,$cid,$qText,$qCat,$optA,$optB,$optC,$optD,$correct,$exp,$maxOrder+1]);
        flash('Questão adicionada!', 'success');
    }
    redirect("quiz-questions.php?id=$quizId");
}

/* ── Delete Question ───────────────────────────────────────── */
if (isset($_GET['del_q'])) {
    dbExec("DELETE FROM questions WHERE id=? AND quiz_id=?", [(int)$_GET['del_q'], $quizId]);
    flash('Questão excluída.', 'success');
    redirect("quiz-questions.php?id=$quizId");
}

/* ── Load ─────────────────────────────────────────────────── */
$questions = dbRows("SELECT * FROM questions WHERE quiz_id=? ORDER BY sort_order ASC, id ASC", [$quizId]);
$editQ     = null;
if (isset($_GET['edit_q'])) {
    $editQ = dbRow("SELECT * FROM questions WHERE id=? AND quiz_id=?", [(int)$_GET['edit_q'], $quizId]);
}
$isCreated = !empty($_GET['created']);

adminHead('Questões: ' . $quiz['title'], 'quizzes.php');
?>
<style>
/* Step indicator */
.quiz-steps { display:flex;align-items:center;gap:0;margin-bottom:24px; }
.quiz-step  { display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600; }
.quiz-step-num {
    width:28px;height:28px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:700;flex-shrink:0;
}
.quiz-step.done   .quiz-step-num { background:var(--green);   color:#fff; }
.quiz-step.active .quiz-step-num { background:var(--pacific); color:#fff; }
.quiz-step.done   span { color:rgba(255,255,255,.7); }
.quiz-step.active span { color:#fff; }
.quiz-step-line      { flex:1;height:2px;background:rgba(255,255,255,.15);margin:0 10px;min-width:32px; }
.quiz-step-line.done { background:var(--green); }

/* Welcome banner */
.welcome-banner {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #86efac;
    border-radius: 12px;
    padding: 18px 22px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.welcome-banner-icon {
    width: 40px; height: 40px;
    background: #16a34a;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px; flex-shrink: 0;
}

/* Question form */
.options-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.opt-row      { display:flex;align-items:center;gap:8px; }
.opt-letter   {
    width:30px;height:30px;
    background:var(--blue-light);color:var(--blue);
    border-radius:6px;
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:13px;flex-shrink:0;
}
.opt-letter.correct-opt { background:var(--green);color:#fff; }

.correct-radio-group { display:flex;gap:10px;flex-wrap:wrap;margin-top:6px; }
.correct-radio-group label {
    display:flex;align-items:center;gap:6px;
    font-size:13px;color:var(--gray-600);
    cursor:pointer;font-weight:600;
    padding:6px 14px;
    background:var(--gray-50);
    border:2px solid var(--gray-200);
    border-radius:8px;
    transition:.15s;
}
.correct-radio-group label:has(input:checked) {
    background:var(--blue-light);border-color:var(--blue);color:var(--blue);
}
.correct-radio-group input[type=radio] { display:none; }

/* Question list */
.q-num-cell  { font-size:20px;font-weight:700;color:var(--gray-200);width:36px;text-align:center; }
.correct-mark { display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:var(--green); }
</style>

<div class="admin-wrap">

<!-- Breadcrumb -->
<div class="flex items-center gap-8 mb-20" style="font-size:13px">
    <a href="quizzes.php" style="color:var(--blue);text-decoration:none">
        <i class="fa-solid fa-arrow-left"></i> Quizzes
    </a>
    <span style="color:var(--gray-300)">/</span>
    <a href="quiz-edit.php?id=<?= $quizId ?>" style="color:var(--blue);text-decoration:none">
        <?= e($quiz['title']) ?>
    </a>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)">Questões</span>
</div>

<!-- Step indicator (só exibe ao criar) -->
<?php if ($isCreated): ?>
<div class="quiz-steps">
    <div class="quiz-step done">
        <div class="quiz-step-num"><i class="fa-solid fa-check"></i></div>
        <span>Dados do Quiz</span>
    </div>
    <div class="quiz-step-line done"></div>
    <div class="quiz-step active">
        <div class="quiz-step-num">2</div>
        <span>Adicionar Questões</span>
    </div>
</div>
<?php endif; ?>

<!-- Header bar -->
<div class="flex items-center justify-between mb-20" style="flex-wrap:wrap;gap:12px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:var(--gray-800)">
            <i class="fa-solid fa-list-ol" style="color:var(--pacific)"></i>
            <?= e($quiz['title']) ?>
        </h1>
        <div style="display:flex;align-items:center;gap:10px;margin-top:4px;flex-wrap:wrap">
            <span class="badge badge-blue"><?= e($quiz['sector']) ?></span>
            <span style="font-size:12px;color:var(--gray-400)">
                <i class="fa-solid fa-list-ol"></i> <?= count($questions) ?> questão(ões)
            </span>
            <span style="font-size:12px;color:var(--gray-400)">
                <i class="fa-solid fa-clock"></i> <?= $quiz['time_per_question'] ?>s/questão
            </span>
            <span style="font-size:12px;color:var(--gray-400)">
                <i class="fa-solid fa-award"></i> Aprovação ≥ <?= $quiz['pass_percentage'] ?>%
            </span>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="import.php?quiz=<?= $quizId ?>" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-file-import"></i> Importar CSV
        </a>
        <a href="../quiz.php?id=<?= $quizId ?>" target="_blank" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-eye"></i> Pré-visualizar
        </a>
        <a href="quiz-edit.php?id=<?= $quizId ?>" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-sliders"></i> Configurações
        </a>
    </div>
</div>

<!-- Banner de boas-vindas (só ao criar) -->
<?php if ($isCreated): ?>
<div class="welcome-banner">
    <div class="welcome-banner-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div>
        <div style="font-weight:700;font-size:14px;color:#15803d;margin-bottom:4px">
            Quiz criado com sucesso!
        </div>
        <div style="font-size:13px;color:#166534;line-height:1.5">
            Agora adicione as questões. Você pode voltar às configurações a qualquer momento
            pelo botão <strong>Configurações</strong> acima.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ─── Formulário Adicionar / Editar Questão ──────────────── -->
<div class="card" id="q-form-card">
    <div class="card-header">
        <h2>
            <?= $editQ
                ? '<i class="fa-solid fa-pen-to-square" style="color:var(--pacific)"></i> Editar Questão'
                : '<i class="fa-solid fa-plus" style="color:var(--green)"></i> Adicionar Questão' ?>
        </h2>
    </div>
    <form method="post">
        <input type="hidden" name="save_question" value="1"/>
        <input type="hidden" name="q_id" value="<?= $editQ['id'] ?? '' ?>"/>

        <div class="form-group">
            <label class="form-label">Pergunta *</label>
            <textarea class="form-textarea" name="q_text" required autofocus
                      placeholder="Digite a pergunta aqui…"><?= e($editQ['question_text'] ?? '') ?></textarea>
        </div>

        <div class="form-row" style="grid-template-columns:1fr auto">
            <div class="form-group">
                <label class="form-label">Categoria / Tema
                    <span style="color:var(--gray-400);font-weight:400">(opcional)</span>
                </label>
                <input class="form-control" type="text" name="q_cat"
                       value="<?= e($editQ['category'] ?? '') ?>"
                       placeholder="Ex: Biossegurança, LGPD, Qualidade…"/>
            </div>
            <div class="form-group" style="min-width:100px">
                <label class="form-label">Ordem</label>
                <input class="form-control" type="number" name="sort_order"
                       value="<?= $editQ['sort_order'] ?? count($questions)+1 ?>"/>
            </div>
        </div>

        <label class="form-label">Alternativas *</label>
        <div class="options-grid" style="margin-bottom:12px">
            <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $key => $letter): ?>
            <div class="opt-row">
                <span class="opt-letter"><?= $letter ?></span>
                <input class="form-control" type="text" name="opt_<?= $key ?>"
                       value="<?= e($editQ['option_'.strtolower($key)] ?? '') ?>"
                       placeholder="Opção <?= $letter ?><?= in_array($key,['a','b']) ? ' (obrigatório)' : ' (opcional)' ?>"
                       <?= in_array($key,['a','b']) ? 'required' : '' ?>/>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label class="form-label">Resposta Correta *</label>
            <div class="correct-radio-group">
                <?php foreach (['A'=>0,'B'=>1,'C'=>2,'D'=>3] as $lbl => $val): ?>
                <label>
                    <input type="radio" name="correct" value="<?= $val ?>"
                           <?= ($editQ['correct_answer'] ?? 0) == $val ? 'checked' : '' ?> required/>
                    Opção <?= $lbl ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">
                Explicação
                <span style="color:var(--gray-400);font-weight:400"> — exibida após responder</span>
            </label>
            <textarea class="form-textarea" name="explanation" style="min-height:70px"
                      placeholder="Explique o motivo da resposta correta…"><?= e($editQ['explanation'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-8">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-circle-check"></i>
                <?= $editQ ? 'Atualizar Questão' : 'Adicionar Questão' ?>
            </button>
            <?php if ($editQ): ?>
            <a href="quiz-questions.php?id=<?= $quizId ?>" class="btn btn-outline">
                <i class="fa-solid fa-xmark"></i> Cancelar Edição
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ─── Lista de Questões ─────────────────────────────────── -->
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h2>
            <i class="fa-solid fa-list-ol" style="color:var(--pacific)"></i>
            Questões
            <span class="badge badge-blue" style="margin-left:8px"><?= count($questions) ?></span>
        </h2>
        <?php if (!empty($questions)): ?>
        <span class="text-muted" style="font-size:12px">
            Clique em <i class="fa-solid fa-pen-to-square"></i> para editar uma questão
        </span>
        <?php endif; ?>
    </div>

    <?php if (empty($questions)): ?>
    <div style="text-align:center;padding:48px 20px;color:var(--gray-400)">
        <i class="fa-solid fa-list-ol" style="font-size:36px;opacity:.2;display:block;margin-bottom:12px"></i>
        <div style="font-size:14px;font-weight:600;margin-bottom:6px">Nenhuma questão ainda</div>
        <div style="font-size:12px">Use o formulário acima para adicionar a primeira questão.</div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>Pergunta</th>
                    <th>Categoria</th>
                    <th>Alternativas</th>
                    <th>Correta</th>
                    <th style="text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($questions as $i => $q): ?>
            <tr>
                <td class="q-num-cell"><?= $i+1 ?></td>
                <td>
                    <div style="font-size:13px;font-weight:600;color:var(--gray-800);max-width:300px;line-height:1.4">
                        <?= e($q['question_text']) ?>
                    </div>
                    <?php if ($q['explanation']): ?>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:3px">
                        <i class="fa-solid fa-lightbulb"></i>
                        <?= e(mb_strimwidth($q['explanation'],0,60,'…')) ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($q['category']): ?>
                    <span class="badge badge-blue"><?= e($q['category']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--gray-300);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--gray-500)">
                    <?= count(array_filter([$q['option_a'],$q['option_b'],$q['option_c'],$q['option_d']])) ?> opções
                </td>
                <td>
                    <?php
                    $letters = ['A','B','C','D'];
                    $ci   = (int)$q['correct_answer'];
                    $opts = [$q['option_a'],$q['option_b'],$q['option_c'],$q['option_d']];
                    echo "<span class='correct-mark'><i class='fa-solid fa-check'></i> {$letters[$ci]}) "
                        . e(mb_strimwidth($opts[$ci],0,28,'…')) . "</span>";
                    ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a href="quiz-questions.php?id=<?= $quizId ?>&edit_q=<?= $q['id'] ?>#q-form-card"
                           class="row-action" title="Editar">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a href="quiz-questions.php?id=<?= $quizId ?>&del_q=<?= $q['id'] ?>"
                           class="row-action row-action--delete" title="Excluir"
                           onclick="return confirmAction('Excluir esta questão permanentemente?')">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Rodapé da lista -->
    <div style="padding:14px 20px;border-top:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <span style="font-size:12px;color:var(--gray-400)">
            <?= count($questions) ?> questão(ões) · aprovação com ≥ <?= $quiz['pass_percentage'] ?>%
            (<?= ceil(count($questions) * $quiz['pass_percentage'] / 100) ?> de <?= count($questions) ?> corretas)
        </span>
        <a href="quizzes.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Concluir e voltar
        </a>
    </div>
    <?php endif; ?>
</div>

</div><!-- /admin-wrap -->
<?php adminFoot(); ?>
