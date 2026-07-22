<?php
/**
 * Endpoint de webhooks EFI Bank.
 * Aceita apenas POST. Sem sessão. Sem HTML.
 * URL configurada na EFI: https://quiz.pageup.net.br/payments/webhook.php?ignorar=
 * (o mTLS mútuo exigido pela EFI é validado no Apache, não aqui — ver certs/README.md)
 */

// IPs oficiais da EFI Bank para notificacoes de webhook.
// https://dev.efipay.com.br/docs/api-pix/webhooks
const EFI_WEBHOOK_IPS = ['34.193.116.226'];

// Somente POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/efi.php';

header('Content-Type: application/json');

// Aceita apenas requisicoes vindas dos IPs oficiais da EFI.
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteIp, EFI_WEBHOOK_IPS, true)) {
    try {
        dbExec(
            "INSERT IGNORE INTO payment_events (efi_notification_id, event_type, raw_payload, processed) VALUES (?,?,?,2)",
            ['ip_rejected_' . time(), 'webhook_ip_rejected', json_encode(['ip' => $remoteIp])]
        );
    } catch (Throwable $ignored) {}
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$rawPayload = file_get_contents('php://input');

if (!$rawPayload) {
    http_response_code(400);
    echo json_encode(['error' => 'empty payload']);
    exit;
}

try {
    $result = efiProcessWebhook($rawPayload);

    if (!empty($result['already_processed'])) {
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'result' => $result]);

} catch (Throwable $e) {
    // Log do erro sem expor detalhes
    $notifId = md5($rawPayload);
    try {
        dbExec(
            "INSERT IGNORE INTO payment_events (efi_notification_id, event_type, raw_payload, processed) VALUES (?,?,?,2)",
            [$notifId . '_err_' . time(), 'webhook_error', $rawPayload]
        );
    } catch (Throwable $ignored) {}

    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
