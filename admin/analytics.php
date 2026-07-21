<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid = adminCompanyId();

// ── Filtro de período ─────────────────────────────────────────────────────────
$period = $_GET['period'] ?? '30';
if (!in_array($period, ['7','30','90','365','all'], true)) $period = '30';

$dateFilter = match($period) {
    '7'   => "AND p.completed_at >= date('now','localtime','-7 days')",
    '30'  => "AND p.completed_at >= date('now','localtime','-30 days')",
    '90'  => "AND p.completed_at >= date('now','localtime','-90 days')",
    '365' => "AND p.completed_at >= date('now','localtime','-1 year')",
    default => '',
};

// ── Stats gerais ──────────────────────────────────────────────────────────────
$stats = dbRow("
    SELECT
        COUNT(*)                                                AS total_starts,
        SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS total_completions,
        ROUND(AVG(CASE WHEN completed_at IS NOT NULL THEN percentage ELSE NULL END)) AS avg_pct,
        SUM(CASE WHEN passed=1 THEN 1 ELSE 0 END)             AS total_passed,
        COUNT(DISTINCT CASE WHEN email!='' THEN email ELSE NULL END) AS unique_participants
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE q.company_id = ? $dateFilter
", [$cid]);

$totalStarts      = (int)($stats['total_starts']      ?? 0);
$totalCompletions = (int)($stats['total_completions'] ?? 0);
$totalPassed      = (int)($stats['total_passed']      ?? 0);
$avgPct           = (int)($stats['avg_pct']           ?? 0);
$uniqueParticipants = (int)($stats['unique_participants'] ?? 0);
$completionRate   = $totalStarts > 0 ? round($totalCompletions / $totalStarts * 100) : 0;
$passRate         = $totalCompletions > 0 ? round($totalPassed / $totalCompletions * 100) : 0;

// ── Taxa de conclusão por quiz ────────────────────────────────────────────────
$byQuiz = dbRows("
    SELECT q.title, q.sector,
        COUNT(*) AS starts,
        SUM(CASE WHEN p.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completions,
        SUM(CASE WHEN p.passed=1 THEN 1 ELSE 0 END) AS passed,
        ROUND(AVG(CASE WHEN p.completed_at IS NOT NULL THEN p.percentage ELSE NULL END)) AS avg_pct
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE q.company_id = ? AND q.active = 1 $dateFilter
    GROUP BY q.id
    ORDER BY starts DESC
    LIMIT 20
", [$cid]);

// ── Distribuição de notas (faixas de 10%) ────────────────────────────────────
$dist = dbRows("
    SELECT
        CASE
            WHEN percentage < 10  THEN '0–9%'
            WHEN percentage < 20  THEN '10–19%'
            WHEN percentage < 30  THEN '20–29%'
            WHEN percentage < 40  THEN '30–39%'
            WHEN percentage < 50  THEN '40–49%'
            WHEN percentage < 60  THEN '50–59%'
            WHEN percentage < 70  THEN '60–69%'
            WHEN percentage < 80  THEN '70–79%'
            WHEN percentage < 90  THEN '80–89%'
            ELSE '90–100%'
        END AS faixa,
        COUNT(*) AS total
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE q.company_id = ? AND p.completed_at IS NOT NULL $dateFilter
    GROUP BY faixa
    ORDER BY MIN(percentage)
", [$cid]);

$distMax = max(1, ...array_column($dist, 'total'));

// ── Top performers (por usuários únicos identificados por email) ───────────────
$topPerf = dbRows("
    SELECT p.name,
        COUNT(*) AS quizzes_done,
        ROUND(AVG(p.percentage)) AS avg_score,
        SUM(p.passed) AS total_passed
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE q.company_id = ? AND p.completed_at IS NOT NULL AND p.name != '' $dateFilter
    GROUP BY LOWER(TRIM(p.name))
    HAVING quizzes_done >= 1
    ORDER BY avg_score DESC, quizzes_done DESC
    LIMIT 10
", [$cid]);

// ── Evolução mensal (últimos 12 meses) ────────────────────────────────────────
$monthly = dbRows("
    SELECT strftime('%Y-%m', p.completed_at) AS mes,
        COUNT(*) AS completions,
        ROUND(AVG(p.percentage)) AS avg_pct,
        SUM(p.passed) AS passed
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE q.company_id = ? AND p.completed_at IS NOT NULL
        AND p.completed_at >= date('now','localtime','-12 months')
    GROUP BY mes
    ORDER BY mes ASC
", [$cid]);

$monthMax = max(1, ...array_column($monthly ?: [[0]], 'completions'));

// ── Distribuição por setor ────────────────────────────────────────────────────
$bySector = dbRows("
    SELECT p.sector, COUNT(*) AS total,
        ROUND(AVG(p.percentage)) AS avg_score,
        SUM(p.passed) AS passed
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE q.company_id = ? AND p.completed_at IS NOT NULL AND p.sector != '' $dateFilter
    GROUP BY p.sector
    ORDER BY total DESC
    LIMIT 10
", [$cid]);

adminHead('Analytics', 'analytics.php');
?>
<style>
.an-kpi { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.an-kpi-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.an-kpi-val  { font-size:28px; font-weight:800; color:var(--prussian); line-height:1; margin-bottom:4px; font-variant-numeric:tabular-nums; }
.an-kpi-lbl  { font-size:12px; color:var(--gray-400); font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
.an-kpi-sub  { font-size:11px; color:var(--gray-300); margin-top:6px; }
.an-grid2   { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.an-card    { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.an-card h3 { font-size:13px; font-weight:700; color:var(--prussian); margin:0 0 16px; display:flex; align-items:center; gap:8px; }
.an-card h3 i { color:var(--pacific); }

/* Barra horizontal */
.bar-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; font-size:12px; }
.bar-lbl { width:90px; color:var(--gray-600); flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bar-lbl-wide { width:120px; }
.bar-track { flex:1; background:var(--gray-100); border-radius:4px; height:8px; overflow:hidden; }
.bar-fill { height:100%; border-radius:4px; background:var(--pacific); transition:width .6s ease; }
.bar-fill.green  { background:var(--green); }
.bar-fill.yellow { background:var(--yellow); }
.bar-fill.orange { background:var(--orange); }
.bar-val { width:40px; text-align:right; color:var(--gray-500); flex-shrink:0; font-variant-numeric:tabular-nums; }
.bar-val-wide { width:60px; }

/* Tabela de quizzes */
.quiz-tbl { width:100%; border-collapse:collapse; font-size:12px; }
.quiz-tbl th { text-align:left; padding:8px 10px; border-bottom:2px solid var(--gray-100); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-400); white-space:nowrap; }
.quiz-tbl td { padding:8px 10px; border-bottom:1px solid var(--gray-50); vertical-align:middle; }
.quiz-tbl tr:last-child td { border-bottom:none; }
.quiz-tbl tr:hover td { background:var(--gray-50); }
.rate-pill { display:inline-flex; align-items:center; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
.rate-pill.good  { background:#d1fae5; color:#065f46; }
.rate-pill.mid   { background:#fef3c7; color:#92400e; }
.rate-pill.bad   { background:#fee2e2; color:#991b1b; }

/* Top performers */
.perf-row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--gray-50); }
.perf-row:last-child { border-bottom:none; }
.perf-rank { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.perf-rank.g1 { background:#FFD700; color:#78350f; }
.perf-rank.g2 { background:#C0C0C0; color:#374151; }
.perf-rank.g3 { background:#cd7f32; color:#fff; }
.perf-rank.gn { background:var(--gray-100); color:var(--gray-500); }
.perf-name { font-size:13px; font-weight:600; color:var(--prussian); min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.perf-score { font-size:18px; font-weight:800; color:var(--pacific); font-variant-numeric:tabular-nums; }

/* Period selector */
.period-bar { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:20px; }
.period-btn { padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; text-decoration:none; background:rgba(255,255,255,.1); color:rgba(255,255,255,.7); border:1px solid rgba(255,255,255,.15); transition:.15s; }
.period-btn:hover { background:rgba(255,255,255,.2); color:#fff; }
.period-btn.active { background:var(--pacific); color:#fff; border-color:var(--pacific); }

/* Mês label */
.month-lbl { font-size:10px; color:var(--gray-400); margin-bottom:2px; }

@media (max-width:900px) {
    .an-kpi  { grid-template-columns:1fr 1fr; }
    .an-grid2 { grid-template-columns:1fr; }
}
@media (max-width:500px) {
    .an-kpi { grid-template-columns:1fr; }
}
</style>

<div class="admin-wrap">

<!-- Header -->
<div class="page-header" style="margin-bottom:16px">
    <div>
        <h1><i class="fa-solid fa-chart-line" style="color:var(--pacific)"></i> Analytics</h1>
        <div class="sub">Desempenho dos treinamentos da sua empresa</div>
    </div>
</div>

<!-- Filtro de período -->
<div class="period-bar">
    <?php foreach (['7'=>'7 dias','30'=>'30 dias','90'=>'90 dias','365'=>'1 ano','all'=>'Tudo'] as $v => $lbl): ?>
    <a href="?period=<?= $v ?>" class="period-btn <?= $period === $v ? 'active' : '' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
</div>

<!-- KPIs -->
<div class="an-kpi">
    <div class="an-kpi-card">
        <div class="an-kpi-val"><?= number_format($totalStarts) ?></div>
        <div class="an-kpi-lbl"><i class="fa-solid fa-play" style="color:var(--pacific)"></i> Participações</div>
        <div class="an-kpi-sub"><?= $uniqueParticipants ?> participante<?= $uniqueParticipants !== 1 ? 's' : '' ?> único<?= $uniqueParticipants !== 1 ? 's' : '' ?></div>
    </div>
    <div class="an-kpi-card">
        <div class="an-kpi-val" style="color:<?= $completionRate >= 70 ? 'var(--green)' : ($completionRate >= 50 ? 'var(--yellow)' : 'var(--orange)') ?>"><?= $completionRate ?>%</div>
        <div class="an-kpi-lbl"><i class="fa-solid fa-flag-checkered" style="color:var(--green)"></i> Taxa de Conclusão</div>
        <div class="an-kpi-sub"><?= $totalCompletions ?> de <?= $totalStarts ?> iniciados</div>
    </div>
    <div class="an-kpi-card">
        <div class="an-kpi-val" style="color:<?= $avgPct >= 70 ? 'var(--green)' : ($avgPct >= 50 ? 'var(--yellow)' : 'var(--orange)') ?>"><?= $avgPct ?>%</div>
        <div class="an-kpi-lbl"><i class="fa-solid fa-bullseye" style="color:var(--yellow)"></i> Nota Média</div>
        <div class="an-kpi-sub">entre todos os concluídos</div>
    </div>
    <div class="an-kpi-card">
        <div class="an-kpi-val" style="color:<?= $passRate >= 70 ? 'var(--green)' : ($passRate >= 50 ? 'var(--yellow)' : 'var(--orange)') ?>"><?= $passRate ?>%</div>
        <div class="an-kpi-lbl"><i class="fa-solid fa-circle-check" style="color:var(--green)"></i> Taxa de Aprovação</div>
        <div class="an-kpi-sub"><?= $totalPassed ?> aprovado<?= $totalPassed !== 1 ? 's' : '' ?></div>
    </div>
</div>

<?php if ($totalCompletions === 0): ?>
<div class="an-card" style="text-align:center; padding:60px 20px; color:var(--gray-400)">
    <i class="fa-solid fa-chart-line" style="font-size:40px; opacity:.3; display:block; margin-bottom:12px"></i>
    <div style="font-weight:600; font-size:15px">Nenhum dado para o período selecionado</div>
    <div style="font-size:13px; margin-top:4px">Quando colaboradores completarem quizzes, os dados aparecerão aqui.</div>
</div>
<?php else: ?>

<!-- Evolução mensal + Distribuição de notas -->
<div class="an-grid2">

    <!-- Evolução mensal -->
    <div class="an-card">
        <h3><i class="fa-solid fa-calendar-days"></i> Conclusões por Mês (últimos 12 meses)</h3>
        <?php if (empty($monthly)): ?>
        <div style="text-align:center;padding:32px;color:var(--gray-300);font-size:12px">Sem dados</div>
        <?php else: ?>
        <?php foreach ($monthly as $m): ?>
        <div class="month-lbl"><?= date('M/Y', strtotime($m['mes'].'-01')) ?></div>
        <div class="bar-row" style="margin-bottom:12px">
            <div class="bar-track">
                <div class="bar-fill" style="width:<?= round($m['completions']/$monthMax*100) ?>%"></div>
            </div>
            <div class="bar-val-wide" style="width:80px;font-size:11px">
                <?= $m['completions'] ?> <span style="color:var(--gray-300)">· <?= $m['avg_pct'] ?>%</span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Distribuição de notas -->
    <div class="an-card">
        <h3><i class="fa-solid fa-chart-bar"></i> Distribuição de Notas</h3>
        <?php if (empty($dist)): ?>
        <div style="text-align:center;padding:32px;color:var(--gray-300);font-size:12px">Sem dados</div>
        <?php else: ?>
        <?php foreach ($dist as $d):
            $pct = round($d['total']/$distMax*100);
            $faixa = $d['faixa'];
            $num   = (int)$d['total'];
            // Color based on faixa
            $fillClass = str_replace(['0–','1','2','3','4','5','6','7','8','9','%','–'], '', $faixa);
            $startNum  = (int)explode('–', $faixa)[0];
            $barClass  = $startNum >= 70 ? 'green' : ($startNum >= 50 ? 'yellow' : 'orange');
        ?>
        <div class="bar-row">
            <div class="bar-lbl"><?= $faixa ?></div>
            <div class="bar-track">
                <div class="bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="bar-val"><?= $num ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Tabela de quizzes + Setor -->
<div class="an-grid2">

    <!-- Por quiz -->
    <div class="an-card" style="overflow:hidden">
        <h3><i class="fa-solid fa-list-check"></i> Desempenho por Quiz</h3>
        <?php if (empty($byQuiz)): ?>
        <div style="text-align:center;padding:32px;color:var(--gray-300);font-size:12px">Sem dados</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="quiz-tbl">
            <thead>
                <tr>
                    <th>Quiz</th>
                    <th>Plays</th>
                    <th>Conclusão</th>
                    <th>Aprovação</th>
                    <th>Média</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byQuiz as $q):
                $cr = $q['starts'] > 0 ? round($q['completions']/$q['starts']*100) : 0;
                $pr = $q['completions'] > 0 ? round($q['passed']/$q['completions']*100) : 0;
                $prClass = $pr >= 70 ? 'good' : ($pr >= 50 ? 'mid' : 'bad');
            ?>
            <tr>
                <td>
                    <div style="font-weight:600;color:var(--prussian);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($q['title']) ?>"><?= htmlspecialchars($q['title']) ?></div>
                    <?php if ($q['sector']): ?><div style="font-size:10px;color:var(--gray-400)"><?= htmlspecialchars($q['sector']) ?></div><?php endif; ?>
                </td>
                <td style="font-variant-numeric:tabular-nums"><?= $q['starts'] ?></td>
                <td style="font-variant-numeric:tabular-nums"><?= $cr ?>%</td>
                <td><span class="rate-pill <?= $prClass ?>"><?= $pr ?>%</span></td>
                <td style="font-variant-numeric:tabular-nums"><?= $q['avg_pct'] !== null ? $q['avg_pct'].'%' : '–' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Por setor -->
    <div class="an-card">
        <h3><i class="fa-solid fa-sitemap"></i> Desempenho por Setor</h3>
        <?php if (empty($bySector)): ?>
        <div style="text-align:center;padding:32px;color:var(--gray-300);font-size:12px">Sem dados por setor</div>
        <?php else:
            $sectorMax = max(1, ...array_column($bySector, 'total'));
        ?>
        <?php foreach ($bySector as $s):
            $sp = $s['total'] > 0 ? round($s['passed']/$s['total']*100) : 0;
            $barSClass = $sp >= 70 ? 'green' : ($sp >= 50 ? 'yellow' : 'orange');
        ?>
        <div class="bar-row">
            <div class="bar-lbl bar-lbl-wide" title="<?= htmlspecialchars($s['sector']) ?>"><?= htmlspecialchars(mb_substr($s['sector'],0,14)) ?></div>
            <div class="bar-track">
                <div class="bar-fill <?= $barSClass ?>" style="width:<?= round($s['total']/$sectorMax*100) ?>%"></div>
            </div>
            <div class="bar-val-wide" style="width:80px;font-size:11px">
                <?= $s['total'] ?> <span style="color:var(--gray-300)">· <?= $s['avg_score'] ?>%</span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Top performers -->
<?php if (!empty($topPerf)): ?>
<div class="an-card">
    <h3><i class="fa-solid fa-trophy"></i> Top Performers</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0">
    <?php foreach ($topPerf as $i => $p):
        $rankClass = match($i) { 0 => 'g1', 1 => 'g2', 2 => 'g3', default => 'gn' };
    ?>
    <div class="perf-row">
        <div class="perf-rank <?= $rankClass ?>"><?= $i+1 ?></div>
        <div style="min-width:0;flex:1">
            <div class="perf-name"><?= htmlspecialchars($p['name']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= $p['quizzes_done'] ?> quiz<?= $p['quizzes_done'] > 1 ? 'zes' : '' ?> · <?= $p['total_passed'] ?> aprovação<?= $p['total_passed'] > 1 ? 'ões' : '' ?></div>
        </div>
        <div class="perf-score"><?= $p['avg_score'] ?>%</div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

</div><!-- /admin-wrap -->
<?php adminFoot(); ?>
