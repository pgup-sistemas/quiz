<?php
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/_layout.php';

userSessionStart();
if (isUserLoggedIn()) { header('Location: dashboard.php'); exit; }

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $sector  = trim($_POST['sector']   ?? '');
    $pass    = $_POST['password']      ?? '';
    $confirm = $_POST['password2']     ?? '';

    if (!$name || !$email || !$pass) {
        $error = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } elseif (strlen($pass) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($pass !== $confirm) {
        $error = 'As senhas não conferem.';
    } else {
        $result = userRegister($name, $email, $pass, $sector);
        if ($result === true) {
            $success = true;
        } else {
            $error = $result;
        }
    }
}

$sectors = dbRows("SELECT name FROM sectors ORDER BY name ASC");

userPageHead('Criar Conta');
?>
<div class="u-box" style="max-width:480px">
  <div class="u-card">
    <div class="u-brand">
      <img src="../assets/logo-icon.svg" width="60" height="60" alt="PageQuiz"/>
      <div class="u-brand-name">Page<span>Quiz</span></div>
      <div class="u-brand-sub">by PageUp Sistemas</div>
    </div>

    <div class="u-title"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Criar conta</div>

    <?php if ($success): ?>
    <div class="u-alert ok">
      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
      <span>Conta criada com sucesso! <a href="login.php" style="color:var(--pacific);font-weight:700">Entrar agora</a></span>
    </div>
    <?php else: ?>

    <?php if ($error): ?>
    <div class="u-alert err"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="form-group">
        <label class="form-label" for="name">Nome completo *</label>
        <input class="form-control" id="name" type="text" name="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               placeholder="Seu nome completo" required autocomplete="name"/>
      </div>
      <div class="form-group">
        <label class="form-label" for="email">E-mail *</label>
        <input class="form-control" id="email" type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="seu@email.com.br" required autocomplete="email"/>
      </div>
      <div class="form-group">
        <label class="form-label" for="sector">Setor</label>
        <select class="form-control" id="sector" name="sector">
          <option value="">— Selecione seu setor —</option>
          <?php foreach ($sectors as $s): ?>
          <option value="<?= htmlspecialchars($s['name']) ?>"
                  <?= ($_POST['sector'] ?? '') === $s['name'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label" for="password">Senha *</label>
          <input class="form-control" id="password" type="password" name="password"
                 placeholder="Mín. 6 caracteres" required autocomplete="new-password"/>
        </div>
        <div class="form-group">
          <label class="form-label" for="password2">Confirmar *</label>
          <input class="form-control" id="password2" type="password" name="password2"
                 placeholder="Repita a senha" required autocomplete="new-password"/>
        </div>
      </div>
      <button type="submit" class="btn-u">Criar Conta <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
    </form>

    <hr class="u-divider"/>
    <div class="u-links">Já tem conta? <a href="login.php">Entrar</a></div>
    <?php endif; ?>
  </div>
  <div class="u-footer"><a href="../index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao início</a></div>
</div>
<?php userPageFoot(); ?>
