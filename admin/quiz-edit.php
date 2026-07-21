<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid    = adminCompanyId();
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz   = $quizId ? dbRow("SELECT * FROM quizzes WHERE id = ? AND company_id = ?", [$quizId, $cid]) : null;
$isNew  = !$quiz;

/* ── Importação Completa (CSV com config + questões) ───────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['full_csv']) && $isNew) {
    if (!companyCanCreateQuiz($cid)) {
        flash('Você atingiu o limite de quizzes do plano Free. Faça upgrade para criar mais.', 'error');
        redirect('quiz-edit.php');
    }
    $file = $_FILES['full_csv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('Erro ao fazer upload do arquivo.', 'error');
        redirect('quiz-edit.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash('Não foi possível ler o arquivo.', 'error');
        redirect('quiz-edit.php');
    }

    // Auto-detect delimiter: scan the full file (a section header like [QUIZ] has neither ; nor ,)
    $rawContent = stream_get_contents($handle);
    $delim      = substr_count($rawContent, ';') >= substr_count($rawContent, ',') ? ';' : ',';
    rewind($handle);

    // Lê todas as linhas
    $allRows = [];
    while (($row = fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        $allRows[] = $row;
    }
    fclose($handle);

    // Parseamento em seções [QUIZ] e [QUESTOES]
    $quizRow    = null;
    $qRows      = [];
    $section    = null;
    $skipNext   = false;   // pula a linha de cabeçalho de cada seção

    foreach ($allRows as $row) {
        // Strip UTF-8 BOM que o Excel adiciona ao primeiro campo do arquivo
        $first = trim(str_replace("\xEF\xBB\xBF", '', $row[0] ?? ''));

        if ($first === '[QUIZ]') {
            $section  = 'quiz';
            $skipNext = true;   // próxima linha é o cabeçalho da seção
            continue;
        }
        if ($first === '[QUESTOES]') {
            $section  = 'questoes';
            $skipNext = true;
            continue;
        }
        if ($skipNext) { $skipNext = false; continue; }   // pula cabeçalho
        if ($first === '' || $first[0] === '#') continue; // linha vazia ou comentário

        if ($section === 'quiz' && $quizRow === null) {
            $quizRow = $row;
        } elseif ($section === 'questoes') {
            $qRows[] = $row;
        }
    }

    if (!$quizRow || !trim($quizRow[0] ?? '')) {
        flash('Arquivo inválido: seção [QUIZ] com título ausente. Baixe o modelo e tente novamente.', 'error');
        redirect('quiz-edit.php');
    }

    // Extrai configuração do quiz
    $title   = trim($quizRow[0] ?? '');
    $desc    = trim($quizRow[1] ?? '');
    $sector  = trim($quizRow[2] ?? 'Geral') ?: 'Geral';
    $timer   = max(5, min(300, (int)($quizRow[3] ?? 30)));
    $passPct = max(0, min(100, (int)($quizRow[4] ?? 70)));

    // Cria o quiz
    dbExec("INSERT INTO quizzes
                (title,description,sector,created_by,time_per_question,pass_percentage,
                 show_feedback,has_certificate,randomize,allow_retake,active,visibility,company_id)
            VALUES (?,?,?,?,?,?,1,1,0,1,1,'all',?)",
        [$title, $desc, $sector, adminName(), $timer, $passPct, $cid]);
    $newQuizId = (int)dbLastId();

    // Importa as questões
    $letters  = ['A'=>0,'B'=>1,'C'=>2,'D'=>3,'a'=>0,'b'=>1,'c'=>2,'d'=>3,'0'=>0,'1'=>1,'2'=>2,'3'=>3];
    $imported = 0;
    $errors   = [];
    $order    = 1;

    $stmt = getDB()->prepare("
        INSERT INTO questions
            (quiz_id,company_id,question_text,category,option_a,option_b,option_c,option_d,
             correct_answer,explanation,sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");

    foreach ($qRows as $i => $cols) {
        $lineNum = $i + 1;

        if (count($cols) < 7) {
            $errors[] = "Questão $lineNum ignorada: apenas " . count($cols) . " coluna(s) (mínimo 7)";
            continue;
        }

        $qText   = trim($cols[0] ?? '');
        $cat     = trim($cols[1] ?? '');
        $optA    = trim($cols[2] ?? '');
        $optB    = trim($cols[3] ?? '');
        $optC    = trim($cols[4] ?? '');
        $optD    = trim($cols[5] ?? '');
        $correct = trim($cols[6] ?? '');
        $exp     = trim($cols[7] ?? '');

        if (!$qText)         { $errors[] = "Questão $lineNum ignorada: pergunta vazia";        continue; }
        if (!$optA || !$optB){ $errors[] = "Questão $lineNum ignorada: opções A e B ausentes"; continue; }
        if (!isset($letters[$correct])) {
            $errors[] = "Questão $lineNum ignorada: resposta '$correct' inválida (use A, B, C, D)";
            continue;
        }

        $idx     = $letters[$correct];
        $optsArr = [$optA, $optB, $optC, $optD];
        if (empty($optsArr[$idx])) {
            $errors[] = "Questão $lineNum ignorada: resposta correta aponta para opção vazia (" . ['A','B','C','D'][$idx] . ")";
            continue;
        }

        $stmt->execute([$newQuizId, $cid, $qText, $cat, $optA, $optB, $optC, $optD, $idx, $exp, $order++]);
        $imported++;
    }

    $warn = $errors ? ' (' . count($errors) . ' linha(s) ignoradas)' : '';
    flash("Quiz «{$title}» criado com {$imported} questão(ões) importada(s)!{$warn}", 'success');
    redirect("quiz-questions.php?id=$newQuizId&created=1");
}

/* ── Salvar Quiz (criação manual / edição) ─────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quiz'])) {
    $title      = trim($_POST['title']       ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $sector     = trim($_POST['sector']      ?? 'Geral');
    $timer      = (int)($_POST['timer']      ?? 30);
    $passPct    = (int)($_POST['pass_pct']   ?? 70);
    $maxQ       = (int)($_POST['max_questions'] ?? 0);
    $expiry      = !empty($_POST['expires_at'])   ? $_POST['expires_at']   : null;
    $visibleFrom = !empty($_POST['visible_from']) ? $_POST['visible_from'] : null;
    $feedback   = isset($_POST['feedback'])        ? 1 : 0;
    $hasCert    = isset($_POST['has_certificate']) ? 1 : 0;
    $randomize  = isset($_POST['randomize'])       ? 1 : 0;
    $retake     = isset($_POST['retake'])          ? 1 : 0;
    $active     = isset($_POST['active'])          ? 1 : 0;
    $createdBy  = trim($_POST['created_by'] ?? adminName());
    $visibility = in_array($_POST['visibility'] ?? '', ['all','sector']) ? $_POST['visibility'] : 'all';
    $targetSectors = array_map('intval', (array)($_POST['target_sectors'] ?? []));

    if (!$title) {
        flash('O título é obrigatório.', 'error');
        redirect($isNew ? 'quiz-edit.php' : "quiz-edit.php?id=$quizId");
    }

    if ($isNew && !companyCanCreateQuiz($cid)) {
        flash('Você atingiu o limite de quizzes do plano Free. Faça upgrade para criar mais.', 'error');
        redirect('quiz-edit.php');
    }

    if ($isNew) {
        dbExec("INSERT INTO quizzes
                    (title,description,sector,created_by,time_per_question,pass_percentage,
                     max_questions,expires_at,visible_from,show_feedback,has_certificate,randomize,
                     allow_retake,active,visibility,company_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$title,$desc,$sector,$createdBy,$timer,$passPct,$maxQ,$expiry,$visibleFrom,
             $feedback,$hasCert,$randomize,$retake,$active,$visibility,$cid]);
        $quizId = (int)dbLastId();
        flash('Quiz criado! Agora adicione as questões.', 'success');
        redirect("quiz-questions.php?id=$quizId&created=1");
    }

    dbExec("UPDATE quizzes SET
                title=?,description=?,sector=?,created_by=?,time_per_question=?,
                pass_percentage=?,max_questions=?,expires_at=?,visible_from=?,show_feedback=?,
                has_certificate=?,randomize=?,allow_retake=?,active=?,visibility=?,
                updated_at=datetime('now','localtime')
            WHERE id=? AND company_id=?",
        [$title,$desc,$sector,$createdBy,$timer,$passPct,$maxQ,$expiry,$visibleFrom,
         $feedback,$hasCert,$randomize,$retake,$active,$visibility,$quizId,$cid]);

    dbExec("DELETE FROM quiz_assignments WHERE quiz_id IN (SELECT id FROM quizzes WHERE id=? AND company_id=?)", [$quizId,$cid]);
    if ($visibility === 'sector' && $targetSectors) {
        $stmt = getDB()->prepare("INSERT OR IGNORE INTO quiz_assignments (quiz_id,sector_id) VALUES (?,?)");
        foreach ($targetSectors as $sid) {
            if (dbRow("SELECT id FROM sectors WHERE id=? AND company_id=?", [$sid,$cid]))
                $stmt->execute([$quizId,$sid]);
        }
    }

    flash('Configurações salvas!', 'success');
    redirect("quiz-edit.php?id=$quizId");
}

/* ── Load ─────────────────────────────────────────────────── */
$quiz = $quizId ? dbRow("SELECT * FROM quizzes WHERE id=? AND company_id=?", [$quizId,$cid]) : null;
$assignedSectorIds = $quizId
    ? array_column(dbRows("SELECT sector_id FROM quiz_assignments WHERE quiz_id=?", [$quizId]), 'sector_id')
    : [];
$allSectors = dbRows("SELECT id,name FROM sectors WHERE company_id=? ORDER BY name ASC", [$cid]);
$qCount     = $quizId ? (int)(dbRow("SELECT COUNT(*) AS c FROM questions WHERE quiz_id=?", [$quizId])['c'] ?? 0) : 0;

adminHead($isNew ? 'Novo Quiz' : 'Configurações: '.($quiz['title'] ?? ''), 'quizzes.php');
?>
<style>
/* Step indicator */
.quiz-steps { display:flex;align-items:center;gap:0;margin-bottom:28px; }
.quiz-step  { display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600; }
.quiz-step-num {
    width:28px;height:28px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:700;flex-shrink:0;
}
.quiz-step.active .quiz-step-num  { background:var(--pacific);color:#fff; }
.quiz-step.pending .quiz-step-num { background:rgba(255,255,255,.15);color:rgba(255,255,255,.5); }
.quiz-step.active  span { color:#fff; }
.quiz-step.pending span { color:rgba(255,255,255,.45); }
.quiz-step-line      { flex:1;height:2px;background:rgba(255,255,255,.15);margin:0 10px;min-width:32px; }

/* Divider "ou" entre as duas opções de criação */
.or-divider {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 28px 0;
    color: var(--gray-400);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.or-divider::before,
.or-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--gray-200);
}

/* Card de importação completa */
.import-full-card {
    border: 2px dashed var(--gray-200);
    border-radius: 14px;
    padding: 28px 28px 24px;
    background: #fafbfc;
    transition: border-color .2s;
}
.import-full-card:hover { border-color: var(--pacific); }
.import-full-card:has(input[type=file]:focus) { border-color: var(--pacific); }

.import-full-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

/* Edit mode */
.cfg-section { border-top:1px solid var(--gray-100);padding-top:22px;margin-top:22px; }
.cfg-section-lbl {
    font-size:11px;font-weight:700;text-transform:uppercase;
    letter-spacing:.7px;color:var(--gray-400);
    margin-bottom:16px;display:flex;align-items:center;gap:7px;
}
.cfg-section-lbl i { color:var(--pacific); }
</style>

<div class="admin-wrap">

<!-- Breadcrumb -->
<div class="flex items-center gap-8 mb-20" style="font-size:13px">
    <a href="quizzes.php" style="color:var(--blue);text-decoration:none">
        <i class="fa-solid fa-arrow-left"></i> Quizzes
    </a>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)"><?= $isNew ? 'Novo Quiz' : e($quiz['title']) ?></span>
    <?php if (!$isNew): ?>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)">Configurações</span>
    <?php endif; ?>
</div>

<?php if ($isNew): ?>
<!-- ══════════════════════════════════════════════════════════
     MODO CRIAÇÃO
══════════════════════════════════════════════════════════ -->

<!-- Step indicator -->
<div class="quiz-steps">
    <div class="quiz-step active">
        <div class="quiz-step-num">1</div>
        <span>Dados do Quiz</span>
    </div>
    <div class="quiz-step-line"></div>
    <div class="quiz-step pending">
        <div class="quiz-step-num">2</div>
        <span>Adicionar Questões</span>
    </div>
</div>

<!-- ── OPÇÃO A: Criar manualmente ─────────────────────────── -->
<div class="card">
    <div class="card-header" style="justify-content:space-between">
        <h2><i class="fa-solid fa-keyboard" style="color:var(--pacific)"></i> Preencher manualmente</h2>
        <span class="badge badge-blue" style="font-size:11px">Opção A</span>
    </div>

    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:12px 16px;font-size:13px;color:var(--prussian);margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;line-height:1.5">
        <i class="fa-solid fa-circle-info" style="color:var(--pacific);margin-top:1px;flex-shrink:0"></i>
        <div>Preencha os dados básicos. Após salvar, você vai direto para adicionar as questões.
        As configurações avançadas ficam disponíveis depois em <em>Configurações do quiz</em>.</div>
    </div>

    <form method="post">
        <input type="hidden" name="save_quiz"       value="1"/>
        <input type="hidden" name="feedback"        value="1"/>
        <input type="hidden" name="has_certificate" value="1"/>
        <input type="hidden" name="retake"          value="1"/>
        <input type="hidden" name="active"          value="1"/>
        <input type="hidden" name="visibility"      value="all"/>
        <input type="hidden" name="randomize"       value="0"/>
        <input type="hidden" name="max_questions"   value="0"/>
        <input type="hidden" name="created_by"      value="<?= e(adminName()) ?>"/>

        <div class="form-group">
            <label class="form-label">Título do Quiz *</label>
            <input class="form-control" type="text" name="title" required autofocus
                   placeholder="Ex: Treinamento de Biossegurança 2025"
                   style="font-size:16px;padding:12px 16px"/>
        </div>
        <div class="form-group">
            <label class="form-label">Descrição / Instruções
                <span style="color:var(--gray-400);font-weight:400">(opcional)</span>
            </label>
            <textarea class="form-textarea" name="description"
                      placeholder="Descreva o objetivo e as instruções para o participante…"
                      style="min-height:80px"></textarea>
        </div>
        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
            <div class="form-group">
                <label class="form-label">Setor / Área</label>
                <?php $sectors = dbRows("SELECT name FROM sectors WHERE company_id=? ORDER BY name ASC", [$cid]); ?>
                <select class="form-control" name="sector">
                    <?php foreach ($sectors ?: [['name'=>'Geral']] as $s): ?>
                    <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tempo por Questão (seg)</label>
                <input class="form-control" type="number" name="timer" min="5" max="300" value="30"/>
            </div>
            <div class="form-group">
                <label class="form-label">% Mínima para Aprovação</label>
                <input class="form-control" type="number" name="pass_pct" min="0" max="100" value="70"/>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:8px">
            <a href="quizzes.php" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-primary" style="font-size:15px;padding:12px 28px">
                <i class="fa-solid fa-arrow-right"></i> Criar e Adicionar Questões
            </button>
        </div>
    </form>
</div>

<!-- Divisor -->
<div class="or-divider">ou importe um quiz completo</div>

<!-- ── OPÇÃO B: Importar CSV completo ─────────────────────── -->
<div class="import-full-card">
    <div class="import-full-header">
        <div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                <h2 style="font-size:16px;font-weight:700;color:var(--gray-800);margin:0">
                    <i class="fa-solid fa-file-arrow-up" style="color:var(--pacific)"></i>
                    Importar Quiz Completo via CSV
                </h2>
                <span class="badge badge-blue" style="font-size:11px">Opção B</span>
            </div>
            <p style="font-size:13px;color:var(--gray-500);margin:0;line-height:1.5">
                Baixe o modelo, preencha no Excel e importe — o quiz e todas as questões são criados de uma vez.
            </p>
        </div>
        <a href="csv-template-quiz.php" class="btn btn-outline" style="white-space:nowrap;flex-shrink:0">
            <i class="fa-solid fa-download"></i> Baixar Modelo CSV
        </a>
    </div>

    <!-- Resumo do formato esperado -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px">
        <div style="background:#fff;border:1px solid var(--gray-100);border-radius:10px;padding:14px 16px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--pacific);margin-bottom:10px">
                <i class="fa-solid fa-gear"></i> Seção [QUIZ] — linha de dados
            </div>
            <div style="font-size:12px;color:var(--gray-600);line-height:1.8">
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">titulo</code> <span style="color:var(--red)">*</span></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">descricao</code></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">setor</code></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">tempo_seg</code> <span style="color:var(--gray-400);font-size:11px">padrão 30</span></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">aprovacao_pct</code> <span style="color:var(--gray-400);font-size:11px">padrão 70</span></div>
            </div>
        </div>
        <div style="background:#fff;border:1px solid var(--gray-100);border-radius:10px;padding:14px 16px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--green);margin-bottom:10px">
                <i class="fa-solid fa-list-ol"></i> Seção [QUESTOES] — uma questão por linha
            </div>
            <div style="font-size:12px;color:var(--gray-600);line-height:1.8">
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">pergunta</code> <span style="color:var(--red)">*</span></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">categoria</code></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">opcao_a, opcao_b</code> <span style="color:var(--red)">*</span></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">opcao_c, opcao_d</code></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">resposta_correta</code> <span style="color:var(--red)">*</span> <span style="color:var(--gray-400);font-size:11px">A, B, C ou D</span></div>
                <div><code style="background:var(--gray-100);padding:1px 6px;border-radius:3px">explicacao</code></div>
            </div>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="full_import" value="1"/>
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:240px;margin-bottom:0">
                <label class="form-label">Arquivo CSV *
                    <span style="color:var(--gray-400);font-weight:400"> — separador <code>;</code> ou <code>,</code> · encoding UTF-8 · máx. 2 MB</span>
                </label>
                <input class="form-control" type="file" name="full_csv" accept=".csv,.txt" required/>
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;margin-bottom:1px">
                <i class="fa-solid fa-rocket"></i> Criar Quiz e Importar Questões
            </button>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     MODO EDIÇÃO — configurações completas
══════════════════════════════════════════════════════════ -->

<div class="flex items-center justify-between mb-20" style="flex-wrap:wrap;gap:12px">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:var(--gray-800)">
            <i class="fa-solid fa-sliders" style="color:var(--pacific)"></i>
            <?= e($quiz['title']) ?>
        </h1>
        <p class="text-muted" style="font-size:13px;margin-top:3px">Configurações do quiz</p>
    </div>
    <a href="quiz-questions.php?id=<?= $quizId ?>" class="btn btn-primary">
        <i class="fa-solid fa-list-ol"></i>
        Gerenciar Questões
        <?php if ($qCount > 0): ?>
        <span style="background:rgba(255,255,255,.25);border-radius:10px;padding:1px 8px;font-size:11px;margin-left:4px"><?= $qCount ?></span>
        <?php endif; ?>
    </a>
</div>

<div class="card">
    <form method="post">
        <input type="hidden" name="save_quiz" value="1"/>

        <!-- Básico -->
        <div class="form-group">
            <label class="form-label">Título *</label>
            <input class="form-control" type="text" name="title" required
                   value="<?= e($quiz['title']) ?>"/>
        </div>
        <div class="form-group">
            <label class="form-label">Descrição / Instruções</label>
            <textarea class="form-textarea" name="description"
                      placeholder="Instruções para o participante…"><?= e($quiz['description'] ?? '') ?></textarea>
        </div>

        <!-- Organização -->
        <div class="cfg-section">
            <div class="cfg-section-lbl"><i class="fa-solid fa-folder"></i> Organização</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Setor / Área</label>
                    <?php $sectors = dbRows("SELECT name FROM sectors WHERE company_id=? ORDER BY name ASC", [$cid]); ?>
                    <select class="form-control" name="sector">
                        <?php foreach ($sectors ?: [['name'=>'Geral']] as $s): ?>
                        <option value="<?= e($s['name']) ?>" <?= ($quiz['sector'] ?? 'Geral') == $s['name'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Criado Por</label>
                    <input class="form-control" type="text" name="created_by"
                           value="<?= e($quiz['created_by'] ?? adminName()) ?>"/>
                </div>
            </div>
        </div>

        <!-- Regras -->
        <div class="cfg-section">
            <div class="cfg-section-lbl"><i class="fa-solid fa-sliders"></i> Regras da Avaliação</div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tempo por Questão (seg)</label>
                    <input class="form-control" type="number" name="timer" min="5" max="300"
                           value="<?= $quiz['time_per_question'] ?? 30 ?>"/>
                </div>
                <div class="form-group">
                    <label class="form-label">% Mínima para Aprovação</label>
                    <input class="form-control" type="number" name="pass_pct" min="0" max="100"
                           value="<?= $quiz['pass_percentage'] ?? 70 ?>"/>
                </div>
                <div class="form-group">
                    <label class="form-label">Máx. Questões por Sessão
                        <span style="font-size:11px;color:var(--gray-400);font-weight:400">(0 = todas)</span>
                    </label>
                    <input class="form-control" type="number" name="max_questions" min="0"
                           value="<?= $quiz['max_questions'] ?? 0 ?>"/>
                </div>
                <div class="form-group">
                    <label class="form-label">Exibir a partir de
                        <span style="font-size:11px;color:var(--gray-400);font-weight:400">(opcional)</span>
                    </label>
                    <input class="form-control" type="date" name="visible_from"
                           value="<?= !empty($quiz['visible_from']) ? date('Y-m-d', strtotime($quiz['visible_from'])) : '' ?>"
                           title="Deixe em branco para exibir imediatamente"/>
                </div>
                <div class="form-group">
                    <label class="form-label">Expira em
                        <span style="font-size:11px;color:var(--gray-400);font-weight:400">(opcional)</span>
                    </label>
                    <input class="form-control" type="date" name="expires_at"
                           value="<?= !empty($quiz['expires_at']) ? date('Y-m-d', strtotime($quiz['expires_at'])) : '' ?>"
                           title="Deixe em branco para não expirar"/>
                </div>
            </div>
        </div>

        <!-- Opções -->
        <div class="cfg-section">
            <div class="cfg-section-lbl"><i class="fa-solid fa-toggle-on"></i> Opções</div>
            <div class="flex flex-wrap gap-12">
                <?php
                $opts = [
                    'feedback'        => ['Mostrar feedback após resposta',   $quiz['show_feedback']   ?? 1],
                    'has_certificate' => ['Emitir certificado ao concluir',   $quiz['has_certificate'] ?? 1],
                    'randomize'       => ['Embaralhar questões',              $quiz['randomize']       ?? 0],
                    'retake'          => ['Permitir repetir o quiz',          $quiz['allow_retake']    ?? 1],
                    'active'          => ['Quiz ativo (visível no portal)',    $quiz['active']          ?? 1],
                ];
                foreach ($opts as $name => [$label, $checked]): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--gray-600);cursor:pointer">
                    <input type="checkbox" name="<?= $name ?>" <?= $checked ? 'checked' : '' ?>
                           style="width:16px;height:16px;accent-color:var(--blue)"/>
                    <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Visibilidade -->
        <div class="cfg-section">
            <?php $curVis = $quiz['visibility'] ?? 'all'; ?>
            <div class="cfg-section-lbl"><i class="fa-solid fa-eye"></i> Visibilidade</div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;padding:9px 16px;border:2px solid var(--gray-200);border-radius:9px;transition:.15s" id="lbl-vis-all">
                    <input type="radio" name="visibility" value="all" <?= $curVis === 'all' ? 'checked' : '' ?>
                           onchange="toggleVisUI()" style="accent-color:var(--blue)"/>
                    <i class="fa-solid fa-globe" style="color:var(--blue)"></i> Todos os colaboradores
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;padding:9px 16px;border:2px solid var(--gray-200);border-radius:9px;transition:.15s" id="lbl-vis-sector">
                    <input type="radio" name="visibility" value="sector" <?= $curVis === 'sector' ? 'checked' : '' ?>
                           onchange="toggleVisUI()" style="accent-color:var(--blue)"/>
                    <i class="fa-solid fa-sitemap" style="color:var(--pacific)"></i> Setores específicos
                </label>
            </div>
            <div id="sector-picker" style="display:<?= $curVis === 'sector' ? 'block' : 'none' ?>">
                <div style="font-size:12px;color:var(--gray-500);margin-bottom:10px">Selecione os setores com acesso:</div>
                <?php if (empty($allSectors)): ?>
                <p style="font-size:13px;color:var(--gray-400)">Nenhum setor. <a href="sectors.php">Criar setor</a></p>
                <?php else: ?>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <?php foreach ($allSectors as $s): ?>
                    <label style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;cursor:pointer;padding:7px 14px;border:2px solid var(--gray-200);border-radius:8px;transition:.15s">
                        <input type="checkbox" name="target_sectors[]" value="<?= $s['id'] ?>"
                               <?= in_array($s['id'], $assignedSectorIds) ? 'checked' : '' ?>
                               style="accent-color:var(--blue)"/>
                        <?= e($s['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-100)">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="../quiz.php?id=<?= $quizId ?>" target="_blank" class="btn btn-outline">
                    <i class="fa-solid fa-eye"></i> Pré-visualizar
                </a>
                <a href="import.php?quiz=<?= $quizId ?>" class="btn btn-outline">
                    <i class="fa-solid fa-file-import"></i> Importar Questões CSV
                </a>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-circle-check"></i> Salvar Configurações
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

</div><!-- /admin-wrap -->
<script>
function toggleVisUI() {
    const isSector = document.querySelector('[name=visibility][value=sector]')?.checked;
    document.getElementById('sector-picker').style.display = isSector ? 'block' : 'none';
    const all    = document.getElementById('lbl-vis-all');
    const sector = document.getElementById('lbl-vis-sector');
    if (all)    all.style.borderColor    = isSector ? 'var(--gray-200)' : 'var(--blue)';
    if (sector) sector.style.borderColor = isSector ? 'var(--blue)'     : 'var(--gray-200)';
}
if (document.getElementById('lbl-vis-all')) toggleVisUI();
</script>
<?php adminFoot(); ?>
