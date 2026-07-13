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

$base = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'pagequiz');
$slug = $company['slug'] ?? '';
$isSubdomain = substr_count($_SERVER['HTTP_HOST'] ?? '', '.') >= 2;
$accessUrl   = $isSubdomain
    ? preg_replace('/^([a-z]+\.)/', $slug.'.', $base) . '/'
    : $base . '/?c=' . urlencode($slug);
$registerUrl = $isSubdomain
    ? preg_replace('/^([a-z]+\.)/', $slug.'.', $base) . '/user/register.php'
    : $base . '/user/register.php?c=' . urlencode($slug);

adminHead('Dashboard', 'index.php');
?>
<style>
/* ── KPI cards ───────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.stat-card {
    background: #fff;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    border: 1px solid var(--gray-100);
}
.stat-card.green { border-left: 4px solid var(--green); }
.stat-card.gold  { border-left: 4px solid var(--warn); }
.stat-card.blue  { border-left: 4px solid var(--blue); }
.stat-card .val  { font-family: 'Syne'; font-size: 32px; font-weight: 800; color: var(--navy); margin-bottom: 4px; }
.stat-card .lbl  { font-size: 13px; color: var(--gray-500); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

/* ── Tab nav ─────────────────────────────────────────── */
.dash-tabs {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid var(--gray-100);
    margin-bottom: 24px;
}
.dash-tab {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-500);
    cursor: pointer;
    border: none;
    background: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    border-radius: 6px 6px 0 0;
    transition: color .15s, border-color .15s, background .15s;
    display: flex;
    align-items: center;
    gap: 7px;
}
.dash-tab:hover { color: var(--navy); background: var(--gray-50); }
.dash-tab.active { color: var(--blue); border-bottom-color: var(--blue); background: #fff; }

/* ── Tab panels ──────────────────────────────────────── */
.dash-panel { display: none; }
.dash-panel.active { display: block; }

/* ── Grids inside panels ─────────────────────────────── */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* ── Access section ──────────────────────────────────── */
.access-grid {
    display: grid;
    grid-template-columns: 1fr 180px;
    gap: 20px;
    margin-bottom: 20px;
}
.url-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.url-pill {
    flex: 1;
    min-width: 0;
    background: #f3f6f9;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    padding: 9px 14px;
    font-family: monospace;
    font-size: 13px;
    color: var(--prussian);
    word-break: break-all;
    text-decoration: none;
    transition: border-color .15s, background .15s;
    display: block;
}
.url-pill:hover { border-color: var(--pacific); background: #edf5fa; }
.qr-card {
    background: #fff;
    border: 1.5px solid var(--gray-100);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-align: center;
}
.section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--gray-400);
    margin-bottom: 6px;
}
@media (max-width: 640px) {
    .access-grid { grid-template-columns: 1fr; }
}

@media (max-width: 860px) {
    .dashboard-grid    { grid-template-columns: 1fr; }
    .flex-mobile-col   { flex-direction: column; align-items: flex-start !important; gap: 16px; }
    .stats-grid        { grid-template-columns: 1fr 1fr; }
    .dash-tab span.tab-label { display: none; }
}
@media (max-width: 500px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>
<div class="admin-wrap">

<div class="flex items-center justify-between mb-24 flex-mobile-col">
    <div>
        <h1 style="font-family:var(--font-heading); font-size:26px; font-weight:800; color:var(--navy); letter-spacing:-0.5px">Dashboard</h1>
        <p class="text-muted" style="font-size:13px; margin-top:4px">Bem-vindo, <strong><?= e(adminName()) ?></strong></p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap">
        <a href="../index.php" class="btn btn-outline" target="_blank">
            <i class="fa-solid fa-rocket"></i> Acessar Plataforma
        </a>
        <a href="quiz-edit.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Novo Quiz
        </a>
    </div>
</div>

<!-- KPIs — sempre visíveis -->
<div class="stats-grid">
    <div class="stat-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="lbl">Total de Quizzes</div>
            <div style="width:36px;height:36px;background:#e0f2fe;border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--pacific);font-size:15px">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
        </div>
        <div class="val"><?= $totalQuizzes ?></div>
    </div>
    <div class="stat-card green">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="lbl">Quizzes Ativos</div>
            <div style="width:36px;height:36px;background:#dcfce7;border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--green);font-size:15px">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <div class="val"><?= $activeQuizzes ?></div>
    </div>
    <div class="stat-card blue">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="lbl">Participações</div>
            <div style="width:36px;height:36px;background:#e0f2fe;border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:15px">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="val"><?= $totalParticipants ?></div>
    </div>
    <div class="stat-card gold">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="lbl">Taxa de Aprovação</div>
            <div style="width:36px;height:36px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#d97706;font-size:15px">
                <i class="fa-solid fa-trophy"></i>
            </div>
        </div>
        <div class="val"><?= $passRate ?>%</div>
    </div>
</div>

<!-- Tab nav -->
<div class="dash-tabs" role="tablist">
    <button class="dash-tab active" onclick="switchTab('resumo')" id="tab-resumo" role="tab" aria-selected="true">
        <i class="fa-solid fa-table-columns"></i>
        <span class="tab-label">Atividade Recente</span>
    </button>
    <button class="dash-tab" onclick="switchTab('acesso')" id="tab-acesso" role="tab" aria-selected="false">
        <i class="fa-solid fa-share-nodes"></i>
        <span class="tab-label">Acesso &amp; Compartilhar</span>
    </button>
    <button class="dash-tab" onclick="switchTab('analise')" id="tab-analise" role="tab" aria-selected="false">
        <i class="fa-solid fa-chart-bar"></i>
        <span class="tab-label">Análise por Setor</span>
    </button>
</div>

<!-- ══ TAB: RESUMO ══════════════════════════════════════ -->
<div class="dash-panel active" id="panel-resumo" role="tabpanel">
    <div class="dashboard-grid">

        <!-- Quiz Stats -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h2><i class="fa-solid fa-clipboard-list"></i> Quizzes Ativos</h2>
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
                <h2><i class="fa-solid fa-users"></i> Últimas Participações</h2>
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

    </div>
</div><!-- /panel-resumo -->

<!-- ══ TAB: ACESSO ══════════════════════════════════════ -->
<div class="dash-panel" id="panel-acesso" role="tabpanel">

    <!-- Linha 1: Links + QR -->
    <div class="access-grid">

        <!-- Card: Links -->
        <div class="card" style="margin-bottom:0">
            <div class="card-header" style="border-bottom:1px solid var(--gray-100);margin-bottom:20px">
                <h2><i class="fa-solid fa-link" style="color:var(--pacific)"></i> Links para Colaboradores</h2>
                <p style="font-size:12px;color:var(--gray-400);margin:4px 0 0;font-weight:400">Compartilhe estes links com os colaboradores para que acessem a plataforma.</p>
            </div>

            <!-- Login -->
            <div style="margin-bottom:20px">
                <div class="section-label"><i class="fa-solid fa-right-to-bracket"></i> Acesso / Login</div>
                <div class="url-row">
                    <a id="access-url" href="<?= htmlspecialchars($accessUrl) ?>" target="_blank" class="url-pill">
                        <?= htmlspecialchars($accessUrl) ?>
                    </a>
                    <button onclick="copyAccessUrl('access-url','btn-copy-main')" id="btn-copy-main" class="btn btn-primary btn-sm" style="white-space:nowrap;flex-shrink:0">
                        <i class="fa-solid fa-copy"></i> Copiar
                    </button>
                    <a href="<?= htmlspecialchars($accessUrl) ?>" target="_blank" class="btn btn-outline btn-sm" style="flex-shrink:0" title="Abrir em nova aba">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
            </div>

            <!-- Divisor -->
            <hr style="border:none;border-top:1px dashed var(--gray-200);margin-bottom:20px"/>

            <!-- Cadastro -->
            <div>
                <div class="section-label"><i class="fa-solid fa-user-plus"></i> Cadastro de Novos Colaboradores</div>
                <div class="url-row">
                    <a id="register-url" href="<?= htmlspecialchars($registerUrl) ?>" target="_blank" class="url-pill">
                        <?= htmlspecialchars($registerUrl) ?>
                    </a>
                    <button onclick="copyAccessUrl('register-url','btn-copy-reg')" id="btn-copy-reg" class="btn btn-outline btn-sm" style="white-space:nowrap;flex-shrink:0">
                        <i class="fa-solid fa-copy"></i> Copiar
                    </button>
                    <a href="<?= htmlspecialchars($registerUrl) ?>" target="_blank" class="btn btn-outline btn-sm" style="flex-shrink:0" title="Abrir em nova aba">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
                <p style="font-size:11px;color:var(--gray-400);margin-top:8px">
                    <i class="fa-solid fa-circle-info"></i> Colaboradores com e-mail/senha criam conta por aqui. Para acesso por convite, use a aba <strong>Convites</strong> em Usuários.
                </p>
            </div>
        </div>

        <!-- QR Code card -->
        <div class="qr-card">
            <div class="section-label" style="margin-bottom:0"><i class="fa-solid fa-qrcode"></i> QR Code</div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&margin=6&color=023047&bgcolor=ffffff&data=<?= urlencode($accessUrl) ?>"
                 width="130" height="130"
                 style="border-radius:10px;border:1.5px solid var(--gray-100);display:block"
                 alt="QR Code de acesso"
                 onerror="this.closest('.qr-card').style.display='none'"/>
            <p style="font-size:11px;color:var(--gray-500);line-height:1.5;margin:0">
                Aponte a câmera<br/>para acessar o login
            </p>
            <a href="<?= htmlspecialchars($accessUrl) ?>" target="_blank" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;font-size:11px">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir link
            </a>
        </div>
    </div>

    <!-- Linha 2: Convites + Atalhos -->
    <div class="dashboard-grid">

        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h2><i class="fa-solid fa-envelope-open-text" style="color:var(--pacific)"></i> Convites por Token</h2>
                <a href="users.php#tab-convites" class="btn btn-outline btn-sm">Gerenciar</a>
            </div>
            <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px">
                Controle quem acessa criando links de convite individuais ou por setor — com validade configurável.
            </p>
            <a href="users.php#tab-convites" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Criar Convite
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-bolt" style="color:var(--warn)"></i> Atalhos Rápidos</h2>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <a href="results.php?passed=0" class="btn btn-outline btn-sm" style="justify-content:flex-start">
                    <i class="fa-solid fa-circle-exclamation" style="color:var(--danger)"></i> Reprovados
                </a>
                <a href="results.php?export=csv" class="btn btn-outline btn-sm" style="justify-content:flex-start">
                    <i class="fa-solid fa-file-csv" style="color:var(--blue)"></i> Exportar CSV
                </a>
                <a href="users.php#tab-importar" class="btn btn-outline btn-sm" style="justify-content:flex-start">
                    <i class="fa-solid fa-file-arrow-up" style="color:var(--green)"></i> Importar CSV
                </a>
                <a href="quizzes.php" class="btn btn-outline btn-sm" style="justify-content:flex-start">
                    <i class="fa-solid fa-list-check" style="color:var(--navy)"></i> Quizzes
                </a>
                <a href="sectors.php" class="btn btn-outline btn-sm" style="justify-content:flex-start">
                    <i class="fa-solid fa-sitemap" style="color:var(--gray-500)"></i> Setores
                </a>
                <a href="quiz-edit.php" class="btn btn-primary btn-sm" style="justify-content:flex-start">
                    <i class="fa-solid fa-plus"></i> Novo Quiz
                </a>
            </div>
        </div>

    </div>

</div><!-- /panel-acesso -->

<!-- ══ TAB: ANÁLISE ═════════════════════════════════════ -->
<div class="dash-panel" id="panel-analise" role="tabpanel">
    <div class="card">
        <div class="card-header" style="border-bottom:1px solid var(--gray-100);margin-bottom:24px">
            <h2><i class="fa-solid fa-chart-bar" style="color:var(--pacific)"></i> Performance por Setor</h2>
            <p style="font-size:12px;color:var(--gray-400);margin:4px 0 0;font-weight:400">
                Taxa de aprovação por setor com base em todas as participações registradas.
                <span style="display:inline-flex;align-items:center;gap:8px;margin-left:12px">
                    <span style="width:10px;height:10px;border-radius:50%;background:var(--green);display:inline-block"></span><span style="font-size:11px">≥ 70%</span>
                    <span style="width:10px;height:10px;border-radius:50%;background:var(--warn);display:inline-block"></span><span style="font-size:11px">50–69%</span>
                    <span style="width:10px;height:10px;border-radius:50%;background:var(--danger);display:inline-block"></span><span style="font-size:11px">< 50%</span>
                </span>
            </p>
        </div>
        <?php if (empty($sectorStats)): ?>
        <p class="text-muted" style="text-align:center;padding:40px 0;font-size:13px">Nenhum dado por setor ainda.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:18px">
            <?php foreach ($sectorStats as $ss):
                $rate = $ss['total'] > 0 ? round(($ss['passed_count'] / $ss['total']) * 100) : 0;
                $barColor = $rate >= 70 ? 'var(--green)' : ($rate >= 50 ? 'var(--warn)' : 'var(--danger)');
                $bgLight  = $rate >= 70 ? '#dcfce7'      : ($rate >= 50 ? '#fef3c7'     : '#fee2e2');
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= $barColor ?>;display:inline-block;flex-shrink:0"></span>
                        <span style="font-weight:700;font-size:13px;color:var(--navy)"><?= e($ss['sector']) ?></span>
                        <span style="font-size:11px;color:var(--gray-400)"><?= $ss['total'] ?> participação(ões)</span>
                    </div>
                    <span style="font-size:13px;font-weight:700;color:<?= $barColor ?>;background:<?= $bgLight ?>;padding:2px 10px;border-radius:20px">
                        <?= $rate ?>%
                    </span>
                </div>
                <div style="height:8px;background:var(--gray-100);border-radius:4px;overflow:hidden">
                    <div style="height:100%;width:<?= $rate ?>%;background:<?= $barColor ?>;border-radius:4px;transition:width 1.2s ease"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div><!-- /panel-analise -->

</div><!-- .admin-wrap -->
<script>
function switchTab(name) {
    document.querySelectorAll('.dash-tab').forEach(t => {
        t.classList.toggle('active', t.id === 'tab-' + name);
        t.setAttribute('aria-selected', t.id === 'tab-' + name);
    });
    document.querySelectorAll('.dash-panel').forEach(p => {
        p.classList.toggle('active', p.id === 'panel-' + name);
    });
    try { sessionStorage.setItem('dash_tab', name); } catch(e) {}
}

// Restaura aba ativa no reload
(function() {
    const saved = sessionStorage.getItem('dash_tab');
    if (saved) switchTab(saved);
})();

function copyAccessUrl(elId, btnId) {
    const text = document.getElementById(elId).textContent.trim();
    const btn  = document.getElementById(btnId);
    const orig = btn.innerHTML;
    const ok = () => {
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(ok);
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ok();
    }
}
</script>
<?php adminFoot(); ?>
