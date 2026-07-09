<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limit = (int)($_POST['free_quiz_limit'] ?? 12);
    if ($limit < 1) {
        $error = 'O limite mínimo de quizzes no plano Free é 1.';
    } else {
        $now = "datetime('now','localtime')";
        $updates = [
            'free_quiz_limit' => (string)$limit,
            'app_name'        => trim($_POST['app_name']      ?? 'PageQuiz'),
            'support_email'   => trim($_POST['support_email'] ?? ''),
        ];
        foreach ($updates as $key => $val) {
            dbExec("UPDATE system_settings SET value=?, updated_at=datetime('now','localtime') WHERE key=?", [$val, $key]);
        }
        logAudit('update_settings', 0, json_encode($updates));
        $msg = 'Configurações salvas com sucesso.';
    }
}

$settings = [];
foreach (dbRows("SELECT * FROM system_settings ORDER BY key") as $row) {
    $settings[$row['key']] = $row;
}

superadminHead('Configurações', 'settings.php');
?>
<div class="sa-wrap" style="max-width:680px">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-sliders" style="color:var(--yellow)"></i> Configurações Globais</h1>
            <div class="sub">Parâmetros que afetam todas as empresas</div>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:16px">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert" style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <form method="POST">
            <div style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--gray-100)">
                <h3 style="font-size:15px;color:var(--prussian);margin:0 0 16px">
                    <i class="fa-solid fa-gauge" style="color:var(--pacific)"></i> Planos
                </h3>
                <div style="margin-bottom:16px">
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">
                        Limite de quizzes no plano Free
                    </label>
                    <input type="number" name="free_quiz_limit" min="1" max="9999" required
                           value="<?= htmlspecialchars($settings['free_quiz_limit']['value'] ?? '12') ?>"
                           style="width:120px;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                    <div style="font-size:12px;color:var(--gray-400);margin-top:6px">
                        Empresas Free poderão criar até este número de quizzes ativos.
                        Empresas que já tiverem mais quizzes não perdem os existentes — apenas ficam bloqueadas de criar novos.
                        <?php if (!empty($settings['free_quiz_limit']['updated_at'])): ?>
                        <br>Última alteração: <?= $settings['free_quiz_limit']['updated_at'] ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--gray-100)">
                <h3 style="font-size:15px;color:var(--prussian);margin:0 0 16px">
                    <i class="fa-solid fa-gear" style="color:var(--pacific)"></i> Plataforma
                </h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Nome da plataforma</label>
                        <input type="text" name="app_name" maxlength="60"
                               value="<?= htmlspecialchars($settings['app_name']['value'] ?? 'PageQuiz') ?>"
                               style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">E-mail de suporte</label>
                        <input type="email" name="support_email" maxlength="120"
                               value="<?= htmlspecialchars($settings['support_email']['value'] ?? '') ?>"
                               style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                        <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Exibido para empresas Free na página de upgrade.</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
                <i class="fa-solid fa-floppy-disk"></i> Salvar configurações
            </button>
        </form>
    </div>
</div>
<?php superadminFoot(); ?>
