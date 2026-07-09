<?php
/**
 * Polling de status de pagamento.
 * Retorna JSON com status atual da subscription.
 * Chamado pelo JS da página de checkout a cada 3 segundos.
 */
session_name('pageup_admin');
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

$subId     = (int)($_GET['sub'] ?? 0);
$companyId = adminCompanyId();

if (!$subId) {
    echo json_encode(['status' => 'unknown']);
    exit;
}

// Garante que a subscription pertence à empresa do admin
$sub = dbRow("SELECT * FROM subscriptions WHERE id=? AND company_id=?", [$subId, $companyId]);
if (!$sub) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

// Para PIX: consulta status em tempo real na EFI se ainda pendente
if ($sub['type'] === 'pix' && $sub['status'] === 'pending' && $sub['pix_txid']) {
    try {
        require_once __DIR__ . '/../includes/efi.php';
        $efiData = efiGetPixCharge($sub['pix_txid']);
        if (($efiData['status'] ?? '') === 'CONCLUIDA') {
            efiActivatePro($companyId, (int)$sub['id'], 'pix');
            $sub['status'] = 'active';
        }
    } catch (Throwable $e) {
        // Não expõe erro — retorna status atual do banco
    }
}

// Recarregar empresa
$company = dbRow("SELECT plan, status FROM companies WHERE id=?", [$companyId]);

echo json_encode([
    'sub_status'     => $sub['status'],
    'company_plan'   => $company['plan']   ?? 'free',
    'company_status' => $company['status'] ?? 'active',
    'next_billing'   => $sub['next_billing_at'],
    'grace_until'    => $sub['grace_until'],
    'activated'      => ($company['plan'] === 'pro' && $company['status'] === 'active'),
]);
