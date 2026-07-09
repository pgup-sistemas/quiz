<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$totalQuizzes = dbRow("SELECT COUNT(*) AS c FROM quizzes")['c'];
$activeQuizzes = dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE active=1")['c'];
$totalParticipants = dbRow("SELECT COUNT(*) AS c FROM participants")['c'];
$passCount = dbRow("SELECT COUNT(*) AS c FROM participants WHERE passed=1")['c'];
$passRate  = $totalParticipants > 0 ? round(($passCount / $totalParticipants) * 100) : 0;

$recentResults = dbRows("
    SELECT p.*, q.title AS quiz_title
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    ORDER BY p.completed_at DESC
    LIMIT 10
");

$quizStats = dbRows("
    SELECT q.id, q.title, q.sector,
           COUNT(p.id) AS total,
           SUM(p.passed) AS passed_count,
           ROUND(AVG(p.percentage),1) AS avg_pct
    FROM quizzes q
    LEFT JOIN participants p ON p.quiz_id = q.id
    WHERE q.active = 1
    GROUP BY q.id
    ORDER BY total DESC
    LIMIT 8
");

// New: Performance by Sector
$sectorStats = dbRows("
    SELECT sector, 
           COUNT(*) AS total, 
           SUM(passed) AS passed_count,
           ROUND(AVG(percentage), 1) AS avg_pct
    FROM participants
    WHERE sector != ''
    GROUP BY sector
    ORDER BY total DESC
");

adminHead('Dashboard', 'index.php');
?>
<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}
.stat-card {
    background: #fff;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--gray-100);
}
.stat-card.green { border-left: 4px solid var(--green); }
.stat-card.gold { border-left: 4px solid var(--warn); }
.stat-card.blue { border-left: 4px solid var(--blue); }
.stat-card .val { font-family: 'Syne'; font-size: 32px; font-weight: 800; color: var(--navy); margin-bottom: 4px; }
.stat-card .lbl { font-size: 13px; color: var(--gray-500); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 860px) {
    .dashboard-grid { grid-template-columns: 1fr; }
    .flex-mobile-col { flex-direction: column; align-items: flex-start !important; gap: 16px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 500px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>
<div class="admin-wrap">



<div class="flex items-center justify-between mb-32 flex-mobile-col">
    <div>
        <h1 style="font-family:var(--font-heading); font-size:28px; font-weight:800; color:var(--navy); letter-spacing:-0.5px">Dashboard</h1>
        <p class="text-muted" style="font-size:14px; margin-top:4px">Bem-vindo, <strong><?= e(adminName()) ?></strong>! Veja o desempenho dos quizzes.</p>
    </div>
    <div style="display:flex; gap:12px; flex-wrap:wrap">
        <a href="../index.php" class="btn btn-outline" target="_blank" style="padding:12px 20px; font-size:14px; border-color:var(--blue); color:var(--blue)">
            <i class="fa-solid fa-rocket"></i> Acessar Plataforma
        </a>
        <a href="quizzes.php?action=new" class="btn btn-primary" style="padding:12px 20px; font-size:14px">
            <i class="fa-solid fa-plus"></i> Novo Quiz
        </a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="val"><?= $totalQuizzes ?></div>
        <div class="lbl">Total Quizzes</div>
    </div>
    <div class="stat-card green">
        <div class="val"><?= $activeQuizzes ?></div>
        <div class="lbl">Quizzes Ativos</div>
    </div>
    <div class="stat-card blue">
        <div class="val"><?= $totalParticipants ?></div>
        <div class="lbl">Participações</div>
    </div>
    <div class="stat-card gold">
        <div class="val"><?= $passRate ?>%</div>
        <div class="lbl">Taxa Aprovação</div>
    </div>
</div>

<div class="dashboard-grid">

<!-- Quiz Stats -->
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h2><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Quizzes Ativos</h2>
        <a href="quizzes.php" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    <?php if (empty($quizStats)): ?>
    <p class="text-muted" style="text-align:center;padding:32px 0;font-size:13px">Nenhum quiz criado ainda.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Quiz</th><th>Partic.</th><th>Aprovação</th><th>Média</th></tr></thead>
            <tbody>
            <?php foreach ($quizStats as $qs): ?>
            <tr>
                <td>
                    <a href="quiz-edit.php?id=<?= $qs['id'] ?>" style="color:var(--blue);text-decoration:none;font-weight:600;font-size:13px"><?= e(mb_strimwidth($qs['title'],0,35,'…')) ?></a>
                    <br/><span style="font-size:11px;color:var(--gray-400)"><?= e($qs['sector']) ?></span>
                </td>
                <td><?= $qs['total'] ?></td>
                <td><?php
                    $r = $qs['total'] > 0 ? round(($qs['passed_count'] / $qs['total']) * 100) : 0;
                    $cls = $r >= 70 ? 'green' : ($r >= 50 ? 'gold' : 'red');
                    echo "<span class='badge badge-$cls'>$r%</span>";
                ?></td>
                <td><?= $qs['avg_pct'] ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Participants -->
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h2><i class="fa-solid fa-users" aria-hidden="true"></i> Últimas Participações</h2>
        <a href="results.php" class="btn btn-outline btn-sm">Ver todos</a>
    </div>
    <?php if (empty($recentResults)): ?>
    <p class="text-muted" style="text-align:center;padding:32px 0;font-size:13px">Nenhuma participação registrada.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Participante</th><th>Nota</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentResults as $r): ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-size:13px"><?= e($r['name']) ?></div>
                    <div style="font-size:11px;color:var(--gray-400)"><?= e($r['sector']) ?> · <?= e(mb_strimwidth($r['quiz_title'],0,28,'…')) ?></div>
                </td>
                <td style="font-weight:700"><?= $r['percentage'] ?>%</td>
                <td>
                    <?php if ($r['passed']): ?>
                        <span class="badge badge-green">Aprovado</span>
                    <?php else: ?>
                        <span class="badge badge-red">Reprovado</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- .dashboard-grid -->

<div class="dashboard-grid" style="margin-top:24px; grid-template-columns: 1.2fr 0.8fr">

    <!-- Sector Stats -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Performance por Setor</h2>
        </div>
        <?php if (empty($sectorStats)): ?>
        <p class="text-muted" style="text-align:center;padding:32px 0;font-size:13px">Nenhum dado por setor ainda.</p>
        <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:16px">
            <?php foreach ($sectorStats as $ss): 
                $rate = $ss['total'] > 0 ? round(($ss['passed_count'] / $ss['total']) * 100) : 0;
            ?>
            <div>
                <div class="flex justify-between items-center mb-4">
                    <span style="font-weight:700; font-size:13px; color:var(--navy)"><?= e($ss['sector']) ?></span>
                    <span style="font-size:12px; color:var(--gray-500)"><?= $rate ?>% de aprovação (<?= $ss['total'] ?> part.)</span>
                </div>
                <div style="height:8px; background:var(--gray-100); border-radius:4px; overflow:hidden">
                    <div style="height:100%; width:<?= $rate ?>%; background:var(--green); border-radius:4px; transition:width 1s ease"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Links / Actions -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-bolt" aria-hidden="true"></i> Atalhos Rápidos</h2>
        </div>
        <div style="display:grid; grid-template-columns: 1fr; gap:12px">
            <a href="results.php?passed=0" class="btn btn-outline btn-block" style="justify-content:flex-start">
                <i class="fa-solid fa-circle-exclamation" style="color:var(--danger)"></i> Ver Reprovados
            </a>
            <a href="results.php?export=csv" class="btn btn-outline btn-block" style="justify-content:flex-start">
                <i class="fa-solid fa-file-csv" style="color:var(--blue)"></i> Exportar Resultados (CSV)
            </a>
            <a href="quizzes.php" class="btn btn-outline btn-block" style="justify-content:flex-start">
                <i class="fa-solid fa-list-check" style="color:var(--navy)"></i> Gerenciar Quizzes
            </a>
            <a href="sectors.php" class="btn btn-outline btn-block" style="justify-content:flex-start">
                <i class="fa-solid fa-sitemap" style="color:var(--gray-500)"></i> Editar Setores
            </a>
        </div>
    </div>

</div>

</div>
<?php adminFoot(); ?>
