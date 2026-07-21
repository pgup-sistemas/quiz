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
    $section = $_POST['section'] ?? 'general';

    if ($section === 'general') {
        $limit = (int)($_POST['free_quiz_limit'] ?? 12);
        if ($limit < 1) {
            $error = 'O limite mínimo de quizzes no plano Free é 1.';
        } else {
            $updates = [
                'free_quiz_limit' => (string)$limit,
                'app_name'        => trim($_POST['app_name']      ?? 'PageQuiz'),
                'support_email'   => trim($_POST['support_email'] ?? ''),
            ];
            foreach ($updates as $key => $val) {
                dbExec("UPDATE system_settings SET value=?, updated_at=datetime('now','localtime') WHERE key=?", [$val, $key]);
            }
            logAudit('update_settings', 0, json_encode($updates));
            $msg = 'Configurações gerais salvas.';
        }
    } elseif ($section === 'email') {
        $emailUpdates = [
            'resend_api_key' => trim($_POST['resend_api_key'] ?? ''),
            'mail_from'      => trim($_POST['mail_from']      ?? 'noreply@quiz.pageup.net.br'),
            'mail_from_name' => trim($_POST['mail_from_name'] ?? 'PageQuiz'),
        ];
        foreach ($emailUpdates as $key => $val) {
            dbExec("UPDATE system_settings SET value=?, updated_at=datetime('now','localtime') WHERE key=?", [$val, $key]);
        }
        logAudit('update_email_settings', 0, json_encode(['mail_from' => $emailUpdates['mail_from'], 'has_key' => (bool)$emailUpdates['resend_api_key']]));
        $msg = 'Configurações de e-mail salvas.';

    } elseif ($section === 'efi') {
        $priceRaw = str_replace(',', '.', trim($_POST['pro_price_monthly'] ?? '49.90'));
        $priceCents = (int)round((float)$priceRaw * 100);
        if ($priceCents < 100) $error = 'Preço mínimo: R$ 1,00.';
        if (!$error) {
            $efiUpdates = [
                'pro_price_monthly'  => (string)$priceCents,
                'efi_client_id'      => trim($_POST['efi_client_id']      ?? ''),
                'efi_client_secret'  => trim($_POST['efi_client_secret']  ?? ''),
                'efi_sandbox'        => ($_POST['efi_sandbox'] ?? '1') === '0' ? '0' : '1',
                'efi_pix_key'        => trim($_POST['efi_pix_key']        ?? ''),
                'efi_cert_path'      => trim($_POST['efi_cert_path']      ?? 'certs/efi-sandbox.p12'),
                'efi_cert_password'  => trim($_POST['efi_cert_password']  ?? ''),
            ];
            foreach ($efiUpdates as $key => $val) {
                dbExec("UPDATE system_settings SET value=?, updated_at=datetime('now','localtime') WHERE key=?", [$val, $key]);
            }
            logAudit('update_efi_settings', 0, json_encode(['sandbox' => $efiUpdates['efi_sandbox'], 'price' => $priceCents]));
            $msg = 'Configurações EFI Bank salvas.';
        }
    }
}

$settings = [];
foreach (dbRows("SELECT * FROM system_settings ORDER BY key") as $row) {
    $settings[$row['key']] = $row;
}

superadminHead('Configurações', 'settings.php');
?>
<div class="sa-wrap">
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

    <!-- Configurações gerais -->
    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:20px">
        <form method="POST">
            <input type="hidden" name="section" value="general"/>
            <div style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--gray-100)">
                <h3 style="font-size:15px;color:var(--prussian);margin:0 0 16px">
                    <i class="fa-solid fa-gauge" style="color:var(--pacific)"></i> Planos
                </h3>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">
                        Limite de quizzes no plano Free
                    </label>
                    <input type="number" name="free_quiz_limit" min="1" max="9999" required
                           value="<?= htmlspecialchars($settings['free_quiz_limit']['value'] ?? '12') ?>"
                           style="width:120px;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
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
                    </div>
                </div>
            </div>
            <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
                <i class="fa-solid fa-floppy-disk"></i> Salvar configurações gerais
            </button>
        </form>
    </div>

    <!-- Configurações de E-mail -->
    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:20px">
        <form method="POST">
            <input type="hidden" name="section" value="email"/>
            <h3 style="font-size:15px;color:var(--prussian);margin:0 0 20px">
                <i class="fa-solid fa-envelope" style="color:var(--pacific)"></i> E-mail Transacional
            </h3>
            <?php $hasResend = !empty($settings['resend_api_key']['value']); ?>
            <div style="background:<?= $hasResend ? '#f0fff4' : '#fffbeb' ?>;border:1px solid <?= $hasResend ? '#9ae6b4' : '#fbbf24' ?>;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:<?= $hasResend ? '#276749' : '#92400e' ?>">
                <i class="fa-solid fa-<?= $hasResend ? 'circle-check' : 'triangle-exclamation' ?>"></i>
                <?= $hasResend ? 'Resend configurado — e-mails serão enviados via API.' : 'API Resend não configurada — e-mails serão enviados via PHP mail() (requer SMTP no servidor).' ?>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div style="grid-column:1/-1">
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">
                        API Key Resend
                        <a href="https://resend.com/api-keys" target="_blank" style="font-weight:400;color:var(--pacific);font-size:11px;margin-left:6px">obter chave →</a>
                    </label>
                    <input type="password" name="resend_api_key"
                           value="<?= htmlspecialchars($settings['resend_api_key']['value'] ?? '') ?>"
                           placeholder="re_xxxxxxxxxxxxxxxxxxxx"
                           autocomplete="off"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:13px;font-family:monospace"/>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Deixe vazio para usar PHP mail() configurado no servidor.</div>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">E-mail remetente</label>
                    <input type="email" name="mail_from"
                           value="<?= htmlspecialchars($settings['mail_from']['value'] ?? 'noreply@quiz.pageup.net.br') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Nome do remetente</label>
                    <input type="text" name="mail_from_name"
                           value="<?= htmlspecialchars($settings['mail_from_name']['value'] ?? 'PageQuiz') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
            </div>
            <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
                <i class="fa-solid fa-floppy-disk"></i> Salvar configurações de e-mail
            </button>
        </form>
    </div>

    <!-- Configurações EFI Bank -->
    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <form method="POST">
            <input type="hidden" name="section" value="efi"/>
            <h3 style="font-size:15px;color:var(--prussian);margin:0 0 20px">
                <i class="fa-solid fa-credit-card" style="color:#219ebc"></i> EFI Bank — Pagamentos
            </h3>

            <?php
            $isSandbox = ($settings['efi_sandbox']['value'] ?? '1') === '1';
            $hasClientId = !empty($settings['efi_client_id']['value']);
            ?>
            <?php if (!$hasClientId): ?>
            <div style="background:#fffbeb;border:1px solid #fbbf24;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#92400e">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Credenciais EFI não configuradas. Pagamentos estarão desabilitados até que você preencha os campos abaixo.
            </div>
            <?php endif; ?>

            <div style="margin-bottom:16px">
                <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Ambiente</label>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin-right:20px">
                    <input type="radio" name="efi_sandbox" value="1" <?= $isSandbox?'checked':'' ?>/>
                    <span style="font-size:14px">Sandbox (homologação)</span>
                </label>
                <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="radio" name="efi_sandbox" value="0" <?= !$isSandbox?'checked':'' ?>/>
                    <span style="font-size:14px">Produção</span>
                </label>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Client ID</label>
                    <input type="text" name="efi_client_id"
                           value="<?= htmlspecialchars($settings['efi_client_id']['value'] ?? '') ?>"
                           placeholder="Client_Id_xxxxx"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:13px;font-family:monospace"/>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Client Secret</label>
                    <input type="password" name="efi_client_secret"
                           value="<?= htmlspecialchars($settings['efi_client_secret']['value'] ?? '') ?>"
                           placeholder="Client_Secret_xxxxx"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:13px;font-family:monospace"/>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Chave PIX</label>
                    <input type="text" name="efi_pix_key"
                           value="<?= htmlspecialchars($settings['efi_pix_key']['value'] ?? '') ?>"
                           placeholder="seu@email.com ou CPF/CNPJ"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Preço mensal Pro (R$)</label>
                    <input type="text" name="pro_price_monthly"
                           value="<?= number_format((int)($settings['pro_price_monthly']['value'] ?? 4990)/100, 2, ',', '.') ?>"
                           placeholder="49,90"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Exibido no checkout e cobrado via EFI.</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">
                        Caminho do certificado PIX (.p12)
                        <a href="../certs/README.md" target="_blank" style="font-weight:400;color:var(--pacific);font-size:11px;margin-left:6px">como obter?</a>
                    </label>
                    <input type="text" name="efi_cert_path"
                           value="<?= htmlspecialchars($settings['efi_cert_path']['value'] ?? 'certs/efi-sandbox.p12') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:13px;font-family:monospace"/>
                    <?php
                    $certPath = __DIR__ . '/../' . ltrim($settings['efi_cert_path']['value'] ?? '', '/');
                    ?>
                    <div style="font-size:11px;margin-top:4px;color:<?= file_exists($certPath) ? '#166534' : '#991b1b' ?>">
                        <?= file_exists($certPath) ? '<i class="fa-solid fa-circle-check"></i> Certificado encontrado' : '<i class="fa-solid fa-circle-xmark"></i> Certificado não encontrado neste caminho' ?>
                    </div>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Senha do .p12 <small style="font-weight:400">(se houver)</small></label>
                    <input type="password" name="efi_cert_password"
                           value="<?= htmlspecialchars($settings['efi_cert_password']['value'] ?? '') ?>"
                           placeholder="Deixe vazio se não tiver"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
            </div>

            <div style="background:var(--gray-50);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:var(--gray-500)">
                <i class="fa-solid fa-circle-info"></i>
                <strong>URL do Webhook:</strong>
                <code style="background:rgba(0,0,0,.06);padding:2px 6px;border-radius:4px">
                    https://seudominio.com/payments/webhook.php
                </code>
                — configure esta URL no painel EFI Bank → API → Webhooks (PIX e cobranças).
            </div>

            <button type="submit" class="btn" style="background:#023047;color:#fff;font-weight:700">
                <i class="fa-solid fa-floppy-disk"></i> Salvar credenciais EFI
            </button>
        </form>
    </div>
</div>
<?php superadminFoot(); ?>
