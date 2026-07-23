<?php
/**
 * Wrapper do SDK EFI Bank para PageQuiz.
 * Encapsula autenticação, PIX, cobranças, assinaturas e links de pagamento.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Efi\EfiPay;

/* ─── Configuração ──────────────────────────────────────────────────────── */

function efiConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $s = function(string $key): string {
        $row = dbRow("SELECT value FROM system_settings WHERE `key`=?", [$key]);
        return $row['value'] ?? '';
    };

    $sandbox  = (bool)(int)$s('efi_sandbox');
    $certPath = __DIR__ . '/../' . ltrim($s('efi_cert_path'), '/');
    $certPass = $s('efi_cert_password');

    $cfg = [
        'client_id'     => $s('efi_client_id'),
        'client_secret' => $s('efi_client_secret'),
        'sandbox'       => $sandbox,
        'debug'         => false,
        'timeout'       => 30,
        'certificate'   => file_exists($certPath) ? $certPath : '',
        'password_cert' => $certPass,
        // Desabilita verificação SSL em sandbox local (XAMPP não tem CA da EFI)
        'validationCertificate' => !$sandbox,
    ];

    return $cfg;
}

function efiClient(): EfiPay {
    static $client = null;
    if ($client === null) {
        // Em sandbox local (XAMPP), o curl não tem o CA da EFI — usa o bundle do XAMPP
        $caBundle = 'C:/xampp/phpMyAdmin/vendor/composer/ca-bundle/res/cacert.pem';
        if (file_exists($caBundle)) {
            ini_set('curl.cainfo', $caBundle);
            ini_set('openssl.cafile', $caBundle);
        }
        $client = new EfiPay(efiConfig());
    }
    return $client;
}

function efiIsSandbox(): bool {
    return (bool)(int)(dbRow("SELECT value FROM system_settings WHERE `key`='efi_sandbox'")['value'] ?? 1);
}

function efiPixKey(): string {
    return dbRow("SELECT value FROM system_settings WHERE `key`='efi_pix_key'")['value'] ?? '';
}

function efiProPrice(): int {
    return (int)(dbRow("SELECT value FROM system_settings WHERE `key`='pro_price_monthly'")['value'] ?? 4990);
}

function efiProPriceFormatted(): string {
    return 'R$ ' . number_format(efiProPrice() / 100, 2, ',', '.');
}

/* ─── PIX ───────────────────────────────────────────────────────────────── */

/**
 * Cria uma cobrança PIX imediata.
 * Retorna ['txid', 'qrcode', 'copiaecola', 'pixCopiaECola', 'status']
 */
function efiCreatePixCharge(
    int $cents,
    string $txid,
    string $description,
    int $expiresIn = 1800,    // 30 min
    array $debtor  = []
): array {
    $pixKey = efiPixKey();
    if (!$pixKey) throw new RuntimeException('Chave PIX não configurada. Configure em Super Admin → Configurações.');

    $body = [
        'calendario'   => ['expiracao' => $expiresIn],
        'valor'        => ['original' => number_format($cents / 100, 2, '.', '')],
        'chave'        => $pixKey,
        'solicitacaoPagador' => $description,
    ];

    if ($debtor) {
        $body['devedor'] = $debtor; // ['cpf'=>'...', 'nome'=>'...'] ou ['cnpj'=>'...', 'nome'=>'...']
    }

    $response = efiClient()->pixCreateImmediateCharge([], $body);

    // Gerar QRCode usando o loc.id retornado pela API
    $locId      = $response['loc']['id'];
    $qrResponse = efiClient()->pixGenerateQRCode(['id' => $locId]);

    return [
        'txid'        => $response['txid'],
        'status'      => $response['status'],
        'qrcode'      => $qrResponse['imagemQrcode'],   // base64 da imagem
        'copiaecola'  => $qrResponse['qrcode'],          // texto copia e cola
    ];
}

/**
 * Consulta status de uma cobrança PIX pelo txid.
 */
function efiGetPixCharge(string $txid): array {
    return efiClient()->pixDetailCharge(['txid' => $txid]);
}

/* ─── Cartão de crédito (cobrança única) ────────────────────────────────── */

/**
 * Cria uma cobrança avulsa no cartão.
 * $token é gerado pelo JS SDK EFI no frontend — PHP nunca vê o número do cartão.
 */
function efiCreateCardCharge(
    int $cents,
    string $paymentToken,
    array $customer,          // ['name', 'cpf', 'email', 'birth', 'phone_number', 'address']
    string $description = 'PageQuiz Pro - Assinatura Mensal'
): array {
    $body = [
        'items' => [[
            'name'   => $description,
            'amount' => 1,
            'value'  => $cents,
        ]],
        'payment' => [
            'credit_card' => [
                'installments'   => 1,
                'payment_token'  => $paymentToken,
                'billing_address' => $customer['address'] ?? efiDefaultAddress(),
                'customer'       => [
                    'name'         => $customer['name'],
                    'cpf'          => preg_replace('/\D/', '', $customer['cpf'] ?? ''),
                    'email'        => $customer['email'] ?? '',
                    'birth'        => $customer['birth']  ?? '',
                    'phone_number' => preg_replace('/\D/', '', $customer['phone'] ?? ''),
                ],
            ],
        ],
    ];

    $response = efiClient()->createOneStepCharge([], $body);

    return [
        'charge_id' => $response['data']['charge_id'],
        'status'    => $response['data']['status'],
        'message'   => $response['data']['message'] ?? '',
    ];
}

/* ─── Assinatura recorrente (cartão) ────────────────────────────────────── */

/**
 * Cria uma assinatura recorrente mensal no cartão.
 */
function efiCreateCardSubscription(
    int $cents,
    string $paymentToken,
    array $customer
): array {
    // Criar plano de assinatura (ou reusar existente)
    $planId = efiGetOrCreateProPlan($cents);

    $body = [
        'plan_id' => $planId,
        'items'   => [[
            'name'   => 'PageQuiz Pro - Mensal',
            'amount' => 1,
            'value'  => $cents,
        ]],
        'payment' => [
            'credit_card' => [
                'payment_token'   => $paymentToken,
                'billing_address' => $customer['address'] ?? efiDefaultAddress(),
                'customer'        => [
                    'name'         => $customer['name'],
                    'cpf'          => preg_replace('/\D/', '', $customer['cpf'] ?? ''),
                    'email'        => $customer['email'] ?? '',
                    'birth'        => $customer['birth']  ?? '',
                    'phone_number' => preg_replace('/\D/', '', $customer['phone'] ?? ''),
                ],
            ],
        ],
    ];

    $response = efiClient()->createSubscription([], $body);

    return [
        'subscription_id' => $response['data']['subscription_id'],
        'charge_id'       => $response['data']['charge_id'],
        'status'          => $response['data']['status'],
        'next_billing'    => $response['data']['next_execution'] ?? null,
    ];
}

function efiCancelSubscription(string $subscriptionId): bool {
    $response = efiClient()->cancelSubscription(['id' => $subscriptionId]);
    return ($response['data']['status'] ?? '') === 'cancelled';
}

/**
 * Obtém ou cria o plano Pro mensal na EFI (cache em system_settings).
 */
function efiGetOrCreateProPlan(int $cents): int {
    $cached = dbRow("SELECT value FROM system_settings WHERE `key`='efi_plan_id'");
    if ($cached && (int)$cached['value'] > 0) return (int)$cached['value'];

    $response = efiClient()->createPlan([], [
        'name'      => 'PageQuiz Pro Mensal',
        'interval'  => 1,
        'repeats'   => null,
    ]);
    $planId = (int)$response['data']['plan_id'];

    // Salva para reusar
    dbExec("INSERT INTO system_settings (`key`, value, description) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), description = VALUES(description)",
           ['efi_plan_id', (string)$planId, 'ID do plano recorrente Pro na EFI Bank']);

    return $planId;
}

/* ─── Link de pagamento ─────────────────────────────────────────────────── */

/**
 * Cria um link de pagamento EFI (aceita PIX e cartão).
 */
function efiCreatePaymentLink(
    int $cents,
    string $description,
    int $companyId
): array {
    $body = [
        'items' => [[
            'name'   => $description,
            'amount' => 1,
            'value'  => $cents,
        ]],
        'metadata' => [
            'custom_id' => "company_{$companyId}",
        ],
        'settings' => [
            'payment_method'           => 'all',
            'request_delivery_address' => false,
            'expire_at'                => date('Y-m-d', strtotime('+30 days')),
        ],
    ];

    $response = efiClient()->createOneStepLink([], $body);

    return [
        'link_id' => $response['data']['link_id'] ?? '',
        'url'     => $response['data']['payment_url'] ?? '',
        'status'  => $response['data']['status'] ?? '',
    ];
}

/* ─── Webhook ───────────────────────────────────────────────────────────── */

/**
 * Valida e processa uma notificação webhook EFI.
 * Retorna array com ['event_type', 'company_id', 'subscription_id'] ou lança exceção.
 */
function efiProcessWebhook(string $rawPayload): array {
    $data = json_decode($rawPayload, true);
    if (!$data) throw new RuntimeException('Payload inválido');

    // Idempotência: notificationId único
    $notifId = $data['notification'] ?? $data['txid'] ?? md5($rawPayload);

    $existing = dbRow("SELECT id FROM payment_events WHERE efi_notification_id=?", [$notifId]);
    if ($existing) {
        return ['already_processed' => true, 'event_id' => $existing['id']];
    }

    // PIX recebido
    if (isset($data['pix'])) {
        return efiHandlePixWebhook($data, $notifId, $rawPayload);
    }

    // Cobrança de cartão / assinatura
    if (isset($data['charge_id']) || isset($data['data']['charge_id']) || isset($data['notification_id'])) {
        return efiHandleChargeWebhook($data, $notifId, $rawPayload);
    }

    // Salvar evento não reconhecido para análise
    dbExec("INSERT IGNORE INTO payment_events (efi_notification_id, event_type, raw_payload, processed) VALUES (?,?,?,2)",
           [$notifId, 'unknown', $rawPayload]);

    return ['event_type' => 'unknown'];
}

function efiHandlePixWebhook(array $data, string $notifId, string $raw): array {
    $results = [];
    foreach ($data['pix'] as $pix) {
        $txid   = $pix['txid'] ?? '';
        $status = $pix['status'] ?? 'ATIVA';

        $sub = dbRow("SELECT * FROM subscriptions WHERE pix_txid=?", [$txid]);
        if (!$sub) continue;

        $eventType  = 'pix_unknown';
        $processed  = 2;

        if ($status === 'CONCLUIDA' || isset($pix['endToEndId'])) {
            $eventType = 'pix_paid';
            efiActivatePro((int)$sub['company_id'], (int)$sub['id'], 'pix');
            $processed = 1;
        }

        dbExec(
            "INSERT IGNORE INTO payment_events (company_id, subscription_id, efi_notification_id, event_type, raw_payload, processed) VALUES (?,?,?,?,?,?)",
            [$sub['company_id'], $sub['id'], $notifId . '_' . $txid, $eventType, $raw, $processed]
        );
        $results[] = ['event_type' => $eventType, 'txid' => $txid];
    }
    return $results[0] ?? ['event_type' => 'pix_no_match'];
}

function efiHandleChargeWebhook(array $data, string $notifId, string $raw): array {
    $chargeId = $data['charge_id'] ?? $data['data']['charge_id'] ?? '';
    $status   = $data['status']    ?? $data['data']['status']    ?? '';

    $sub = $chargeId
        ? dbRow("SELECT * FROM subscriptions WHERE efi_charge_id=?", [$chargeId])
        : null;

    // Fallback: pagamentos via link (superadmin/payment-link.php) nao tem efi_charge_id
    // gravado no momento da criacao -- casa pelo metadata.custom_id ("company_{id}")
    // que foi enviado na criacao do link, e vincula o charge_id retroativamente.
    if (!$sub) {
        $customId = $data['metadata']['custom_id'] ?? $data['data']['metadata']['custom_id'] ?? '';
        if ($customId && preg_match('/^company_(\d+)$/', $customId, $m)) {
            $linkCompanyId = (int)$m[1];
            $sub = dbRow(
                "SELECT * FROM subscriptions WHERE company_id=? AND type='payment_link' AND status='pending' ORDER BY created_at DESC LIMIT 1",
                [$linkCompanyId]
            );
            if ($sub && $chargeId) {
                dbExec("UPDATE subscriptions SET efi_charge_id=? WHERE id=?", [$chargeId, $sub['id']]);
            }
        }
    }

    $companyId = $sub ? (int)$sub['company_id'] : 0;
    $subId     = $sub ? (int)$sub['id']         : 0;

    $eventType = match($status) {
        'paid'      => 'charge_paid',
        'unpaid'    => 'charge_failed',
        'cancelled' => 'charge_cancelled',
        'waiting'   => 'charge_waiting',
        default     => 'charge_' . $status,
    };

    dbExec(
        "INSERT IGNORE INTO payment_events (company_id, subscription_id, efi_notification_id, event_type, raw_payload, processed) VALUES (?,?,?,?,?,?)",
        [$companyId, $subId, $notifId, $eventType, $raw, 0]
    );
    $eventId = (int)dbLastId();

    if ($status === 'paid' && $sub) {
        efiActivatePro($companyId, $subId, $sub['type'] ?? 'card_once');
        dbExec("UPDATE payment_events SET processed=1 WHERE id=?", [$eventId]);
    } elseif ($status === 'unpaid' && $sub && $sub['type'] === 'card_recurring') {
        // Período de graça: 7 dias
        $graceUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
        dbExec("UPDATE subscriptions SET status='overdue', grace_until=? WHERE id=?", [$graceUntil, $subId]);
        dbExec("UPDATE payment_events SET processed=1 WHERE id=?", [$eventId]);
        $company = dbRow("SELECT name, email FROM companies WHERE id=?", [$companyId]);
        if ($company) notifyPaymentOverdue($company['name'], $company['email'], $graceUntil);
    }

    return ['event_type' => $eventType, 'charge_id' => $chargeId, 'status' => $status];
}

/* ─── Ativar Pro após pagamento confirmado ───────────────────────────────── */

function efiActivatePro(int $companyId, int $subscriptionId, string $type): void {
    $now          = date('Y-m-d H:i:s');
    // Pagamentos avulsos (pix/card_once/manual/payment_link) valem por 30 dias corridos;
    // assinaturas recorrentes renovam a cada cobranca bem-sucedida via webhook.
    $nextBilling  = $type === 'card_recurring'
        ? date('Y-m-d H:i:s', strtotime('+1 month'))
        : date('Y-m-d H:i:s', strtotime('+30 days'));

    dbExec("UPDATE subscriptions SET status='active', next_billing_at=?, updated_at=? WHERE id=?",
           [$nextBilling, $now, $subscriptionId]);

    dbExec("UPDATE companies SET plan='pro', status='active', updated_at=? WHERE id=?",
           [$now, $companyId]);

    // Audit log
    dbExec("INSERT INTO audit_log (actor_type, actor_id, action, target_company_id, detail) VALUES (?,?,?,?,?)",
           ['system', 0, 'payment_confirmed', $companyId,
            json_encode(['subscription_id' => $subscriptionId, 'type' => $type])]);

    $company = dbRow("SELECT name, email FROM companies WHERE id=?", [$companyId]);
    if ($company) {
        notifyPaymentConfirmed($company['name'], $company['email'], $type);
    }
}

/* ─── Utilitários ───────────────────────────────────────────────────────── */

function efiWebhookUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host/payments/webhook.php";
}

function efiDefaultAddress(): array {
    return [
        'street'     => 'Rua Não Informada',
        'number'     => 'S/N',
        'neighborhood' => 'Centro',
        'zipcode'    => '00000000',
        'city'       => 'Cidade',
        'state'      => 'RO',
    ];
}

/**
 * Retorna os últimos N dias de cobranças PIX (útil para reprocessamento).
 */
function efiListPixCharges(int $days = 7): array {
    $inicio = date('Y-m-d\TH:i:s\Z', strtotime("-$days days"));
    $fim    = date('Y-m-d\TH:i:s\Z');
    return efiClient()->pixListCharges(['inicio' => $inicio, 'fim' => $fim]);
}
