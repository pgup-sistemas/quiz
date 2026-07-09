<?php
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/_layout.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$user  = $token ? validateResetToken($token) : null;
$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $pass    = $_POST['password']  ?? '';
    $confirm = $_POST['password2'] ?? '';

    if (strlen($pass) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($pass !== $confirm) {
        $error = 'As senhas não conferem.';
    } elseif (resetPassword($token, $pass)) {
        $done = true;
    } else {
        $error = 'Link inválido ou expirado. Solicite um novo.';
    }
}

userPageHead('Redefinir Senha');
?>
<div class="u-box">
  <div class="u-card">
    <div class="u-brand">
      <img src="../assets/logo-icon.svg" width="60" height="60" alt="PageQuiz"/>
      <div class="u-brand-name">Page<span>Quiz</span></div>
    </div>

    <div class="u-title"><i class="fa-solid fa-lock" aria-hidden="true"></i> Nova senha</div>

    <?php if ($done): ?>
      <div class="u-alert ok">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>Senha alterada com sucesso!</span>
      </div>
      <a href="login.php" class="btn-u" style="display:block;text-align:center;text-decoration:none;margin-top:4px">Entrar agora →</a>
    <?php elseif (!$user): ?>
      <div class="u-alert err">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>Link inválido ou expirado. <a href="forgot-password.php" style="color:var(--pacific)">Solicitar novo link</a></span>
      </div>
    <?php else: ?>
      <?php if ($error): ?>
      <div class="u-alert err"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>
      <p style="font-size:13px;color:var(--gray-500);margin-bottom:20px">
        Redefinindo senha para <strong><?= htmlspecialchars($user['email']) ?></strong>
      </p>
      <form method="post" autocomplete="off">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>
        <div class="form-group">
          <label class="form-label" for="password">Nova senha</label>
          <input class="form-control" id="password" type="password" name="password"
                 placeholder="Mínimo 6 caracteres" required autocomplete="new-password"/>
        </div>
        <div class="form-group">
          <label class="form-label" for="password2">Confirmar nova senha</label>
          <input class="form-control" id="password2" type="password" name="password2"
                 placeholder="Repita a nova senha" required autocomplete="new-password"/>
        </div>
        <button type="submit" class="btn-u">
          <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Salvar nova senha
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php userPageFoot(); ?>
