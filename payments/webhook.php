<?php
/**
 * Endpoint de webhooks EFI Bank.
 * Aceita apenas POST. Sem sessão. Sem HTML.
 * URL configurada na EFI: https://seudominio.com/payments/webhook.php
 */

// Somente POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/efi.php';

header('Content-Type: application/json');

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
