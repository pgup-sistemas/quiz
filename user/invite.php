<?php
require_once __DIR__ . '/../includes/user-auth.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/_layout.php';

userSessionStart();
if (isUserLoggedIn()) { header('Location: dashboard.php'); exit; }

$token  = trim($_GET['token'] ?? '');
$error  = '';
$success = false;

if (!$token) {
    http_response_code(404);
    include __DIR__ . '/../404.php';
    exit;
}

// Valida token: existe, não expirado, não usado
$invite = dbRow(
    "SELECT i.*, c.name AS company_name, c.slug, c.status AS company_status
     FROM invites i
     JOIN companies c ON c.id = i.company_id
     WHERE i.token = ?",
    [$token]
);

if (!$invite) {
    $error = 'Link de convite inválido.';
} elseif (!empty($invite['used_at'])) {
    $error = 'Este link de convite já foi utilizado.';
} elseif (strtotime($invite['expires_at']) < time()) {
    $error = 'Este link de convite expirou. Solicite um novo ao administrador.';
} elseif ($invite['company_status'] === 'suspended') {
    $error = 'Esta empresa está suspensa. Entre em contato com o suporte.';
}

// Injeta tenant na sessão para que userRegister() use o company_id correto
if (!$error) {
    $_SESSION['tenant_company_id'] = (int)$invite['company_id'];
    $_SESSION['_tenant_slug']      = $invite['slug'];
    // Não sobrescreve se já estava cacheado para a mesma empresa
    if (empty($_SESSION['tenant_company']) || (int)($_SESSION['tenant_company']['id'] ?? 0) !== (int)$invite['company_id']) {
        $_SESSION['tenant_company'] = dbRow("SELECT * FROM companies WHERE id = ?", [(int)$invite['company_id']]);
    }
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']      ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $pass    = $_POST['password']       ?? '';
    $pass2   = $_POST['password2']      ?? '';
    $sector  = trim($_POST['sector']    ?? $invite['sector'] ?? '');

    if (!$name || !$email || !$pass) {
        $error = 'Preencha todos os campos obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } elseif ($invite['email'] && $email !== strtolower($invite['email'])) {
        $error = 'Este convite foi emitido para ' . htmlspecialchars($invite['email']) . '. Use este e-mail para se cadastrar.';
    } elseif (strlen($pass) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($pass !== $pass2) {
        $error = 'As senhas não conferem.';
    } else {
        $result = userRegister($name, $email, $pass, $sector);
        if ($result === true) {
            // Marca convite como usado
            dbExec("UPDATE invites SET used_at = datetime('now','localtime') WHERE token = ?", [$token]);
            // Auto-login
            userLogin($email, $pass);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result;
        }
    }
}

$sectors = dbRows(
    "SELECT name FROM sectors WHERE company_id = ? ORDER BY name ASC",
    [(int)($invite['company_id'] ?? 0)]
);

userPageHead('Criar Conta — ' . ($invite['company_name'] ?? 'PageQuiz'));
?>
<div class="u-box" style="max-width:480px">
  <div class="u-card">
    <div class="u-brand">
      <img src="../assets/logo-icon.svg" width="60" height="60" alt="PageQuiz"/>
      <div class="u-brand-name">Page<span>Quiz</span></div>
      <?php if (!empty($invite['company_name'])): ?>
      <div class="u-brand-sub"><?= htmlspecialchars($invite['company_name']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($error && !$_POST): ?>
    <!-- Erro estrutural: token inválido/expirado/usado — não exibe formulário -->
    <div class="u-alert err">
      <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <hr class="u-divider"/>
    <div class="u-links"><a href="../index.php"><i class="fa-solid fa-arrow-left"></i> Voltar ao início</a></div>

    <?php else: ?>

    <div class="u-title"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i> Você foi convidado!</div>
    <p style="font-size:13px;color:#64748b;margin:0 0 20px;line-height:1.6">
      Crie sua conta para acessar os treinamentos de
      <strong><?= htmlspecialchars($invite['company_name'] ?? '') ?></strong>.
    </p>

    <?php if ($error): ?>
    <div class="u-alert err">
      <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>

      <div class="form-group">
        <label class="form-label" for="name">Nome completo *</label>
        <input class="form-control" id="name" type="text" name="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               placeholder="Seu nome completo" required autocomplete="name"/>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">E-mail *</label>
        <input class="form-control" id="email" type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? $invite['email'] ?? '') ?>"
               placeholder="seu@email.com.br" required autocomplete="email"
               <?= !empty($invite['email']) ? 'readonly style="background:var(--gray-50)"' : '' ?>/>
        <?php if (!empty($invite['email'])): ?>
        <div class="form-hint">E-mail vinculado ao convite — não pode ser alterado.</div>
        <?php endif; ?>
      </div>

      <?php if (!empty($sectors)): ?>
      <div class="form-group">
        <label class="form-label" for="sector">Setor</label>
        <select class="form-control" id="sector" name="sector">
          <option value="">— Selecione seu setor —</option>
          <?php foreach ($sectors as $s): ?>
          <option value="<?= htmlspecialchars($s['name']) ?>"
                  <?= ($invite['sector'] === $s['name'] || ($_POST['sector'] ?? '') === $s['name']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

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

      <button type="submit" class="btn-u">
        Criar Conta <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
      </button>
    </form>

    <hr class="u-divider"/>
    <div style="font-size:12px;color:var(--gray-400);text-align:center">
      Convite válido até <?= date('d/m/Y H:i', strtotime($invite['expires_at'])) ?>
    </div>

    <?php endif; ?>
  </div>
  <div class="u-footer"><a href="../index.php"><i class="fa-solid fa-arrow-left"></i> Voltar ao início</a></div>
</div>
<?php userPageFoot(); ?>
