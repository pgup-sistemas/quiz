<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid    = adminCompanyId();
$quizId = (int)($_GET['quiz'] ?? 0);
$quiz   = $quizId ? dbRow("SELECT * FROM quizzes WHERE id=? AND company_id=?", [$quizId, $cid]) : null;

if (!$quiz) {
    flash('Quiz não encontrado.', 'error');
    redirect('quizzes.php');
}

$imported = 0;
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('Erro ao fazer upload do arquivo.', 'error');
        redirect("import.php?quiz=$quizId");
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash('Não foi possível ler o arquivo.', 'error');
        redirect("import.php?quiz=$quizId");
    }

    // ── Auto-detect delimiter (lê primeira linha, conta ; vs ,) ──────────────
    $firstLine = fgets($handle);
    $delim     = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    // Pula cabeçalho
    fgetcsv($handle, 0, $delim, '"', '');

    $maxOrder = (int)(dbRow("SELECT COALESCE(MAX(sort_order),0) AS m FROM questions WHERE quiz_id=?", [$quizId])['m'] ?? 0);
    $row      = 2;

    $stmt = getDB()->prepare("
        INSERT INTO questions
            (quiz_id, company_id, question_text, category,
             option_a, option_b, option_c, option_d,
             correct_answer, explanation, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");

    // Aceita A/B/C/D maiúsculo, minúsculo e índice numérico 0-3
    $letters = ['A'=>0,'B'=>1,'C'=>2,'D'=>3,'a'=>0,'b'=>1,'c'=>2,'d'=>3,
                '0'=>0,'1'=>1,'2'=>2,'3'=>3];

    while (($cols = fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        // Mínimo: pergunta + A + B + C/D opcional + resposta correta (col 6)
        if (count($cols) < 7) {
            $errors[] = "Linha $row ignorada: apenas " . count($cols) . " coluna(s) — mínimo 7 (pergunta, categoria, A, B, C, D, resposta). Separador detectado: '$delim'";
            $row++;
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

        // Validações básicas
        if (!$qText) {
            $errors[] = "Linha $row ignorada: pergunta vazia";
            $row++; continue;
        }
        if (!$optA || !$optB) {
            $errors[] = "Linha $row ignorada: opções A e B são obrigatórias";
            $row++; continue;
        }
        if (!isset($letters[$correct])) {
            $errors[] = "Linha $row ignorada: resposta correta '$correct' inválida (use A, B, C, D ou 0, 1, 2, 3)";
            $row++; continue;
        }

        $correctIdx = $letters[$correct];

        // Valida que a opção marcada como correta existe
        $optsArr = [$optA, $optB, $optC, $optD];
        if (empty($optsArr[$correctIdx])) {
            $errors[] = "Linha $row ignorada: resposta correta aponta para opção vazia (" . ['A','B','C','D'][$correctIdx] . ")";
            $row++; continue;
        }

        $maxOrder++;
        $stmt->execute([$quizId, $cid, $qText, $cat, $optA, $optB, $optC, $optD, $correctIdx, $exp, $maxOrder]);
        $imported++;
        $row++;
    }
    fclose($handle);

    if ($imported > 0) {
        $warn = count($errors) ? ' (' . count($errors) . ' linha(s) ignoradas)' : '';
        flash("$imported questão(ões) importada(s) com sucesso!$warn", 'success');
    } else {
        flash('Nenhuma questão importada. Verifique o formato do arquivo e os erros abaixo.', 'error');
    }
    redirect("quiz-questions.php?id=$quizId");
}

adminHead('Importar Questões', 'quizzes.php');
?>
<style>
.import-format-card {
    background: #f8fafc;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
}
.col-row {
    display: grid;
    grid-template-columns: 36px 1fr auto 2fr;
    gap: 12px;
    align-items: start;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 13px;
}
.col-row:last-child { border-bottom: none; }
.col-num {
    width: 28px; height: 28px;
    background: var(--prussian);
    color: #fff;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; flex-shrink: 0;
}
.col-name { font-weight: 700; color: var(--gray-800); }
.col-example { color: var(--gray-400); font-style: italic; font-size: 12px; }
</style>

<div class="admin-wrap">

<!-- Breadcrumb -->
<div class="flex items-center gap-8 mb-24" style="font-size:13px">
    <a href="quizzes.php" style="color:var(--blue);text-decoration:none">
        <i class="fa-solid fa-arrow-left"></i> Quizzes
    </a>
    <span style="color:var(--gray-300)">/</span>
    <a href="quiz-questions.php?id=<?= $quizId ?>" style="color:var(--blue);text-decoration:none">
        <?= e($quiz['title']) ?>
    </a>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)">Importar CSV</span>
</div>

<div class="flex items-center justify-between mb-20">
    <div>
        <h1 style="font-size:20px;font-weight:700;color:var(--gray-800)">
            <i class="fa-solid fa-file-arrow-up" style="color:var(--pacific)"></i> Importar Questões via CSV
        </h1>
        <p class="text-muted" style="font-size:13px;margin-top:3px">
            Quiz: <strong><?= e($quiz['title']) ?></strong>
        </p>
    </div>
    <a href="csv-template.php?quiz=<?= $quizId ?>" class="btn btn-outline">
        <i class="fa-solid fa-download"></i> Baixar Modelo CSV
    </a>
</div>

<!-- Formato esperado -->
<div class="card">
    <div class="card-header">
        <h2><i class="fa-solid fa-table-list" style="color:var(--pacific)"></i> Formato do CSV</h2>
    </div>

    <p style="font-size:13px;color:var(--gray-600);margin-bottom:16px">
        A primeira linha é o cabeçalho e será ignorada. Separador aceito: ponto-e-vírgula <code>;</code> ou vírgula <code>,</code>
        (detectado automaticamente). Encoding: <strong>UTF-8</strong>.
    </p>

    <div class="import-format-card">
        <div class="col-row" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-400);padding-bottom:8px">
            <span>Col.</span><span>Campo</span><span>Req.</span><span>Exemplo</span>
        </div>
        <div class="col-row">
            <div class="col-num">1</div>
            <div class="col-name">Pergunta</div>
            <span class="badge badge-red">Sim</span>
            <div class="col-example">Qual EPI é obrigatório ao manusear amostras biológicas?</div>
        </div>
        <div class="col-row">
            <div class="col-num">2</div>
            <div class="col-name">Categoria</div>
            <span class="badge badge-gray">Não</span>
            <div class="col-example">Biossegurança</div>
        </div>
        <div class="col-row">
            <div class="col-num">3</div>
            <div class="col-name">Opção A</div>
            <span class="badge badge-red">Sim</span>
            <div class="col-example">Máscara N95</div>
        </div>
        <div class="col-row">
            <div class="col-num">4</div>
            <div class="col-name">Opção B</div>
            <span class="badge badge-red">Sim</span>
            <div class="col-example">Luvas de nitrila e avental</div>
        </div>
        <div class="col-row">
            <div class="col-num">5</div>
            <div class="col-name">Opção C</div>
            <span class="badge badge-gray">Não</span>
            <div class="col-example">Óculos de proteção</div>
        </div>
        <div class="col-row">
            <div class="col-num">6</div>
            <div class="col-name">Opção D</div>
            <span class="badge badge-gray">Não</span>
            <div class="col-example">Capote estéril</div>
        </div>
        <div class="col-row" style="background:#f0f9ff;border-radius:8px;padding:10px;margin-top:4px;border:none">
            <div class="col-num" style="background:var(--green)">7</div>
            <div class="col-name" style="color:var(--green)">Resposta Correta</div>
            <span class="badge badge-red">Sim</span>
            <div class="col-example">
                <strong style="color:var(--gray-700)">B</strong>
                <span style="color:var(--gray-400)"> — use A, B, C ou D (ou 0, 1, 2, 3)</span>
            </div>
        </div>
        <div class="col-row">
            <div class="col-num">8</div>
            <div class="col-name">Explicação</div>
            <span class="badge badge-gray">Não</span>
            <div class="col-example">Luvas e avental são os EPIs mínimos obrigatórios conforme NR-32.</div>
        </div>
    </div>

    <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;font-size:12px;color:#78350f;margin-bottom:20px">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong>Atenção:</strong> Se Opção C ou D estiver vazia, não aponte a resposta correta para elas —
        a linha será ignorada com erro.
        A coluna <strong>7 (Resposta Correta)</strong> é obrigatória; linhas sem ela são descartadas.
    </div>

    <!-- Upload -->
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Arquivo CSV *</label>
            <input class="form-control" type="file" name="csv_file" accept=".csv,.txt" required/>
            <div class="form-hint">Formato .csv — separador <code>;</code> ou <code>,</code> — máx. 2 MB — encoding UTF-8</div>
        </div>
        <div class="flex gap-8">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-file-import"></i> Importar Questões
            </button>
            <a href="quiz-questions.php?id=<?= $quizId ?>" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>

</div><!-- /admin-wrap -->
<?php adminFoot(); ?>
