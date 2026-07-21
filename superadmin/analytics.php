<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

// ── Stats gerais ──────────────────────────────────────────────────────────────
$totals = dbRow("
    SELECT
        COUNT(*) AS total_companies,
        SUM(CASE WHEN plan='pro' AND status='active' THEN 1 ELSE 0 END) AS pro_active,
        SUM(CASE WHEN plan='free' AND status='active' THEN 1 ELSE 0 END) AS free_active,
        SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END) AS suspended,
        SUM(CASE WHEN status='pending_payment' THEN 1 ELSE 0 END) AS pending
    FROM companies
");

$totalCo    = (int)($totals['total_companies'] ?? 0);
$proActive  = (int)($totals['pro_active']      ?? 0);
$freeActive = (int)($totals['free_active']      ?? 0);
$suspended  = (int)($totals['suspended']        ?? 0);
$pending    = (int)($totals['pending']          ?? 0);

// MRR: soma das assinaturas Pro ativas
$mrrRow = dbRow("
    SELECT ROUND(SUM(s.amount)/100.0, 2) AS mrr
    FROM subscriptions s
    JOIN companies c ON c.id = s.company_id
    WHERE s.status = 'active' AND c.plan = 'pro'
");
$mrr = (float)($mrrRow['mrr'] ?? 0);

// Ticket médio
$avgTicket = $proActive > 0 ? round($mrr / $proActive, 2) : 0;

// Taxa de conversão Free → Pro (empresas que já foram Pro alguma vez)
$conversionRate = $totalCo > 0 ? round($proActive / $totalCo * 100) : 0;

// ── Crescimento mensal de empresas (últimos 12 meses) ─────────────────────────
$monthlyCompanies = dbRows("
    SELECT strftime('%Y-%m', created_at) AS mes,
        COUNT(*) AS total,
        SUM(CASE WHEN plan='pro' THEN 1 ELSE 0 END) AS pro_count
    FROM companies
    WHERE created_at >= date('now','localtime','-12 months')
    GROUP BY mes
    ORDER BY mes ASC
");

$coMonthMax = max(1, ...array_column($monthlyCompanies ?: [[0]], 'total'));

// ── Crescimento mensal de participações (atividade) ───────────────────────────
$monthlyActivity = dbRows("
    SELECT strftime('%Y-%m', p.completed_at) AS mes,
        COUNT(*) AS completions
    FROM participants p
    WHERE p.completed_at >= date('now','localtime','-12 months')
    GROUP BY mes
    ORDER BY mes ASC
");

$actMax = max(1, ...array_column($monthlyActivity ?: [[0]], 'completions'));

// ── Top empresas por uso (participantes + quizzes) ────────────────────────────
$topCompanies = dbRows("
    SELECT c.name, c.plan, c.status,
        (SELECT COUNT(*) FROM quizzes q WHERE q.company_id=c.id AND q.active=1) AS quiz_count,
        (SELECT COUNT(*) FROM participants p JOIN quizzes q ON q.id=p.quiz_id WHERE q.company_id=c.id AND p.completed_at IS NOT NULL) AS completions,
        (SELECT COUNT(*) FROM users u WHERE u.company_id=c.id) AS user_count
    FROM companies c
    WHERE c.status IN ('active','pending_payment')
    ORDER BY completions DESC, quiz_count DESC
    LIMIT 15
");

// ── Conversões recentes (free → pro) ─────────────────────────────────────────
$recentPro = dbRows("
    SELECT c.name, c.updated_at
    FROM companies c
    WHERE c.plan = 'pro' AND c.status = 'active'
    ORDER BY c.updated_at DESC
    LIMIT 8
");

superadminHead('Analytics', 'analytics.php');
?>
<style>
.sa-kpi { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:24px; }
.sa-kpi-card { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:16px 18px; }
.sa-kpi-val  { font-size:26px; font-weight:800; color:#e2e8f0; line-height:1; margin-bottom:4px; font-variant-numeric:tabular-nums; }
.sa-kpi-lbl  { font-size:11px; color:rgba(255,255,255,.45); font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
.sa-kpi-sub  { font-size:11px; color:rgba(255,255,255,.3); margin-top:5px; }
.sa-card { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:20px; margin-bottom:20px; }
.sa-card h3  { font-size:13px; font-weight:700; color:#e2e8f0; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
.sa-card h3 i { color:var(--yellow); }
.sa-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }

/* Barra */
.bar-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; font-size:12px; }
.bar-lbl { width:80px; color:rgba(255,255,255,.5); flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bar-lbl-md { width:140px; }
.bar-track { flex:1; background:rgba(255,255,255,.1); border-radius:4px; height:8px; overflow:hidden; }
.bar-fill-sa { height:100%; border-radius:4px; background:var(--yellow); transition:width .6s ease; }
.bar-fill-sa.pacific { background:var(--pacific); }
.bar-fill-sa.green   { background:var(--green); }
.bar-val { width:40px; text-align:right; color:rgba(255,255,255,.4); flex-shrink:0; font-variant-numeric:tabular-nums; }
.bar-val-wide { width:70px; }

/* Tabela */
.sa-tbl { width:100%; border-collapse:collapse; font-size:12px; }
.sa-tbl th { text-align:left; padding:8px 10px; border-bottom:1px solid rgba(255,255,255,.1); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:rgba(255,255,255,.35); }
.sa-tbl td { padding:8px 10px; border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle; color:#e2e8f0; }
.sa-tbl tr:last-child td { border-bottom:none; }
.sa-tbl tr:hover td { background:rgba(255,255,255,.03); }

.month-lbl { font-size:10px; color:rgba(255,255,255,.35); margin-bottom:2px; }

@media (max-width:1000px) { .sa-kpi { grid-template-columns:repeat(3,1fr); } }
@media (max-width:700px) { .sa-kpi { grid-template-columns:1fr 1fr; } .sa-grid2 { grid-template-columns:1fr; } }
@media (max-width:400px) { .sa-kpi { grid-template-columns:1fr; } }
</style>

<div class="sa-wrap">
<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-chart-line" style="color:var(--yellow)"></i> Analytics</h1>
        <div class="sub">Visão geral da plataforma e métricas de receita</div>
    </div>
</div>

<!-- KPIs -->
<div class="sa-kpi">
    <div class="sa-kpi-card">
        <div class="sa-kpi-val"><?= $totalCo ?></div>
        <div class="sa-kpi-lbl"><i class="fa-solid fa-building"></i> Empresas</div>
        <div class="sa-kpi-sub"><?= $suspended ?> suspensa<?= $suspended !== 1 ? 's' : '' ?></div>
    </div>
    <div class="sa-kpi-card">
        <div class="sa-kpi-val" style="color:var(--yellow)"><?= $proActive ?></div>
        <div class="sa-kpi-lbl"><i class="fa-solid fa-star"></i> Pro Ativas</div>
        <div class="sa-kpi-sub"><?= $pending ?> pendente<?= $pending !== 1 ? 's' : '' ?></div>
    </div>
    <div class="sa-kpi-card">
        <div class="sa-kpi-val" style="color:#4ade80">R$ <?= number_format($mrr, 2, ',', '.') ?></div>
        <div class="sa-kpi-lbl"><i class="fa-solid fa-money-bill-trend-up"></i> MRR</div>
        <div class="sa-kpi-sub">receita mensal recorrente</div>
    </div>
    <div class="sa-kpi-card">
        <div class="sa-kpi-val" style="color:var(--pacific)">R$ <?= number_format($avgTicket, 2, ',', '.') ?></div>
        <div class="sa-kpi-lbl"><i class="fa-solid fa-receipt"></i> Ticket Médio</div>
        <div class="sa-kpi-sub">por empresa Pro</div>
    </div>
    <div class="sa-kpi-card">
        <div class="sa-kpi-val" style="color:<?= $conversionRate >= 20 ? '#4ade80' : 'rgba(255,255,255,.7)' ?>"><?= $conversionRate ?>%</div>
        <div class="sa-kpi-lbl"><i class="fa-solid fa-arrow-trend-up"></i> Conversão</div>
        <div class="sa-kpi-sub">Free → Pro</div>
    </div>
</div>

<!-- Gráficos mensais -->
<div class="sa-grid2">
    <!-- Novas empresas por mês -->
    <div class="sa-card">
        <h3><i class="fa-solid fa-calendar-days"></i> Novas Empresas / Mês (12 meses)</h3>
        <?php if (empty($monthlyCompanies)): ?>
        <div style="text-align:center;padding:32px;color:rgba(255,255,255,.2);font-size:12px">Sem dados</div>
        <?php else: ?>
        <?php foreach ($monthlyCompanies as $m): ?>
        <div class="month-lbl"><?= date('M/Y', strtotime($m['mes'].'-01')) ?></div>
        <div class="bar-row" style="margin-bottom:12px">
            <div class="bar-track">
                <div class="bar-fill-sa" style="width:<?= round($m['total']/$coMonthMax*100) ?>%"></div>
            </div>
            <div class="bar-val-wide">
                <?= $m['total'] ?> <span style="color:rgba(255,255,255,.25)">· <?= $m['pro_count'] ?> Pro</span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Atividade mensal (participações) -->
    <div class="sa-card">
        <h3><i class="fa-solid fa-chart-bar"></i> Participações / Mês (12 meses)</h3>
        <?php if (empty($monthlyActivity)): ?>
        <div style="text-align:center;padding:32px;color:rgba(255,255,255,.2);font-size:12px">Sem dados</div>
        <?php else: ?>
        <?php foreach ($monthlyActivity as $m): ?>
        <div class="month-lbl"><?= date('M/Y', strtotime($m['mes'].'-01')) ?></div>
        <div class="bar-row" style="margin-bottom:12px">
            <div class="bar-track">
                <div class="bar-fill-sa pacific" style="width:<?= round($m['completions']/$actMax*100) ?>%"></div>
            </div>
            <div class="bar-val"><?= $m['completions'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Top empresas + Conversões recentes -->
<div class="sa-grid2">
    <!-- Top empresas -->
    <div class="sa-card" style="overflow:hidden">
        <h3><i class="fa-solid fa-ranking-star"></i> Top Empresas por Atividade</h3>
        <?php if (empty($topCompanies)): ?>
        <div style="text-align:center;padding:32px;color:rgba(255,255,255,.2);font-size:12px">Sem dados</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="sa-tbl">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Plano</th>
                    <th>Quizzes</th>
                    <th>Usuários</th>
                    <th>Conclusões</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topCompanies as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:600;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></div>
                </td>
                <td>
                    <?php if ($c['plan'] === 'pro'): ?>
                    <span style="background:var(--yellow);color:var(--prussian);font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px">Pro</span>
                    <?php else: ?>
                    <span style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.5);font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px">Free</span>
                    <?php endif; ?>
                </td>
                <td style="font-variant-numeric:tabular-nums"><?= $c['quiz_count'] ?></td>
                <td style="font-variant-numeric:tabular-nums"><?= $c['user_count'] ?></td>
                <td style="font-variant-numeric:tabular-nums;color:var(--yellow)"><?= $c['completions'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Conversões recentes -->
    <div class="sa-card">
        <h3><i class="fa-solid fa-star"></i> Conversões Recentes → Pro</h3>
        <?php if (empty($recentPro)): ?>
        <div style="text-align:center;padding:32px;color:rgba(255,255,255,.2);font-size:12px">Nenhuma empresa Pro ainda</div>
        <?php else: ?>
        <?php foreach ($recentPro as $r): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06)">
            <div style="width:32px;height:32px;background:rgba(255,183,3,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--yellow);font-size:13px;flex-shrink:0">
                <i class="fa-solid fa-star"></i>
            </div>
            <div style="min-width:0;flex:1">
                <div style="font-size:13px;font-weight:600;color:#e2e8f0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['name']) ?></div>
                <div style="font-size:11px;color:rgba(255,255,255,.35)"><?= date('d/m/Y', strtotime($r['updated_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Distribuição plano -->
<div class="sa-card">
    <h3><i class="fa-solid fa-pie-chart"></i> Distribuição por Plano</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:20px">
        <?php
        $planData = [
            ['label'=>'Free Ativas',  'count'=>$freeActive, 'color'=>'rgba(255,255,255,.4)'],
            ['label'=>'Pro Ativas',   'count'=>$proActive,  'color'=>'var(--yellow)'],
            ['label'=>'Pro Pendente', 'count'=>$pending,    'color'=>'var(--orange)'],
            ['label'=>'Suspensas',    'count'=>$suspended,  'color'=>'var(--red)'],
        ];
        foreach ($planData as $pd):
            $pct2 = $totalCo > 0 ? round($pd['count']/$totalCo*100) : 0;
        ?>
        <div style="text-align:center">
            <div style="font-size:32px;font-weight:800;color:<?= $pd['color'] ?>;font-variant-numeric:tabular-nums"><?= $pd['count'] ?></div>
            <div style="font-size:11px;color:rgba(255,255,255,.4);margin:4px 0"><?= $pd['label'] ?></div>
            <div style="background:rgba(255,255,255,.08);border-radius:4px;height:6px;overflow:hidden;margin-top:6px">
                <div style="height:100%;background:<?= $pd['color'] ?>;width:<?= $pct2 ?>%;border-radius:4px"></div>
            </div>
            <div style="font-size:10px;color:rgba(255,255,255,.25);margin-top:4px"><?= $pct2 ?>% do total</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</div><!-- /sa-wrap -->
<?php superadminFoot(); ?>
