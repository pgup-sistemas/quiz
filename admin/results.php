<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid = adminCompanyId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)$_POST['id'];
    // IDOR: só exclui se o participante pertence a um quiz desta empresa
    if (dbRow("SELECT p.id FROM participants p JOIN quizzes q ON q.id=p.quiz_id WHERE p.id=? AND q.company_id=?", [$delId, $cid])) {
        dbExec("DELETE FROM answers WHERE participant_id = ?", [$delId]);
        dbExec("DELETE FROM participants WHERE id = ?", [$delId]);
    }
    flash("Resultado excluído com sucesso.", "success");
    
    $q = $_GET;
    $qs = http_build_query($q);
    header("Location: results.php" . ($qs ? "?$qs" : ""));
    exit;
}

$filterQuiz = isset($_GET['quiz'])   ? (int)$_GET['quiz']         : 0;
$filterName = trim($_GET['search']   ?? '');
$filterPass = $_GET['passed']        ?? '';

// Pagination
$perPage    = 25;
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset     = ($page - 1) * $perPage;

// CSV Export
if (isset($_GET['export'])) {
    $where = 'WHERE q.company_id=?';
    $params = [$cid];
    if ($filterQuiz) { $where .= ' AND p.quiz_id=?'; $params[] = $filterQuiz; }
    if ($filterName) { $where .= ' AND (p.name LIKE ? OR p.email LIKE ?)'; $params[] = "%$filterName%"; $params[] = "%$filterName%"; }
    if ($filterPass !== '') { $where .= ' AND p.passed=?'; $params[] = (int)$filterPass; }

    $rows = dbRows("
        SELECT p.*, q.title AS quiz_title
        FROM participants p
        JOIN quizzes q ON q.id = p.quiz_id
        $where
        ORDER BY p.completed_at DESC
    ", $params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="resultados_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['ID','Nome','Setor','Email','Quiz','Acertos','Total','Nota (%)','Aprovado','Tempo Médio','Data'], ';', '"', "");
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['name'], $r['sector'], $r['email'],
            $r['quiz_title'], $r['score'], $r['total_questions'],
            $r['percentage'], $r['passed'] ? 'Sim' : 'Não',
            $r['avg_time'] . 's', $r['completed_at'],
        ], ';', '"', "");
    }
    fclose($out);
    exit;
}

// Build WHERE
$where  = 'WHERE q.company_id=?';
$params = [$cid];
if ($filterQuiz) { $where .= ' AND p.quiz_id=?';  $params[] = $filterQuiz; }
if ($filterName) { $where .= ' AND (p.name LIKE ? OR p.email LIKE ?)'; $params[] = "%$filterName%"; $params[] = "%$filterName%"; }
if ($filterPass !== '') { $where .= ' AND p.passed=?'; $params[] = (int)$filterPass; }

$results = dbRows("
    SELECT p.*, q.title AS quiz_title, q.pass_percentage AS q_pass_pct
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    $where
    ORDER BY p.completed_at DESC
    LIMIT ? OFFSET ?
", array_merge($params, [$perPage, $offset]));

$quizList   = dbRows("SELECT id, title FROM quizzes WHERE company_id=? ORDER BY title ASC", [$cid]);
$totalRows  = dbRow("SELECT COUNT(*) AS c FROM participants p JOIN quizzes q ON q.id=p.quiz_id $where", $params)['c'];
$totalPages = ceil($totalRows / $perPage);

// Stats for filtered set
$statsQ = "SELECT COUNT(*) AS total, SUM(passed) AS passed_count, ROUND(AVG(percentage),1) AS avg_pct FROM participants p JOIN quizzes q ON q.id=p.quiz_id $where";
$stats  = dbRow($statsQ, $params);

adminHead('Resultados', 'results.php');
$flash = getFlash();
?>
<div class="admin-wrap">



<div class="flex items-center justify-between mb-16">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">Resultados</h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px"><?= $totalRows ?> participação(ões) encontrada(s)</p>
    </div>
    <div class="flex gap-8">
        <?php if ($filterQuiz): ?>
        <a href="live.php?id=<?= $filterQuiz ?>" class="btn btn-primary" style="background:var(--navy); border:none;"><i class="fa-solid fa-tower-broadcast"></i> Modo Ao Vivo</a>
        <?php endif; ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['export'=>1])) ?>" class="btn btn-dark"><i class="fa-solid fa-download"></i> Exportar CSV</a>
    </div>
</div>

<!-- Stats Row -->
<div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:20px">
    <div class="stat-card">
        <div class="val"><?= $stats['total'] ?? 0 ?></div>
        <div class="lbl">Participações</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= $stats['total'] > 0 ? round(($stats['passed_count']/$stats['total'])*100) : 0 ?>%</div>
        <div class="lbl">Taxa de Aprovação</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= $stats['avg_pct'] ?? 0 ?>%</div>
        <div class="lbl">Nota Média</div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-16">
    <form method="get" class="flex flex-wrap gap-8 items-center">
        <select class="form-select" name="quiz" style="width:auto;min-width:200px">
            <option value="">Todos os Quizzes</option>
            <?php foreach ($quizList as $qz): ?>
            <option value="<?= $qz['id'] ?>" <?= $filterQuiz == $qz['id'] ? 'selected' : '' ?>><?= e($qz['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="form-control" type="text" name="search" placeholder="Buscar por nome ou e-mail…"
               value="<?= e($filterName) ?>" style="width:220px"/>
        <select class="form-select" name="passed" style="width:auto">
            <option value="">Todos os Status</option>
            <option value="1" <?= $filterPass==='1' ? 'selected' : '' ?>>Aprovados</option>
            <option value="0" <?= $filterPass==='0' ? 'selected' : '' ?>>Reprovados</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
        <?php if ($filterQuiz || $filterName || $filterPass !== ''): ?>
        <a href="results.php" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Limpar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card">
    <?php if (empty($results)): ?>
    <p style="text-align:center;padding:48px;color:var(--gray-400);font-size:14px">Nenhum resultado encontrado com os filtros aplicados.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:1%">#</th>
                    <th>Participante</th>
                    <th>Quiz</th>
                    <th style="width:1%; white-space:nowrap">Setor</th>
                    <th style="width:1%">Nota</th>
                    <th style="width:1%">Acertos</th>
                    <th style="width:1%">Tempo</th>
                    <th style="width:1%">Status</th>
                    <th style="width:1%">Data</th>
                    <th style="width:1%; text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td style="color:var(--gray-300);font-size:12px; white-space:nowrap"><?= $r['id'] ?></td>
                <td>
                    <div style="font-weight:700;font-size:14px; min-width:140px"><?= e($r['name']) ?></div>
                    <?php if ($r['email']): ?>
                    <div style="font-size:11px;color:var(--gray-400)"><?= e($r['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-size:13px;max-width:180px"><?= e(mb_strimwidth($r['quiz_title'],0,40,'…')) ?></div>
                </td>
                <td style="white-space:nowrap"><span class="badge badge-blue"><?= e($r['sector']) ?></span></td>
                <td style="white-space:nowrap">
                    <?php
                    $pct = number_format($r['percentage'], 1);
                    $cls = $r['percentage'] >= $r['q_pass_pct'] ? 'green' : 'red';
                    echo "<span class='badge badge-$cls' style='font-size:13px'>{$pct}%</span>";
                    ?>
                </td>
                <td style="font-weight:600; white-space:nowrap"><?= $r['score'] ?> / <?= $r['total_questions'] ?></td>
                <td style="white-space:nowrap"><?= number_format($r['avg_time'],1) ?>s</td>
                <td style="white-space:nowrap">
                    <?php if ($r['passed']): ?>
                        <span class="badge badge-green"><i class="fa-solid fa-circle-check"></i> Aprovado</span>
                    <?php else: ?>
                        <span class="badge badge-red"><i class="fa-solid fa-xmark"></i> Reprovado</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--gray-400); white-space:nowrap">
                    <?= $r['completed_at'] ? date('d/m/y H:i', strtotime($r['completed_at'])) : '–' ?>
                </td>
                <td style="white-space:nowrap; text-align:right">
                    <div style="display:inline-flex; gap:4px; align-items:center; justify-content:flex-end">
                        <a href="participant.php?id=<?= $r['id'] ?>" style="color:var(--gray-500); padding:4px 6px; font-size:15px; text-decoration:none; transition: color 0.2s;" onmouseover="this.style.color='var(--blue)'" onmouseout="this.style.color='var(--gray-500)'" title="Ver Detalhes"><i class="fa-solid fa-eye"></i></a>
                        <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('Tem certeza que deseja excluir este resultado? Esta ação não pode ser desfeita.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" style="background:none; border:none; color:var(--gray-400); padding:4px 6px; font-size:15px; cursor:pointer; transition: color 0.2s;" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--gray-400)'" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between" style="padding:16px 20px; border-top:1px solid var(--gray-100); background:var(--gray-50)">
        <div style="font-size:12px; color:var(--gray-500)">
            Página <?= $page ?> de <?= $totalPages ?> (Total: <?= $totalRows ?> resultados)
        </div>
        <div class="flex gap-4">
            <?php 
            $queryParams = $_GET;
            unset($queryParams['page']);
            $baseUrl = 'results.php?' . http_build_query($queryParams);
            ?>

            <?php if ($page > 1): ?>
                <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="btn btn-outline btn-sm" style="padding:4px 10px; font-size:12px">Anterior</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $start + 4);
            if ($end - $start < 4) $start = max(1, $end - 4);
            
            for ($i = $start; $i <= $end; $i++): 
            ?>
                <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm" style="padding:4px 10px; font-size:12px; min-width:32px; text-align:center"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="btn btn-outline btn-sm" style="padding:4px 10px; font-size:12px">Próximo</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

</div>
<?php adminFoot(); ?>
