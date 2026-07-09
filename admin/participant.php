<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$pid = (int)($_GET['id'] ?? 0);
if (!$pid) redirect('results.php');

$p = dbRow("
    SELECT p.*, q.title AS quiz_title, q.pass_percentage AS q_pass_pct, q.id AS quiz_id
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE p.id = ?
", [$pid]);

if (!$p) { flash('Participação não encontrada.', 'error'); redirect('results.php'); }

$answers = dbRows("
    SELECT a.*, q.question_text, q.category,
           q.option_a, q.option_b, q.option_c, q.option_d,
           q.correct_answer, q.explanation
    FROM answers a
    JOIN questions q ON q.id = a.question_id
    WHERE a.participant_id = ?
    ORDER BY a.id ASC
", [$pid]);

adminHead('Detalhes do Participante', 'results.php');
?>
<div class="admin-wrap">

<div class="flex items-center gap-8 mb-24" style="font-size:13px">
    <a href="results.php" style="color:var(--blue);text-decoration:none">← Resultados</a>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)"><?= e($p['name']) ?></span>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:24px">

    <!-- Participant Info -->
    <div class="card">
        <div class="card-header"><h2>👤 Participante</h2></div>
        <div style="font-size:24px;font-weight:700;color:var(--gray-800);margin-bottom:4px"><?= e($p['name']) ?></div>
        <?php if ($p['email']): ?>
        <div style="font-size:13px;color:var(--gray-400);margin-bottom:12px"><?= e($p['email']) ?></div>
        <?php endif; ?>
        <div class="flex flex-wrap gap-8">
            <span class="badge badge-blue"><?= e($p['sector']) ?></span>
            <?php if ($p['passed']): ?>
            <span class="badge badge-green">✅ Aprovado</span>
            <?php else: ?>
            <span class="badge badge-red">❌ Reprovado</span>
            <?php endif; ?>
        </div>
        <hr style="border:none;border-top:1px solid var(--gray-100);margin:16px 0"/>
        <div style="font-size:13px;color:var(--gray-600);line-height:2">
            <b>Quiz:</b> <?= e($p['quiz_title']) ?><br/>
            <b>Data:</b> <?= $p['completed_at'] ? date('d/m/Y H:i', strtotime($p['completed_at'])) : '–' ?><br/>
            <b>Tempo Médio:</b> <?= number_format($p['avg_time'],1) ?>s / questão
        </div>
    </div>

    <!-- Score -->
    <div class="card">
        <div class="card-header"><h2>📊 Resultado</h2></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
            <?php
            $pct = $p['percentage'];
            $scoreColor = $p['passed'] ? 'var(--green)' : 'var(--red)';
            ?>
            <div style="text-align:center">
                <div style="font-size:48px;font-weight:700;color:<?= $scoreColor ?>"><?= $pct ?>%</div>
                <div style="font-size:12px;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px">Nota Final</div>
                <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Mínimo: <?= $p['q_pass_pct'] ?>%</div>
            </div>
            <div style="text-align:center;padding-top:8px">
                <div style="font-size:32px;font-weight:700;color:var(--green)"><?= $p['score'] ?></div>
                <div style="font-size:12px;color:var(--gray-400)">Acertos</div>
                <div style="margin-top:8px">
                <div style="font-size:32px;font-weight:700;color:var(--red)"><?= $p['total_questions'] - $p['score'] ?></div>
                <div style="font-size:12px;color:var(--gray-400)">Erros</div>
                </div>
            </div>
            <div style="text-align:center;padding-top:8px">
                <?php
                $bar_w = round(($p['score'] / max($p['total_questions'],1)) * 100);
                ?>
                <div style="font-size:32px;font-weight:700;color:var(--blue)"><?= $p['total_questions'] ?></div>
                <div style="font-size:12px;color:var(--gray-400)">Questões Total</div>
                <div style="margin-top:16px;background:var(--gray-100);border-radius:20px;height:8px">
                    <div style="background:<?= $scoreColor ?>;height:8px;border-radius:20px;width:<?= $bar_w ?>%;transition:.5s"></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Answer Detail -->
<?php if (!empty($answers)): ?>
<div class="card">
    <div class="card-header"><h2>📋 Respostas Detalhadas</h2></div>
    <?php $ltrs = ['A','B','C','D']; ?>
    <?php foreach ($answers as $i => $a): ?>
    <div style="padding:16px 0;border-bottom:1px solid var(--gray-100);<?= $i === 0 ? 'padding-top:0' : '' ?>">
        <div class="flex items-center gap-8" style="margin-bottom:8px">
            <span style="font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px">Q<?= $i+1 ?></span>
            <?php if ($a['category']): ?><span class="badge badge-blue"><?= e($a['category']) ?></span><?php endif; ?>
            <span class="badge <?= $a['is_correct'] ? 'badge-green' : 'badge-red' ?>"><?= $a['is_correct'] ? '✅ Correto' : ($a['selected_answer'] == -1 ? '⏱ Timeout' : '❌ Incorreto') ?></span>
            <span style="margin-left:auto;font-size:12px;color:var(--gray-400)">⏱ <?= $a['time_taken'] ?>s</span>
        </div>
        <div style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;line-height:1.4"><?= e($a['question_text']) ?></div>
        <div style="display:flex;flex-direction:column;gap:6px">
            <?php
            $opts = [$a['option_a'],$a['option_b'],$a['option_c'],$a['option_d']];
            foreach ($opts as $oi => $opt):
                if (!trim($opt)) continue;
                $isCorrect  = $oi == (int)$a['correct_answer'];
                $isSelected = $oi == (int)$a['selected_answer'];
                $bg = $isCorrect ? '#e8f8f4' : ($isSelected && !$isCorrect ? '#fff0f1' : 'var(--gray-50)');
                $border = $isCorrect ? 'var(--green)' : ($isSelected && !$isCorrect ? 'var(--red)' : 'var(--gray-200)');
            ?>
            <div style="background:<?= $bg ?>;border:1.5px solid <?= $border ?>;border-radius:8px;padding:8px 12px;font-size:13px;display:flex;align-items:center;gap:8px">
                <span style="font-weight:700;min-width:22px;color:<?= $isCorrect ? 'var(--green)' : ($isSelected && !$isCorrect ? 'var(--red)' : 'var(--gray-400)') ?>"><?= $ltrs[$oi] ?>)</span>
                <span><?= e($opt) ?></span>
                <?php if ($isCorrect): ?><span style="margin-left:auto;font-size:11px;font-weight:700;color:var(--green)">✓ Correta</span><?php endif; ?>
                <?php if ($isSelected && !$isCorrect): ?><span style="margin-left:auto;font-size:11px;font-weight:700;color:var(--red)">← Sua resposta</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($a['explanation']): ?>
        <div style="background:var(--blue-pale);border-left:3px solid var(--blue);border-radius:6px;padding:10px 12px;margin-top:10px;font-size:12.5px;color:var(--blue-deeper)">
            💡 <?= e($a['explanation']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

</div>
<?php adminFoot(); ?>
