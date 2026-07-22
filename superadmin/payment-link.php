<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/efi.php';
require_once __DIR__ . '/layout.php';

$companyId = (int)($_GET['company_id'] ?? 0);
$company   = $companyId ? dbRow("SELECT * FROM companies WHERE id=?", [$companyId]) : null;

$msg        = '';
$error      = '';
$linkResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid    = (int)($_POST['company_id'] ?? 0);
    $cents  = (int)round((float)str_replace(',', '.', $_POST['amount'] ?? '0') * 100);
    $desc   = trim($_POST['description'] ?? 'PageQuiz Pro');
    $co     = dbRow("SELECT * FROM companies WHERE id=?", [$cid]);

    if (!$co)        $error = 'Empresa não encontrada.';
    elseif ($cents < 100) $error = 'Valor mínimo: R$ 1,00.';

    if (!$error) {
        try {
            $linkResult = efiCreatePaymentLink($cents, $desc, $cid);
            dbExec(
                "INSERT INTO subscriptions (company_id, type, status, amount, payment_link_url) VALUES (?,?,?,?,?)",
                [$cid, 'payment_link', 'pending', $cents, $linkResult['url']]
            );
            $subId = (int)dbLastId();
            logAudit('payment_link_created', $cid, json_encode(['amount'=>$cents, 'link_id'=>$linkResult['link_id']]));
            $company = $co;
            $msg     = "Link gerado para {$co['name']}!";
        } catch (Throwable $e) {
            $error = 'Erro ao gerar link: ' . $e->getMessage();
        }
    }
}

$companies  = dbRows("SELECT id, name, email, plan FROM companies ORDER BY name");
$defaultPrice = (int)(dbRow("SELECT value FROM system_settings WHERE `key`='pro_price_monthly'")['value'] ?? 4990);

superadminHead('Gerar Link de Pagamento', 'payments.php');
?>
<div class="sa-wrap">
    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-link" style="color:var(--yellow)"></i> Link de Pagamento</h1>
            <div class="sub">Gera um link EFI Bank para enviar ao cliente (aceita PIX e cartão)</div>
        </div>
        <a href="companies.php" style="color:var(--gray-400);font-size:13px">← Voltar para empresas</a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success shadow-sm" style="margin-bottom:16px">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert" style="background:rgba(239,68,68,.15);color:#fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($linkResult && !empty($linkResult['url'])): ?>
    <div class="card" style="border-radius:var(--radius);padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:20px;background:rgba(34,197,94,.10);border:2px solid rgba(34,197,94,.4)">
        <div style="font-weight:700;color:#86efac;margin-bottom:12px"><i class="fa-solid fa-link"></i> Link gerado com sucesso!</div>
        <div style="background:rgba(0,0,0,.15);color:var(--gray-700);border:1px solid rgba(34,197,94,.4);border-radius:8px;padding:12px;word-break:break-all;font-size:13px;margin-bottom:12px">
            <?= htmlspecialchars($linkResult['url']) ?>
        </div>
        <div style="display:flex;gap:10px">
            <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkResult['url']) ?>').then(()=>alert('Copiado!'))"
                    class="btn btn-sm" style="background:var(--pacific);color:#fff">
                <i class="fa-solid fa-copy"></i> Copiar link
            </button>
            <a href="<?= htmlspecialchars($linkResult['url']) ?>" target="_blank" class="btn btn-sm" style="background:var(--gray-100);color:var(--gray-700)">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir
            </a>
        </div>
        <div style="margin-top:12px;font-size:12px;color:#86efac">
            Quando o cliente pagar, o Pro da empresa <strong><?= htmlspecialchars($company['name'] ?? '') ?></strong> será ativado automaticamente via webhook.
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="border-radius:var(--radius);padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08)">
        <form method="POST">
            <div style="margin-bottom:16px">
                <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Empresa</label>
                <select name="company_id" required style="width:100%;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px;background:#fff">
                    <option value="">Selecione a empresa…</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($companyId && $c['id']==$companyId)?'selected':'' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                        (<?= $c['plan'] === 'pro' ? 'Pro' : 'Free' ?>)
                        — <?= htmlspecialchars($c['email']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Valor (R$)</label>
                    <input type="text" name="amount" required
                           value="<?= number_format($defaultPrice / 100, 2, ',', '.') ?>"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                    <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Valor padrão: preço mensal Pro</div>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:6px">Descrição</label>
                    <input type="text" name="description" value="PageQuiz Pro - Assinatura Mensal" maxlength="80"
                           style="width:100%;box-sizing:border-box;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:14px"/>
                </div>
            </div>

            <button type="submit" class="btn" style="background:var(--pacific);color:#fff;font-weight:700">
                <i class="fa-solid fa-link"></i> Gerar link de pagamento
            </button>
        </form>
    </div>
</div>
<?php superadminFoot(); ?>
