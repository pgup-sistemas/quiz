<?php
require_once __DIR__ . '/../includes/user-auth.php';

userSessionStart();
if (!isUserLoggedIn()) {
    header('Location: login.php?redirect=dashboard.php');
    exit;
}

$user = currentUser();

// Quiz history (by email or name match)
$history = dbRows("
    SELECT p.*, q.title AS quiz_title, q.pass_percentage AS pass_pct
    FROM participants p
    JOIN quizzes q ON q.id = p.quiz_id
    WHERE (p.email = ? OR (p.email = '' AND p.name = ?))
      AND p.completed_at IS NOT NULL
    ORDER BY p.completed_at DESC
    LIMIT 20
", [$user['email'], $user['name']]);

$totalDone   = count($history);
$totalPassed = count(array_filter($history, fn($h) => $h['passed']));
$avgPct      = $totalDone > 0 ? round(array_sum(array_column($history, 'percentage')) / $totalDone) : 0;

// Profile update
$profileMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name   = trim($_POST['name']   ?? '');
    $sector = trim($_POST['sector'] ?? '');
    if ($name) {
        userUpdateProfile($user['id'], $name, $sector);
        $profileMsg = 'Perfil atualizado!';
        $user = currentUser();
    }
}

// Password change
$passMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $cur  = $_POST['current_pass'] ?? '';
    $new  = $_POST['new_pass']     ?? '';
    $conf = $_POST['conf_pass']    ?? '';
    if (!$cur || !$new) {
        $passMsg = 'err:Preencha todos os campos.';
    } elseif (strlen($new) < 6) {
        $passMsg = 'err:Senha deve ter mínimo 6 caracteres.';
    } elseif ($new !== $conf) {
        $passMsg = 'err:As senhas não conferem.';
    } elseif (userChangePassword($user['id'], $cur, $new)) {
        $passMsg = 'ok:Senha alterada com sucesso!';
    } else {
        $passMsg = 'err:Senha atual incorreta.';
    }
}

$sectors = dbRows("SELECT name FROM sectors ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Meu Painel · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body{background:#eef4f7;font-family:'DM Sans',sans-serif}
.dash-nav{background:var(--prussian);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:56px;position:sticky;top:0;z-index:100}
.dash-nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.dash-nav-logo img{height:32px}
.dash-nav-brand{color:#fff;font-size:16px;font-weight:700}
.dash-nav-brand span{color:#8ECAE6}
.dash-nav-right{display:flex;align-items:center;gap:8px}
.dash-nav-right a{color:rgba(255,255,255,.7);font-size:13px;text-decoration:none;padding:6px 12px;border-radius:8px;transition:.2s}
.dash-nav-right a:hover{color:#fff;background:rgba(255,255,255,.1)}
.dash-nav-right .btn-out{border:1px solid rgba(255,255,255,.25);color:rgba(255,255,255,.8)}
.wrap{max-width:960px;margin:0 auto;padding:32px 20px}
.dash-header{margin-bottom:28px}
.dash-greeting{font-size:22px;font-weight:700;color:var(--prussian)}
.dash-greeting span{color:var(--pacific)}
.dash-sub{font-size:13px;color:var(--gray-500);margin-top:4px}
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
.stat-box{background:#fff;border-radius:14px;padding:20px;text-align:center;border:1px solid #e2edf2}
.stat-box .val{font-size:28px;font-weight:800;color:var(--prussian);font-family:'Syne',sans-serif}
.stat-box .lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-400);margin-top:4px}
.card{background:#fff;border-radius:16px;border:1px solid #e2edf2;margin-bottom:24px;overflow:hidden}
.card-hd{padding:18px 24px;border-bottom:1px solid #eef2f5;display:flex;align-items:center;justify-content:space-between}
.card-hd h2{font-size:15px;font-weight:700;color:var(--prussian);display:flex;align-items:center;gap:8px}
.card-hd h2 i{color:var(--pacific)}
.card-body{padding:20px 24px}
.history-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f4f7;gap:12px}
.history-row:last-child{border-bottom:none}
.history-title{font-size:13px;font-weight:700;color:var(--prussian);margin-bottom:2px}
.history-meta{font-size:11px;color:var(--gray-400)}
.badge-pass{background:#e6fffa;color:#00875a;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.badge-fail{background:#fff5f5;color:#c53030;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.badge-pct{font-size:15px;font-weight:800;color:var(--prussian);margin-right:8px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:0}
.form-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--gray-500);margin-bottom:6px}
.form-control{width:100%;padding:11px 14px;border:1.5px solid #dce8ef;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--prussian);outline:none;transition:.2s;background:#fff}
.form-control:focus{border-color:var(--pacific);box-shadow:0 0 0 3px rgba(33,158,188,.10)}
.btn-save{padding:11px 24px;background:var(--pacific);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:.2s}
.btn-save:hover{background:var(--prussian)}
.msg-ok{font-size:13px;color:#276749;background:#f0fff4;border:1px solid #9ae6b4;border-radius:8px;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.msg-err{font-size:13px;color:#c53030;background:#fff5f5;border:1px solid #fed7d7;border-radius:8px;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.empty-hist{text-align:center;padding:32px;color:var(--gray-400);font-size:14px}
.cert-link{color:var(--pacific);font-size:12px;text-decoration:none;font-weight:600}
.cert-link:hover{color:var(--prussian)}
@media(max-width:600px){.stats-row{grid-template-columns:1fr 1fr}.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<nav class="dash-nav">
  <a class="dash-nav-logo" href="../index.php">
    <img src="../assets/logo-white.svg" alt="PageQuiz" height="28"/>
  </a>
  <div class="dash-nav-right">
    <a href="../index.php"><i class="fa-solid fa-house" aria-hidden="true"></i> Início</a>
    <a href="logout.php" class="btn-out"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sair</a>
  </div>
</nav>

<div class="wrap">
  <div class="dash-header">
    <div class="dash-greeting">Olá, <span><?= htmlspecialchars($user['name']) ?></span>!</div>
    <div class="dash-sub">
      <i class="fa-solid fa-envelope" aria-hidden="true"></i> <?= htmlspecialchars($user['email']) ?>
      <?php if ($user['sector']): ?>
        &nbsp;·&nbsp; <i class="fa-solid fa-sitemap" aria-hidden="true"></i> <?= htmlspecialchars($user['sector']) ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="val"><?= $totalDone ?></div>
      <div class="lbl">Quizzes Feitos</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $totalPassed ?></div>
      <div class="lbl">Aprovações</div>
    </div>
    <div class="stat-box">
      <div class="val"><?= $avgPct ?>%</div>
      <div class="lbl">Média Geral</div>
    </div>
  </div>

  <!-- Histórico -->
  <div class="card">
    <div class="card-hd">
      <h2><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Histórico de Quizzes</h2>
      <a href="../index.php" style="font-size:13px;color:var(--pacific);text-decoration:none;font-weight:600">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Fazer novo quiz
      </a>
    </div>
    <div class="card-body" style="padding:0 24px">
      <?php if (empty($history)): ?>
        <div class="empty-hist">
          <i class="fa-solid fa-clipboard-list" style="font-size:36px;margin-bottom:12px;display:block;color:var(--gray-200)" aria-hidden="true"></i>
          Você ainda não fez nenhum quiz.<br/>
          <a href="../index.php" style="color:var(--pacific);font-weight:600;text-decoration:none">Ver quizzes disponíveis →</a>
        </div>
      <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div class="history-row">
          <div style="flex:1;min-width:0">
            <div class="history-title"><?= htmlspecialchars($h['quiz_title']) ?></div>
            <div class="history-meta"><?= date('d/m/Y H:i', strtotime($h['completed_at'])) ?> · <?= $h['score'] ?>/<?= $h['total_questions'] ?> acertos</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <span class="badge-pct"><?= $h['percentage'] ?>%</span>
            <?php if ($h['passed']): ?>
              <span class="badge-pass">Aprovado</span>
              <?php if ($h['verify_code']): ?>
              <a href="../verify.php?code=<?= urlencode($h['verify_code']) ?>" class="cert-link" title="Ver certificado">
                <i class="fa-solid fa-award" aria-hidden="true"></i>
              </a>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge-fail">Reprovado</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

    <!-- Editar Perfil -->
    <div class="card">
      <div class="card-hd"><h2><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Meu Perfil</h2></div>
      <div class="card-body">
        <?php if ($profileMsg): ?>
        <div class="msg-ok"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><?= htmlspecialchars($profileMsg) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="update_profile" value="1"/>
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Nome</label>
            <input class="form-control" type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required/>
          </div>
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Setor</label>
            <select class="form-control" name="sector">
              <option value="">— Selecione —</option>
              <?php foreach ($sectors as $s): ?>
              <option value="<?= htmlspecialchars($s['name']) ?>" <?= $user['sector'] === $s['name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn-save">Salvar</button>
        </form>
      </div>
    </div>

    <!-- Alterar Senha -->
    <div class="card">
      <div class="card-hd"><h2><i class="fa-solid fa-lock" aria-hidden="true"></i> Alterar Senha</h2></div>
      <div class="card-body">
        <?php if ($passMsg): ?>
          <?php [$type,$msg] = explode(':', $passMsg, 2); ?>
          <div class="msg-<?= $type ?>"><i class="fa-solid fa-<?= $type === 'ok' ? 'circle-check' : 'circle-exclamation' ?>" aria-hidden="true"></i><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="change_pass" value="1"/>
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Senha atual</label>
            <input class="form-control" type="password" name="current_pass" placeholder="••••••••" required/>
          </div>
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Nova senha</label>
            <input class="form-control" type="password" name="new_pass" placeholder="Mín. 6 caracteres" required/>
          </div>
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Confirmar nova senha</label>
            <input class="form-control" type="password" name="conf_pass" placeholder="Repita a nova senha" required/>
          </div>
          <button type="submit" class="btn-save">Alterar</button>
        </form>
      </div>
    </div>

  </div>
</div>
</body>
</html>
