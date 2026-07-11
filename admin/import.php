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

    // ── Auto-detect delimiter (read raw first line, count ; vs ,) ──────────
    $firstLine = fgets($handle);
    $delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    // Skip header row using the detected delimiter
    fgetcsv($handle, 0, $delim, '"', '');

    $maxOrder = dbRow("SELECT COALESCE(MAX(sort_order),0) AS m FROM questions WHERE quiz_id=?", [$quizId])['m'];
    $row = 2;

    $stmt = getDB()->prepare("
        INSERT INTO questions (quiz_id, question_text, category, option_a, option_b, option_c, option_d,
                               correct_answer, explanation, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    $letters = ['A'=>0,'B'=>1,'C'=>2,'D'=>3,'a'=>0,'b'=>1,'c'=>2,'d'=>3,'0'=>0,'1'=>1,'2'=>2,'3'=>3];

    while (($cols = fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        if (count($cols) < 6) {
            $errors[] = "Linha $row ignorada: colunas insuficientes (" . count($cols) . "). Separador detectado: '$delim'";
            $row++;
            continue;
        }

        $qText   = trim($cols[0] ?? '');
        $cat     = trim($cols[1] ?? '');
        $optA    = trim($cols[2] ?? '');
        $optB    = trim($cols[3] ?? '');
        $optC    = trim($cols[4] ?? '');
        $optD    = trim($cols[5] ?? '');
        $correct = trim($cols[6] ?? '0');
        $exp     = trim($cols[7] ?? '');

        if (!$qText || !$optA || !$optB) {
            $errors[] = "Linha $row ignorada: pergunta ou opções A/B ausentes";
            $row++;
            continue;
        }

        $correctIdx = $letters[$correct] ?? 0;
        $maxOrder++;
        $stmt->execute([$quizId, $qText, $cat, $optA, $optB, $optC, $optD, $correctIdx, $exp, $maxOrder]);
        $imported++;
        $row++;
    }
    fclose($handle);

    if ($imported > 0) {
        flash("$imported questão(ões) importada(s) com sucesso!" . (count($errors) ? ' (' . count($errors) . ' linha(s) ignoradas)' : ''), 'success');
    } else {
        flash('Nenhuma questão importada. Verifique o formato do arquivo.', 'error');
    }
    redirect("quiz-edit.php?id=$quizId#questions");
}

adminHead('Importar Questões', 'quizzes.php');
?>
<div class="admin-wrap" style="max-width:760px">

<div class="flex items-center gap-8 mb-24" style="font-size:13px">
    <a href="quizzes.php" style="color:var(--blue);text-decoration:none">← Quizzes</a>
    <span style="color:var(--gray-300)">/</span>
    <a href="quiz-edit.php?id=<?= $quizId ?>" style="color:var(--blue);text-decoration:none"><?= e($quiz['title']) ?></a>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)">Importar CSV</span>
</div>

<div class="card">
    <div class="card-header"><h2><i class="fa-solid fa-file-arrow-up"></i> Importar Questões via CSV</h2></div>

    <div class="alert alert-info">
        <i class="fa-solid fa-table-list"></i> <strong>Formato esperado do CSV</strong> — separador ponto-e-vírgula <code>;</code> (ou vírgula <code>,</code>)<br/>
        Primeira linha deve ser o cabeçalho (será ignorada). As colunas devem estar nesta ordem:
    </div>

    <div class="table-wrap" style="margin-bottom:20px">
        <table>
            <thead><tr><th>Col.</th><th>Campo</th><th>Obrigatório</th><th>Exemplo</th></tr></thead>
            <tbody>
                <tr><td>1</td><td>Pergunta</td><td><span class="badge badge-red">Sim</span></td><td>Qual EPI é obrigatório ao manusear amostras?</td></tr>
                <tr><td>2</td><td>Categoria</td><td><span class="badge badge-gray">Não</span></td><td>Biossegurança</td></tr>
                <tr><td>3</td><td>Opção A</td><td><span class="badge badge-red">Sim</span></td><td>Máscara N95</td></tr>
                <tr><td>4</td><td>Opção B</td><td><span class="badge badge-red">Sim</span></td><td>Luvas e avental</td></tr>
                <tr><td>5</td><td>Opção C</td><td><span class="badge badge-gray">Não</span></td><td>Óculos de proteção</td></tr>
                <tr><td>6</td><td>Opção D</td><td><span class="badge badge-gray">Não</span></td><td>Capote estéril</td></tr>
                <tr><td>7</td><td>Resposta Correta</td><td><span class="badge badge-red">Sim</span></td><td>B ou 1 (A=0, B=1, C=2, D=3)</td></tr>
                <tr><td>8</td><td>Explicação</td><td><span class="badge badge-gray">Não</span></td><td>Luvas e avental são os EPIs mínimos…</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Download Template -->
    <div style="margin-bottom:20px">
        <a href="csv-template.php?quiz=<?= $quizId ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-download"></i> Baixar Modelo CSV</a>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Arquivo CSV *</label>
            <input class="form-control" type="file" name="csv_file" accept=".csv,.txt" required/>
            <div class="form-hint">Arquivo .csv com separador ponto-e-vírgula ou vírgula. Máx. 2 MB.</div>
        </div>
        <div class="flex gap-8">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import"></i> Importar Questões</button>
            <a href="quiz-edit.php?id=<?= $quizId ?>" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>

</div>
<?php adminFoot(); ?>
