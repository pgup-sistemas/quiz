<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid = adminCompanyId();

/* ── Excluir resultado ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int)$_POST['id'];
    if (dbRow("SELECT p.id FROM participants p JOIN quizzes q ON q.id=p.quiz_id WHERE p.id=? AND q.company_id=?", [$delId, $cid])) {
        dbExec("DELETE FROM answers WHERE participant_id = ?", [$delId]);
        dbExec("DELETE FROM participants WHERE id = ?", [$delId]);
    }
    flash("Resultado excluído.", "success");
    $q = $_GET; $qs = http_build_query($q);
    header("Location: results.php" . ($qs ? "?$qs" : ""));
    exit;
}

/* ── Parâmetros ────────────────────────────────────────────── */
$filterQuiz = isset($_GET['quiz'])   ? (int)$_GET['quiz']   : 0;
$filterName = trim($_GET['search']   ?? '');
$filterPass = $_GET['passed']        ?? '';

$allowedPerPage = [10, 25, 50, 100];
$perPage = in_array((int)($_GET['perpage'] ?? 0), $allowedPerPage) ? (int)$_GET['perpage'] : 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* ── WHERE compartilhado ───────────────────────────────────── */
$where  = 'WHERE q.company_id=?';
$params = [$cid];
if ($filterQuiz)    { $where .= ' AND p.quiz_id=?';                        $params[] = $filterQuiz; }
if ($filterName)    { $where .= ' AND (p.name LIKE ? OR p.email LIKE ?)';  $params[] = "%$filterName%"; $params[] = "%$filterName%"; }
if ($filterPass !== '') { $where .= ' AND p.passed=?';                     $params[] = (int)$filterPass; }

/* ── Export CSV ────────────────────────────────────────────── */
if (isset($_GET['export'])) {
    $rows = dbRows("SELECT p.*, q.title AS quiz_title FROM participants p
                    JOIN quizzes q ON q.id=p.quiz_id $where ORDER BY p.completed_at DESC", $params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="resultados_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['ID','Nome','Setor','Email','Quiz','Acertos','Total','Nota (%)','Aprovado','Tempo Médio','Data'], ';', '"', "");
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['name'], $r['sector'], $r['email'], $r['quiz_title'],
            $r['score'], $r['total_questions'], $r['percentage'],
            $r['passed'] ? 'Sim' : 'Não', $r['avg_time'].'s', $r['completed_at']
        ], ';', '"', "");
    }
    fclose($out); exit;
}

/* ── Consultas ─────────────────────────────────────────────── */
$results    = dbRows("SELECT p.*, q.title AS quiz_title, q.pass_percentage AS q_pass_pct
                      FROM participants p JOIN quizzes q ON q.id=p.quiz_id
                      $where ORDER BY p.completed_at DESC LIMIT ? OFFSET ?",
                     array_merge($params, [$perPage, $offset]));

$quizList   = dbRows("SELECT id, title FROM quizzes WHERE company_id=? ORDER BY title ASC", [$cid]);
$totalRows  = (int)(dbRow("SELECT COUNT(*) AS c FROM participants p JOIN quizzes q ON q.id=p.quiz_id $where", $params)['c'] ?? 0);
$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $perPage) : 1;
$stats      = dbRow("SELECT COUNT(*) AS total, SUM(passed) AS passed_count, ROUND(AVG(percentage),1) AS avg_pct
                     FROM participants p JOIN quizzes q ON q.id=p.quiz_id $where", $params);

// Contadores para as tabs (sem filtro de passed)
$baseWhere  = 'WHERE q.company_id=?' . ($filterQuiz ? ' AND p.quiz_id=?' : '') . ($filterName ? ' AND (p.name LIKE ? OR p.email LIKE ?)' : '');
$baseParams = array_merge([$cid], $filterQuiz ? [$filterQuiz] : [], $filterName ? ["%$filterName%","%$filterName%"] : []);
$cntAll     = (int)(dbRow("SELECT COUNT(*) AS c FROM participants p JOIN quizzes q ON q.id=p.quiz_id $baseWhere", $baseParams)['c'] ?? 0);
$cntPass    = (int)(dbRow("SELECT COUNT(*) AS c FROM participants p JOIN quizzes q ON q.id=p.quiz_id $baseWhere AND p.passed=1", $baseParams)['c'] ?? 0);
$cntFail    = $cntAll - $cntPass;

// Exibindo X–Y de Z
$from = $totalRows > 0 ? $offset + 1 : 0;
$to   = min($offset + $perPage, $totalRows);

/* ── Helper: monta URL preservando todos os filtros ativos ── */
function resultsUrl(array $extra = []): string {
    global $filterQuiz, $filterName, $filterPass, $perPage;
    $p = [];
    if ($filterQuiz)    $p['quiz']    = $filterQuiz;
    if ($filterName)    $p['search']  = $filterName;
    if ($filterPass !== '') $p['passed'] = $filterPass;
    if ($perPage !== 25)    $p['perpage'] = $perPage;
    return 'results.php?' . http_build_query(array_merge($p, $extra));
}

adminHead('Resultados', 'results.php');
?>
<style>
.stats-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px; }
.stat-card { background:#fff;padding:18px 20px;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);border:1px solid var(--gray-100); }
.stat-card.green { border-left:4px solid var(--green); }
.stat-card.gold  { border-left:4px solid var(--yellow); }
.stat-card .val  { font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:var(--prussian);margin-bottom:2px; }
.stat-card .lbl  { font-size:11px;color:var(--gray-500);font-weight:700;text-transform:uppercase;letter-spacing:.5px; }

/* Barra de filtros */
.filter-bar {
    display:flex;align-items:center;gap:8px;flex-wrap:wrap;
    padding:12px 16px;
    background:#fff;
    border:1px solid var(--gray-100);
    border-radius:12px;
    margin-bottom:4px;
}
.filter-bar .filter-group { display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1; }
.filter-bar .filter-right { display:flex;align-items:center;gap:8px;flex-shrink:0; }

/* Seletor perpage */
.perpage-select {
    display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--gray-500);
    border-left:1px solid var(--gray-200);padding-left:12px;
}
.perpage-select select {
    border:1px solid var(--gray-200);border-radius:7px;padding:5px 10px;font-size:12px;
    font-weight:700;color:var(--gray-700);background:#fff;cursor:pointer;
}
.perpage-select select:focus { outline:none;border-color:var(--pacific); }

/* Paginação */
.pagination-bar {
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
    padding:14px 20px;border-top:1px solid var(--gray-100);background:var(--gray-50);
    border-radius:0 0 14px 14px;
}
.pagination-info { font-size:12px;color:var(--gray-500);line-height:1.4; }
.pagination-info strong { color:var(--gray-700); }
.pagination-pages { display:flex;gap:4px;align-items:center; }
.page-btn {
    min-width:32px;height:30px;padding:0 10px;font-size:12px;font-weight:700;
    border-radius:7px;border:1px solid var(--gray-200);background:#fff;
    color:var(--gray-600);text-decoration:none;display:inline-flex;
    align-items:center;justify-content:center;transition:all .15s;
}
.page-btn:hover { border-color:var(--pacific);color:var(--pacific); }
.page-btn.active { background:var(--pacific);border-color:var(--pacific);color:#fff; }
.page-btn.disabled { opacity:.4;pointer-events:none; }
.page-ellipsis { color:var(--gray-400);padding:0 4px;font-size:12px; }
</style>

<div class="admin-wrap">

<!-- Header -->
<div class="flex items-center justify-between mb-16" style="flex-wrap:wrap;gap:10px">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">Resultados</h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px">
            <?= $totalRows ?> participação(ões)<?= $filterQuiz || $filterName || $filterPass !== '' ? ' — filtros ativos' : '' ?>
        </p>
    </div>
    <div class="flex gap-8" style="flex-wrap:wrap">
        <?php if ($filterQuiz): ?>
        <a href="live.php?id=<?= $filterQuiz ?>" class="btn btn-primary">
            <i class="fa-solid fa-tower-broadcast"></i> Ao Vivo
        </a>
        <?php endif; ?>
        <a href="<?= resultsUrl(['export'=>1]) ?>" class="btn btn-outline">
            <i class="fa-solid fa-download"></i> Exportar CSV
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="val"><?= number_format($stats['total'] ?? 0) ?></div>
        <div class="lbl">Participações</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= $stats['total'] > 0 ? round(($stats['passed_count'] / $stats['total']) * 100) : 0 ?>%</div>
        <div class="lbl">Taxa de Aprovação</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= $stats['avg_pct'] ?? 0 ?>%</div>
        <div class="lbl">Nota Média</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= $cntPass ?></div>
        <div class="lbl">Aprovados</div>
    </div>
    <div class="stat-card">
        <div class="val"><?= $cntFail ?></div>
        <div class="lbl">Reprovados</div>
    </div>
</div>

<!-- Tabs -->
<?php
$activeTab = $filterPass === '1' ? 'aprovados' : ($filterPass === '0' ? 'reprovados' : 'todos');
$tabBase   = array_filter(['quiz'=>$filterQuiz,'search'=>$filterName,'perpage'=>$perPage!==25?$perPage:null], fn($v)=>$v);
?>
<div class="page-tabs" role="tablist" style="margin-bottom:8px">
    <a class="page-tab <?= $activeTab==='todos' ? 'active' : '' ?>"
       href="results.php<?= $tabBase ? '?'.http_build_query($tabBase) : '' ?>" role="tab">
        <i class="fa-solid fa-list"></i> Todos
        <span class="tab-badge"><?= $cntAll ?></span>
    </a>
    <a class="page-tab <?= $activeTab==='aprovados' ? 'active' : '' ?>"
       href="results.php?<?= http_build_query(array_merge($tabBase,['passed'=>'1'])) ?>" role="tab">
        <i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Aprovados
        <span class="tab-badge"><?= $cntPass ?></span>
    </a>
    <a class="page-tab <?= $activeTab==='reprovados' ? 'active' : '' ?>"
       href="results.php?<?= http_build_query(array_merge($tabBase,['passed'=>'0'])) ?>" role="tab">
        <i class="fa-solid fa-circle-xmark" style="color:var(--red)"></i> Reprovados
        <span class="tab-badge"><?= $cntFail ?></span>
    </a>
</div>

<!-- Barra de filtros + perpage -->
<form method="get" id="filter-form">
    <?php if ($filterPass !== ''): ?>
    <input type="hidden" name="passed" value="<?= htmlspecialchars($filterPass) ?>"/>
    <?php endif; ?>
    <input type="hidden" name="perpage" value="<?= $perPage ?>"/>

    <div class="filter-bar">
        <div class="filter-group">
            <select class="form-select" name="quiz" style="width:auto;min-width:190px">
                <option value="">Todos os Quizzes</option>
                <?php foreach ($quizList as $qz): ?>
                <option value="<?= $qz['id'] ?>" <?= $filterQuiz == $qz['id'] ? 'selected' : '' ?>>
                    <?= e(mb_strimwidth($qz['title'], 0, 48, '…')) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="text" name="search"
                   placeholder="Buscar nome ou e-mail…"
                   value="<?= e($filterName) ?>" style="width:200px"/>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i> Filtrar
            </button>
            <?php if ($filterQuiz || $filterName): ?>
            <a href="results.php<?= $filterPass!=='' ? '?passed='.$filterPass : '' ?><?= $perPage!==25 ? ($filterPass!==''?'&':'?').'perpage='.$perPage : '' ?>"
               class="btn btn-outline">
                <i class="fa-solid fa-xmark"></i> Limpar
            </a>
            <?php endif; ?>
        </div>

        <!-- Seletor de itens por página -->
        <div class="perpage-select">
            <span>Itens por pág:</span>
            <select onchange="setPerPage(this.value)">
                <?php foreach ($allowedPerPage as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<!-- Tabela -->
<div class="card" style="margin-top:4px">
    <?php if (empty($results)): ?>
    <p style="text-align:center;padding:56px;color:var(--gray-400);font-size:14px">
        <i class="fa-solid fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;opacity:.4"></i>
        Nenhum resultado com os filtros aplicados.
    </p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:1%">#</th>
                    <th>Participante</th>
                    <th>Quiz</th>
                    <th style="width:1%;white-space:nowrap">Setor</th>
                    <th style="width:1%">Nota</th>
                    <th style="width:1%">Acertos</th>
                    <th style="width:1%">Tempo Méd.</th>
                    <th style="width:1%">Status</th>
                    <th style="width:1%;white-space:nowrap">Data</th>
                    <th style="width:1%;text-align:right">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td style="color:var(--gray-300);font-size:12px"><?= $r['id'] ?></td>
                <td>
                    <div style="font-weight:700;font-size:13px;white-space:nowrap"><?= e($r['name']) ?></div>
                    <?php if ($r['email']): ?>
                    <div style="font-size:11px;color:var(--gray-400)"><?= e($r['email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-size:13px;max-width:200px" title="<?= e($r['quiz_title']) ?>">
                        <?= e(mb_strimwidth($r['quiz_title'], 0, 42, '…')) ?>
                    </div>
                </td>
                <td><span class="badge badge-blue"><?= e($r['sector']) ?></span></td>
                <td style="white-space:nowrap">
                    <?php $cls = $r['percentage'] >= $r['q_pass_pct'] ? 'green' : 'red'; ?>
                    <span class="badge badge-<?= $cls ?>" style="font-size:13px;font-weight:700">
                        <?= number_format($r['percentage'], 1) ?>%
                    </span>
                </td>
                <td style="font-weight:600;white-space:nowrap;font-size:13px"><?= $r['score'] ?>/<?= $r['total_questions'] ?></td>
                <td style="white-space:nowrap;font-size:13px"><?= number_format($r['avg_time'], 1) ?>s</td>
                <td style="white-space:nowrap">
                    <?php if ($r['passed']): ?>
                    <span class="badge badge-green"><i class="fa-solid fa-circle-check"></i> Aprovado</span>
                    <?php else: ?>
                    <span class="badge badge-red"><i class="fa-solid fa-xmark"></i> Reprovado</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--gray-400);white-space:nowrap">
                    <?= $r['completed_at'] ? date('d/m/y H:i', strtotime($r['completed_at'])) : '–' ?>
                </td>
                <td style="white-space:nowrap;text-align:right">
                    <div style="display:inline-flex;gap:2px;align-items:center;justify-content:flex-end">
                        <a href="participant.php?id=<?= $r['id'] ?>"
                           class="row-action" title="Ver Detalhes">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <form method="post" style="display:inline;margin:0"
                              onsubmit="return confirm('Excluir este resultado permanentemente?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"     value="<?= $r['id'] ?>">
                            <?php $gq = $_GET; $gqs = http_build_query($gq); ?>
                            <input type="hidden" name="_redirect" value="results.php<?= $gqs ? '?'.$gqs : '' ?>">
                            <button type="submit" class="row-action row-action-danger" title="Excluir">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <div class="pagination-bar">
        <div class="pagination-info">
            <?php if ($totalRows > 0): ?>
            Exibindo <strong><?= $from ?>–<?= $to ?></strong> de <strong><?= $totalRows ?></strong>
            <?php if ($totalPages > 1): ?> &middot; Página <strong><?= $page ?></strong> de <strong><?= $totalPages ?></strong><?php endif; ?>
            <?php else: ?>
            Nenhum resultado
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination-pages">
            <!-- Anterior -->
            <?php if ($page > 1): ?>
            <a href="<?= resultsUrl(['page' => $page - 1]) ?>" class="page-btn">
                <i class="fa-solid fa-chevron-left" style="font-size:10px"></i>
            </a>
            <?php else: ?>
            <span class="page-btn disabled"><i class="fa-solid fa-chevron-left" style="font-size:10px"></i></span>
            <?php endif; ?>

            <!-- Números -->
            <?php
            $window = 2;
            $showFirst = 1;
            $showLast  = $totalPages;
            $rangeStart = max(2, $page - $window);
            $rangeEnd   = min($totalPages - 1, $page + $window);
            ?>
            <a href="<?= resultsUrl(['page'=>1]) ?>" class="page-btn <?= $page===1?'active':'' ?>">1</a>
            <?php if ($rangeStart > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
            <?php for ($i = $rangeStart; $i <= $rangeEnd; $i++): ?>
            <a href="<?= resultsUrl(['page'=>$i]) ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($rangeEnd < $totalPages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
            <?php if ($totalPages > 1): ?>
            <a href="<?= resultsUrl(['page'=>$totalPages]) ?>" class="page-btn <?= $page===$totalPages?'active':'' ?>"><?= $totalPages ?></a>
            <?php endif; ?>

            <!-- Próximo -->
            <?php if ($page < $totalPages): ?>
            <a href="<?= resultsUrl(['page' => $page + 1]) ?>" class="page-btn">
                <i class="fa-solid fa-chevron-right" style="font-size:10px"></i>
            </a>
            <?php else: ?>
            <span class="page-btn disabled"><i class="fa-solid fa-chevron-right" style="font-size:10px"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div><!-- /card -->

</div><!-- /admin-wrap -->
<script>
function setPerPage(val) {
    const form = document.getElementById('filter-form');
    form.querySelector('[name=perpage]').value = val;
    // Reseta para pág 1 ao mudar perpage
    const pageInput = form.querySelector('[name=page]');
    if (pageInput) pageInput.value = 1;
    form.submit();
}
</script>
<?php adminFoot(); ?>
