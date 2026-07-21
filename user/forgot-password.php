<?php
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/_layout.php';

$sent  = false;
$error = '';
$token = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        $token = generateResetToken($email);
        $sent  = true;

        if ($token) {
            $resetUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                      . '://' . $_SERVER['HTTP_HOST']
                      . dirname($_SERVER['SCRIPT_NAME'])
                      . '/reset-password.php?token=' . $token;

            $html = mailTemplate(
                'Redefinição de senha',
                "<p>Olá!</p>"
                . "<p>Recebemos uma solicitação para redefinir a senha da sua conta na plataforma.</p>"
                . "<p>Clique no botão abaixo para criar uma nova senha (link válido por <strong>1 hora</strong>).</p>"
                . mailBtnHtml(htmlspecialchars($resetUrl), 'Redefinir minha senha →')
                . "<p style='font-size:12px;color:#94a3b8'>Se você não solicitou isso, ignore este e-mail. Sua senha permanece a mesma.</p>"
            );
            sendMail($email, 'Redefinição de senha · PageQuiz', $html);
        }
    }
}

userPageHead('Esqueci minha senha');
?>
<div class="u-box">
  <div class="u-card">
    <div class="u-brand">
      <img src="../assets/logo-icon.svg" width="60" height="60" alt="PageQuiz"/>
      <div class="u-brand-name">Page<span>Quiz</span></div>
    </div>

    <div class="u-title"><i class="fa-solid fa-key" aria-hidden="true"></i> Recuperar senha</div>

    <?php if ($sent): ?>
      <?php if ($token): ?>
      <div class="u-alert ok">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <div>
          <strong>Link gerado!</strong> Em produção ele é enviado por e-mail.<br/>
          <span style="font-size:12px;color:var(--gray-500)">Ambiente local — clique no link abaixo para redefinir:</span>
          <div style="margin-top:10px;word-break:break-all">
            <a href="reset-password.php?token=<?= htmlspecialchars($token) ?>" style="color:var(--pacific);font-size:13px">
              Redefinir senha agora →
            </a>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="u-alert ok">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>Se esse e-mail estiver cadastrado, as instruções foram enviadas.</span>
      </div>
      <?php endif; ?>
      <div class="u-links"><a href="login.php">← Voltar ao login</a></div>
    <?php else: ?>
      <?php if ($error): ?>
      <div class="u-alert err"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>
      <p style="font-size:13px;color:var(--gray-500);margin-bottom:20px;line-height:1.6">
        Informe o e-mail da sua conta. Enviaremos um link para criar uma nova senha.
      </p>
      <form method="post" autocomplete="on">
        <div class="form-group">
          <label class="form-label" for="email">E-mail</label>
          <input class="form-control" id="email" type="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="seu@email.com.br" required autocomplete="email"/>
        </div>
        <button type="submit" class="btn-u">
          <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar link de recuperação
        </button>
      </form>
      <hr class="u-divider"/>
      <div class="u-links"><a href="login.php">← Voltar ao login</a></div>
    <?php endif; ?>
  </div>
</div>
<?php userPageFoot(); ?>
