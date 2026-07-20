<?php
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/_layout.php';

userSessionStart();
resolveTenant(); // garante que o tenant fica na sessão (via subdomínio ou ?c=slug)
if (isUserLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';
$redirect = $_GET['redirect'] ?? '../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (!$email || !$pass) {
        $error = 'Preencha e-mail e senha.';
    } elseif (userLogin($email, $pass)) {
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'E-mail ou senha incorretos.';
    }
}

userPageHead('Entrar');
?>
<div class="u-box">
  <div class="u-card">
    <div class="u-brand">
      <img src="../assets/logo-icon.svg" width="60" height="60" alt="PageQuiz"/>
      <div class="u-brand-name">Page<span>Quiz</span></div>
      <div class="u-brand-sub">by PageUp Sistemas</div>
    </div>

    <div class="u-title"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Portal do Colaborador</div>
    <div style="background:#f0f7fa;border:1px solid #bde3ef;border-radius:10px;padding:10px 14px;margin-bottom:18px;font-size:12.5px;color:#2c6e84;line-height:1.55">
      <i class="fa-solid fa-circle-info" style="margin-right:5px"></i>
      Este portal é para <strong>colaboradores e participantes</strong> de treinamentos.<br/>
      Se você é gestor ou administrador de empresa, <a href="../admin/login.php" style="color:var(--prussian);font-weight:700;text-decoration:underline">acesse o painel administrativo</a>.
    </div>

    <?php if ($error): ?>
    <div class="u-alert err"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="form-group">
        <label class="form-label" for="email">E-mail</label>
        <input class="form-control" id="email" type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="seu@email.com.br" required autocomplete="email"/>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Senha</label>
        <input class="form-control" id="password" type="password" name="password"
               placeholder="••••••••" required autocomplete="current-password"/>
      </div>
      <div style="text-align:right;margin:-8px 0 16px;font-size:13px">
        <a href="forgot-password.php" style="color:var(--pacific);text-decoration:none">Esqueci minha senha</a>
      </div>
      <button type="submit" class="btn-u">Entrar <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
    </form>

    <hr class="u-divider"/>
    <div class="u-links">
      Não tem conta? <a href="register.php">Criar conta grátis</a>
    </div>
  </div>
  <div class="u-footer"><a href="../index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao início</a></div>
</div>
<?php userPageFoot(); ?>
