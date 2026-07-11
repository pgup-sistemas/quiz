<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid     = adminCompanyId();
$company = dbRow("SELECT * FROM companies WHERE id=?", [$cid]);

$totalQuizzes      = dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE company_id=?", [$cid])['c'];
$activeQuizzes     = dbRow("SELECT COUNT(*) AS c FROM quizzes WHERE active=1 AND company_id=?", [$cid])['c'];
$totalParticipants = dbRow("SELECT COUNT(*) AS c FROM participants p JOIN quizzes q ON q.id=p.quiz_id WHERE q.company_id=?", [$cid])['c'];
$passCount         = dbRow("SELECT COUNT(*) AS c FROM participants p JOIN quizzes q ON q.id=p.quiz_id WHERE p.passed=1 AND q.company_id=?", [$cid])['c'];
$passRate  = $totalParticipants > 0 ? round(($passCount / $totalParticipants) * 100) : 0;

$recentResults = dbRows("
    SELECT p.*, q.title AS quiz_title
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE q.company_id = ?
    ORDER BY p.completed_at DESC
    LIMIT 10
", [$cid]);

$quizStats = dbRows("
    SELECT q.id, q.title, q.sector,
           COUNT(p.id) AS total,
           SUM(p.passed) AS passed_count,
           ROUND(AVG(p.percentage),1) AS avg_pct
    FROM quizzes q
    LEFT JOIN participants p ON p.quiz_id = q.id
    WHERE q.active = 1 AND q.company_id = ?
    GROUP BY q.id
    ORDER BY total DESC
    LIMIT 8
", [$cid]);

$sectorStats = dbRows("
    SELECT p.sector,
           COUNT(*) AS total,
           SUM(p.passed) AS passed_count,
           ROUND(AVG(p.percentage), 1) AS avg_pct
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE p.sector != '' AND q.company_id = ?
    GROUP BY p.sector
    ORDER BY total DESC
", [$cid]);

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
        <a href="quiz-edit.php" class="btn btn-primary" style="padding:12px 20px; font-size:14px">
            <i class="fa-solid fa-plus"></i> Novo Quiz
        </a>
    </div>
</div>

<!-- Link de acesso para colaboradores -->
<?php
$base = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'pagequiz');
$slug = $company['slug'] ?? '';
// Em produção com subdomínio; localmente usa ?c=slug
$isSubdomain = substr_count($_SERVER['HTTP_HOST'] ?? '', '.') >= 2;
$accessUrl   = $isSubdomain
    ? preg_replace('/^([a-z]+\.)/', $slug.'.', $base) . '/'
    : $base . '/?c=' . urlencode($slug);
$registerUrl = $isSubdomain
    ? preg_replace('/^([a-z]+\.)/', $slug.'.', $base) . '/user/register.php'
    : $base . '/user/register.php?c=' . urlencode($slug);
?>
<div style="background:linear-gradient(135deg,var(--prussian) 0%,#034a6e 100%);border-radius:16px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
        <div style="color:rgba(255,255,255,.65);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px">
            <i class="fa-solid fa-link" style="color:var(--yellow)"></i> Link de acesso para colaboradores
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <code id="access-url" style="background:rgba(255,255,255,.1);color:#fff;padding:8px 14px;border-radius:8px;font-size:13px;letter-spacing:.2px;word-break:break-all"><?= htmlspecialchars($accessUrl) ?></code>
            <button onclick="copyAccessUrl('access-url','btn-copy-main')" id="btn-copy-main"
                    style="padding:8px 16px;background:var(--yellow);color:var(--prussian);border:none;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;white-space:nowrap;transition:.2s">
                <i class="fa-solid fa-copy"></i> Copiar
            </button>
        </div>
        <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="color:rgba(255,255,255,.5);font-size:11px">Cadastro:</span>
            <code id="register-url" style="background:rgba(255,255,255,.07);color:rgba(255,255,255,.8);padding:5px 12px;border-radius:6px;font-size:12px;word-break:break-all"><?= htmlspecialchars($registerUrl) ?></code>
            <button onclick="copyAccessUrl('register-url','btn-copy-reg')" id="btn-copy-reg"
                    style="padding:5px 12px;background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:6px;font-weight:600;font-size:11px;cursor:pointer;white-space:nowrap">
                <i class="fa-solid fa-copy"></i> Copiar
            </button>
        </div>
    </div>
    <div style="text-align:center;flex-shrink:0">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&margin=0&color=023047&bgcolor=ffffff&data=<?= urlencode($accessUrl) ?>"
             width="90" height="90" style="border-radius:8px;display:block" alt="QR Code de acesso"/>
        <div style="color:rgba(255,255,255,.5);font-size:10px;margin-top:6px">QR Code</div>
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
<script>
function copyAccessUrl(elId, btnId) {
    const text = document.getElementById(elId).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById(btnId);
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}
</script>
<?php adminFoot(); ?>
