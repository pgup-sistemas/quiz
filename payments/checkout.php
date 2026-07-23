<?php
session_name('pageup_admin');
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/efi.php';

requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireCsrf();

$companyId = adminCompanyId();
$company   = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);

// Já é Pro ativo
if ($company['plan'] === 'pro' && $company['status'] === 'active') {
    header('Location: ../admin/billing.php');
    exit;
}

$price     = efiProPrice();
$priceStr  = efiProPriceFormatted();
$isSandbox = efiIsSandbox();

$errors  = [];
$subId   = null;
$pixData = null;
$method  = $_GET['method'] ?? 'pix';

// POST: processar pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? 'pix';

    // Guarda simples contra duplo-clique/duplo-submit: ignora nova tentativa do
    // mesmo método se já existe uma cobrança 'pending' criada há menos de 15s.
    $recentDup = dbRow(
        "SELECT id FROM subscriptions WHERE company_id=? AND type=? AND status='pending'
         AND created_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND) ORDER BY created_at DESC LIMIT 1",
        [$companyId, $method === 'card_once' || $method === 'card_recurring' ? $method : 'pix']
    );
    if ($recentDup) {
        $errors[] = 'Já existe uma tentativa de pagamento em andamento. Aguarde alguns segundos e recarregue a página.';
    } elseif ($method === 'pix') {
        try {
            $txid    = 'PQ' . strtoupper(bin2hex(random_bytes(11)));
            $pixData = efiCreatePixCharge(
                $price,
                $txid,
                "PageQuiz Pro - {$company['name']}",
                1800
            );

            dbExec(
                "INSERT INTO subscriptions (company_id, type, status, amount, pix_txid, pix_qrcode, pix_copiaecola) VALUES (?,?,?,?,?,?,?)",
                [$companyId, 'pix', 'pending', $price, $txid, $pixData['qrcode'], $pixData['copiaecola']]
            );
            $subId = (int)dbLastId();

        } catch (Throwable $e) {
            $errors[] = 'Erro ao gerar QR Code PIX: ' . $e->getMessage();
        }

    } elseif ($method === 'card_once' || $method === 'card_recurring') {
        $token    = trim($_POST['payment_token'] ?? '');
        $custName = trim($_POST['customer_name'] ?? $company['name']);
        $custCpf  = preg_replace('/\D/', '', $_POST['customer_cpf'] ?? '');
        $custBirth = trim($_POST['customer_birth'] ?? '');
        $custPhone = preg_replace('/\D/', '', $_POST['customer_phone'] ?? '');

        if (!$token)    $errors[] = 'Token do cartão não recebido. Tente novamente.';
        if (!$custCpf)  $errors[] = 'CPF é obrigatório para pagamento com cartão.';

        if (empty($errors)) {
            try {
                $customer = [
                    'name'  => $custName,
                    'cpf'   => $custCpf,
                    'email' => $company['email'],
                    'birth' => $custBirth,
                    'phone' => $custPhone,
                ];

                if ($method === 'card_once') {
                    $result = efiCreateCardCharge($price, $token, $customer);
                    $status = $result['status'] === 'new' ? 'pending' : ($result['status'] === 'paid' ? 'active' : 'pending');
                    dbExec(
                        "INSERT INTO subscriptions (company_id, efi_charge_id, type, status, amount) VALUES (?,?,?,?,?)",
                        [$companyId, $result['charge_id'], 'card_once', $status, $price]
                    );
                    $subId = (int)dbLastId();
                    if ($result['status'] === 'paid') {
                        efiActivatePro($companyId, $subId, 'card_once');
                        header('Location: ../admin/billing.php?activated=1'); exit;
                    }
                } else {
                    $result = efiCreateCardSubscription($price, $token, $customer);
                    $nextBilling = !empty($result['next_billing'])
                        ? date('Y-m-d H:i:s', strtotime($result['next_billing']))
                        : date('Y-m-d H:i:s', strtotime('+1 month'));
                    $status = $result['status'] === 'active' ? 'active' : 'pending';
                    dbExec(
                        "INSERT INTO subscriptions (company_id, efi_subscription_id, efi_charge_id, type, status, amount, next_billing_at) VALUES (?,?,?,?,?,?,?)",
                        [$companyId, $result['subscription_id'], $result['charge_id'], 'card_recurring', $status, $price, $nextBilling]
                    );
                    $subId = (int)dbLastId();
                    if ($status === 'active') {
                        efiActivatePro($companyId, $subId, 'card_recurring');
                        header('Location: ../admin/billing.php?activated=1'); exit;
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Erro no pagamento com cartão: ' . $e->getMessage();
            }
        }
    }
}

// Recuperar PIX pendente existente (para exibir QR já gerado)
if (!$pixData && $method === 'pix') {
    $pendingSub = dbRow(
        "SELECT * FROM subscriptions WHERE company_id=? AND type='pix' AND status='pending' ORDER BY created_at DESC LIMIT 1",
        [$companyId]
    );
    if ($pendingSub) {
        $subId   = (int)$pendingSub['id'];
        $pixData = [
            'qrcode'     => $pendingSub['pix_qrcode'],
            'copiaecola' => $pendingSub['pix_copiaecola'],
            'txid'       => $pendingSub['pix_txid'],
        ];
    }
}

// EFI JS SDK URL (cartão)
$efiJsSdk = $isSandbox
    ? 'https://sandbox.efipay.com.br/v1/cdn/efi.min.js'
    : 'https://efipay.com.br/v1/cdn/efi.min.js';

$efiClientId = dbRow("SELECT value FROM system_settings WHERE `key`='efi_client_id'")['value'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Checkout Pro · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<?php if ($efiClientId): ?>
<script src="<?= htmlspecialchars($efiJsSdk) ?>" data-client-id="<?= htmlspecialchars($efiClientId) ?>"></script>
<?php endif; ?>
<style>
html,body { min-height:100vh; background:#f0f4f8; margin:0; font-family:var(--font-body,'DM Sans',sans-serif); }
.co-outer { min-height:100vh; display:flex; align-items:flex-start; justify-content:center; padding:40px 20px; }
.co-box   { background:#fff; border-radius:16px; width:100%; max-width:640px; box-shadow:0 8px 32px rgba(2,48,71,.1); overflow:hidden; margin-bottom:40px; }
.co-header { background:var(--prussian,#023047); padding:28px 32px; }
.co-header h2 { font-family:var(--font-heading,'Syne',sans-serif); font-size:22px; color:#fff; margin:0 0 4px; }
.co-header p  { color:rgba(255,255,255,.7); font-size:14px; margin:0; }
.co-price { font-size:32px; font-weight:800; color:var(--yellow,#FFB703); font-family:var(--font-heading); }
.co-price span { font-size:14px; font-weight:400; color:rgba(255,255,255,.6); }

.co-tabs { display:flex; border-bottom:2px solid var(--gray-100,#f3f4f6); }
.co-tab  { flex:1; padding:14px 10px; text-align:center; font-size:13px; font-weight:600; color:var(--gray-500); cursor:pointer; text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px; display:flex; flex-direction:column; align-items:center; gap:4px; }
.co-tab i { font-size:18px; }
.co-tab.active { color:var(--pacific,#219EBC); border-bottom-color:var(--pacific,#219EBC); }
.co-tab:hover  { color:var(--prussian,#023047); }

.co-body { padding:28px 32px; }
.fg { margin-bottom:16px; }
.fg label { display:block; font-size:13px; font-weight:600; color:var(--gray-700,#374151); margin-bottom:6px; }
.fg input { width:100%; box-sizing:border-box; padding:10px 14px; border:1.5px solid var(--gray-200,#e5e7eb); border-radius:8px; font-size:14px; font-family:inherit; transition:.2s; }
.fg input:focus { outline:none; border-color:var(--pacific); box-shadow:0 0 0 3px rgba(33,158,188,.15); }
.fg2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.btn-pay { width:100%; padding:14px; background:var(--pacific,#219EBC); color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:700; font-family:inherit; cursor:pointer; transition:.2s; margin-top:8px; display:flex; align-items:center; justify-content:center; gap:8px; }
.btn-pay:hover { background:#1a7d96; }
.btn-pay:disabled { opacity:.6; cursor:not-allowed; }

.pix-box { text-align:center; }
.pix-qr  { width:220px; height:220px; border:3px solid var(--gray-200); border-radius:12px; margin:16px auto; display:block; }
.pix-code { background:var(--gray-50,#f9fafb); border:1px solid var(--gray-200); border-radius:8px; padding:12px 14px; font-size:11px; word-break:break-all; color:var(--gray-600); margin:12px 0; text-align:left; max-height:80px; overflow:hidden; cursor:pointer; }
.pix-timer { font-size:13px; color:var(--gray-500); margin-top:8px; }
.pix-status { margin-top:16px; }
.status-waiting { color:var(--gray-500); }
.status-paid    { color:#16a34a; font-weight:700; }

.sandbox-badge { background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; display:inline-block; margin-bottom:12px; }
.alert-err { background:#fee2e2; color:#991b1b; border-radius:8px; padding:12px 14px; font-size:13px; margin-bottom:16px; }
.alert-err li { margin-bottom:4px; }
.card-fields { background:var(--gray-50,#f9fafb); border-radius:10px; padding:16px; margin-bottom:16px; border:1.5px solid var(--gray-200); }
.card-row { display:grid; grid-template-columns:2fr 1fr 1fr; gap:10px; }
</style>
</head>
<body>
<div class="co-outer">
<div class="co-box">
    <!-- Header -->
    <div class="co-header">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
            <div>
                <?php if ($isSandbox): ?><div class="sandbox-badge"><i class="fa-solid fa-flask"></i> Ambiente Sandbox (Homologação)</div><?php endif; ?>
                <h2><i class="fa-solid fa-star" style="color:var(--yellow)"></i> Assinar plano Pro</h2>
                <p>Quizzes ilimitados, certificado personalizado, logo e cores da empresa.</p>
            </div>
            <div style="text-align:right">
                <div class="co-price"><?= htmlspecialchars($priceStr) ?><span>/mês</span></div>
                <div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:4px"><?= htmlspecialchars($company['name']) ?></div>
            </div>
        </div>
    </div>

    <!-- Abas de método de pagamento -->
    <div class="co-tabs">
        <a class="co-tab <?= $method==='pix'?'active':'' ?>" href="?method=pix">
            <i class="fa-brands fa-pix"></i> PIX
        </a>
        <a class="co-tab <?= $method==='card_once'?'active':'' ?>" href="?method=card_once">
            <i class="fa-solid fa-credit-card"></i> Cartão
        </a>
        <a class="co-tab <?= $method==='card_recurring'?'active':'' ?>" href="?method=card_recurring">
            <i class="fa-solid fa-rotate"></i> Assinatura
        </a>
    </div>

    <div class="co-body">
        <?php if ($errors): ?>
        <div class="alert-err"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <!-- ── PIX ────────────────────────────────────────────── -->
        <?php if ($method === 'pix'): ?>
        <?php if ($pixData): ?>
        <!-- QR Code gerado -->
        <div class="pix-box">
            <div style="font-weight:600;color:var(--prussian);margin-bottom:4px">Pague <?= htmlspecialchars($priceStr) ?> via PIX</div>
            <div style="font-size:13px;color:var(--gray-500)">Escaneie o QR Code ou use o Copia e Cola</div>

            <?php if (!empty($pixData['qrcode'])): ?>
            <img src="<?= htmlspecialchars($pixData['qrcode']) ?>" class="pix-qr" alt="QR Code PIX"/>
            <?php endif; ?>

            <div style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:6px">Copia e Cola:</div>
            <div class="pix-code" id="pix-code" onclick="copyPixCode(this)" title="Clique para copiar">
                <?= htmlspecialchars($pixData['copiaecola']) ?>
            </div>
            <button type="button" onclick="copyPixCode(document.getElementById('pix-code'))"
                    style="background:var(--gray-100);border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:12px">
                <i class="fa-solid fa-copy"></i> Copiar código
            </button>

            <div class="pix-timer" id="pix-timer">⏱ QR Code válido por <strong id="timer-count">30:00</strong></div>

            <div class="pix-status" id="pix-status">
                <div class="status-waiting"><i class="fa-solid fa-circle-notch fa-spin"></i> Aguardando pagamento…</div>
            </div>
        </div>

        <script>
        // Timer de 30 min
        let seconds = 1800;
        const timerEl = document.getElementById('timer-count');
        const interval = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(interval);
                timerEl.textContent = 'Expirado';
                document.getElementById('pix-status').innerHTML =
                    '<div style="color:#991b1b"><i class="fa-solid fa-ban"></i> QR Code expirado. <a href="?method=pix">Gerar novo</a></div>';
                return;
            }
            const m = String(Math.floor(seconds/60)).padStart(2,'0');
            const s = String(seconds%60).padStart(2,'0');
            timerEl.textContent = m + ':' + s;
        }, 1000);

        // Polling de status a cada 4 segundos
        const subId = <?= $subId ?? 0 ?>;
        let polling;
        if (subId) {
            polling = setInterval(async () => {
                try {
                    const r = await fetch(`../payments/status.php?sub=${subId}`);
                    const d = await r.json();
                    if (d.activated) {
                        clearInterval(polling);
                        clearInterval(interval);
                        document.getElementById('pix-status').innerHTML =
                            '<div class="status-paid"><i class="fa-solid fa-circle-check"></i> Pagamento confirmado! Pro ativado!</div>';
                        setTimeout(() => window.location = '../admin/billing.php?activated=1', 2000);
                    }
                } catch(e) {}
            }, 4000);
        }

        function copyPixCode(el) {
            navigator.clipboard.writeText(el.textContent.trim()).then(() => {
                const btn = document.querySelector('button');
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado!';
                setTimeout(() => btn.innerHTML = orig, 2000);
            });
        }
        </script>

        <?php else: ?>
        <!-- Botão para gerar PIX -->
        <div style="text-align:center;padding:20px 0">
            <div style="font-size:48px;color:var(--pacific);margin-bottom:12px"><i class="fa-brands fa-pix"></i></div>
            <div style="font-weight:600;color:var(--prussian);font-size:18px;margin-bottom:6px">PIX instantâneo</div>
            <div style="color:var(--gray-500);font-size:14px;margin-bottom:24px">Pague <?= htmlspecialchars($priceStr) ?> e ative o Pro imediatamente após confirmação.</div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="method" value="pix"/>
                <button type="submit" class="btn-pay">
                    <i class="fa-brands fa-pix"></i> Gerar QR Code PIX — <?= htmlspecialchars($priceStr) ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── Cartão único ────────────────────────────────────── -->
        <?php elseif ($method === 'card_once'): ?>
        <div style="font-weight:600;color:var(--prussian);margin-bottom:4px">Cartão de crédito — cobrança única</div>
        <div style="font-size:13px;color:var(--gray-500);margin-bottom:20px">Ativa o Pro por 1 mês. Para renovar, você faz um novo pagamento.</div>

        <?php if (!$efiClientId): ?>
        <div class="alert-err">Credenciais EFI Bank não configuradas. Configure em <a href="../superadmin/settings.php">Super Admin → Configurações</a>.</div>
        <?php else: ?>
        <form id="form-card" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="method" value="card_once"/>
            <input type="hidden" name="payment_token" id="payment_token"/>

            <div class="card-fields">
                <div style="font-size:12px;font-weight:700;color:var(--gray-500);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">Dados do cartão</div>
                <div class="fg">
                    <label>Número do cartão</label>
                    <input type="text" id="card_number" maxlength="19" placeholder="0000 0000 0000 0000" autocomplete="cc-number"
                           oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()"/>
                </div>
                <div class="card-row">
                    <div class="fg"><label>Nome no cartão</label><input type="text" id="card_name" placeholder="NOME SOBRENOME" style="text-transform:uppercase" autocomplete="cc-name"/></div>
                    <div class="fg"><label>Validade</label><input type="text" id="card_expiry" placeholder="MM/AA" maxlength="5" autocomplete="cc-exp"
                         oninput="this.value=this.value.replace(/\D/g,'').replace(/^(\d{2})(\d)/,'$1/$2').slice(0,5)"/></div>
                    <div class="fg"><label>CVV</label><input type="text" id="card_cvv" placeholder="000" maxlength="4" autocomplete="cc-csc"/></div>
                </div>
            </div>

            <div style="font-size:12px;font-weight:700;color:var(--gray-500);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">Dados do titular</div>
            <div class="fg2">
                <div class="fg"><label>Nome completo</label><input type="text" name="customer_name" value="<?= htmlspecialchars($company['name']) ?>"/></div>
                <div class="fg"><label>CPF</label><input type="text" name="customer_cpf" placeholder="000.000.000-00" maxlength="14"
                     oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2').slice(0,14)"/></div>
                <div class="fg"><label>Data de nascimento</label><input type="date" name="customer_birth"/></div>
                <div class="fg"><label>Telefone</label><input type="text" name="customer_phone" placeholder="(69) 99999-9999" maxlength="15"/></div>
            </div>

            <button type="submit" class="btn-pay" id="btn-card" onclick="return tokenizeAndSubmit(event)">
                <i class="fa-solid fa-lock"></i> Pagar <?= htmlspecialchars($priceStr) ?> com segurança
            </button>
            <div style="text-align:center;margin-top:8px;font-size:11px;color:var(--gray-400)">
                <i class="fa-solid fa-shield-halved"></i> Dados criptografados via EFI Bank · PageQuiz nunca armazena seus dados de cartão
            </div>
        </form>
        <?php endif; ?>

        <!-- ── Assinatura recorrente ───────────────────────────── -->
        <?php elseif ($method === 'card_recurring'): ?>
        <div style="font-weight:600;color:var(--prussian);margin-bottom:4px">Assinatura recorrente</div>
        <div style="font-size:13px;color:var(--gray-500);margin-bottom:20px">
            Cobrança automática mensal de <?= htmlspecialchars($priceStr) ?> no cartão. Cancele a qualquer momento pelo painel.
        </div>

        <?php if (!$efiClientId): ?>
        <div class="alert-err">Credenciais EFI Bank não configuradas. Configure em <a href="../superadmin/settings.php">Super Admin → Configurações</a>.</div>
        <?php else: ?>
        <form id="form-card-rec" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="method" value="card_recurring"/>
            <input type="hidden" name="payment_token" id="payment_token_rec"/>

            <div class="card-fields">
                <div style="font-size:12px;font-weight:700;color:var(--gray-500);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">Dados do cartão</div>
                <div class="fg">
                    <label>Número do cartão</label>
                    <input type="text" id="card_number_rec" maxlength="19" placeholder="0000 0000 0000 0000" autocomplete="cc-number"
                           oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()"/>
                </div>
                <div class="card-row">
                    <div class="fg"><label>Nome no cartão</label><input type="text" id="card_name_rec" placeholder="NOME SOBRENOME" style="text-transform:uppercase"/></div>
                    <div class="fg"><label>Validade</label><input type="text" id="card_expiry_rec" placeholder="MM/AA" maxlength="5"
                         oninput="this.value=this.value.replace(/\D/g,'').replace(/^(\d{2})(\d)/,'$1/$2').slice(0,5)"/></div>
                    <div class="fg"><label>CVV</label><input type="text" id="card_cvv_rec" placeholder="000" maxlength="4"/></div>
                </div>
            </div>

            <div class="fg2">
                <div class="fg"><label>Nome completo</label><input type="text" name="customer_name" value="<?= htmlspecialchars($company['name']) ?>"/></div>
                <div class="fg"><label>CPF</label><input type="text" name="customer_cpf" placeholder="000.000.000-00" maxlength="14"
                     oninput="this.value=this.value.replace(/\D/g,'').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2').slice(0,14)"/></div>
                <div class="fg"><label>Data de nascimento</label><input type="date" name="customer_birth"/></div>
                <div class="fg"><label>Telefone</label><input type="text" name="customer_phone" placeholder="(69) 99999-9999" maxlength="15"/></div>
            </div>

            <button type="submit" class="btn-pay" onclick="return tokenizeAndSubmitRec(event)">
                <i class="fa-solid fa-rotate"></i> Assinar <?= htmlspecialchars($priceStr) ?>/mês
            </button>
            <div style="text-align:center;margin-top:8px;font-size:11px;color:var(--gray-400)">
                Cobrado automaticamente todo mês · Cancele quando quiser em <strong>Admin → Cobrança</strong>
            </div>
        </form>
        <?php endif; ?>

        <?php endif; ?>

        <!-- Rodapé do checkout -->
        <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--gray-100);text-align:center;font-size:12px;color:var(--gray-400)">
            <a href="../admin/upgrade.php" style="color:var(--gray-400)">← Voltar</a>
            &nbsp;·&nbsp; Pagamento processado por
            <strong style="color:var(--gray-500)">EFI Bank</strong>
            &nbsp;·&nbsp;
            <?php if ($isSandbox): ?><span style="color:#92400e">Modo sandbox ativo</span><?php else: ?><i class="fa-solid fa-shield-halved"></i> Ambiente seguro<?php endif; ?>
        </div>
    </div><!-- co-body -->
</div><!-- co-box -->
</div>

<script>
// Tokenização EFI — cartão único
function tokenizeAndSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-card');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processando…';

    const cardData = {
        brand: detectBrand(document.getElementById('card_number').value.replace(/\s/g,'')),
        number: document.getElementById('card_number').value.replace(/\s/g,''),
        cvv: document.getElementById('card_cvv').value,
        expiration_month: document.getElementById('card_expiry').value.split('/')[0],
        expiration_year: '20' + (document.getElementById('card_expiry').value.split('/')[1] || ''),
        reuse: false,
    };

    if (typeof EfiJs === 'undefined') {
        alert('SDK EFI não carregado. Verifique sua conexão.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pagar <?= htmlspecialchars($priceStr) ?> com segurança';
        return false;
    }

    EfiJs.CreditCard
        .setCardData(cardData)
        .then(token => {
            document.getElementById('payment_token').value = token;
            document.getElementById('form-card').submit();
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-lock"></i> Pagar <?= htmlspecialchars($priceStr) ?> com segurança';
            alert('Erro ao processar cartão: ' + (err.message || err));
        });
    return false;
}

// Tokenização EFI — assinatura recorrente
function tokenizeAndSubmitRec(e) {
    e.preventDefault();
    if (typeof EfiJs === 'undefined') { alert('SDK EFI não carregado.'); return false; }
    const cardData = {
        brand: detectBrand(document.getElementById('card_number_rec').value.replace(/\s/g,'')),
        number: document.getElementById('card_number_rec').value.replace(/\s/g,''),
        cvv: document.getElementById('card_cvv_rec').value,
        expiration_month: document.getElementById('card_expiry_rec').value.split('/')[0],
        expiration_year: '20' + (document.getElementById('card_expiry_rec').value.split('/')[1] || ''),
        reuse: true, // Importante para assinatura!
    };
    EfiJs.CreditCard
        .setCardData(cardData)
        .then(token => {
            document.getElementById('payment_token_rec').value = token;
            document.getElementById('form-card-rec').submit();
        })
        .catch(err => alert('Erro: ' + (err.message || err)));
    return false;
}

function detectBrand(number) {
    if (/^4/.test(number)) return 'visa';
    if (/^5[1-5]/.test(number)) return 'mastercard';
    if (/^3[47]/.test(number)) return 'amex';
    if (/^6(?:011|5)/.test(number)) return 'discover';
    if (/^(?:2131|1800|35)/.test(number)) return 'jcb';
    if (/^(?:5018|5020|5038|6304)/.test(number)) return 'maestro';
    if (/^(606282|3841)/.test(number)) return 'hipercard';
    if (/^(384100|384140|384160|606282|637095|637568)/.test(number)) return 'hipercard';
    if (/^(4011|4312|4389|4514|4576|5041|5066|5067|509|6277|6362|6363|650|6516|6550)/.test(number)) return 'elo';
    return 'visa';
}
</script>
</body>
</html>
