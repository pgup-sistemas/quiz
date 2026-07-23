<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

/**
 * Verifica e aplica downgrade automático para empresas Pro cujo período de assinatura expirou.
 * Chamado no adminHead() — executa no máximo uma vez por hora por processo PHP (via cache na sessão).
 *
 * T29: detecta assinaturas expiradas/vencidas → rebaixa plano para 'free'
 * T30: inativa quizzes excedentes ao limite free_quiz_limit
 */
function checkAndApplyDowngrades(): void {
    // Throttle: roda no máximo 1x/hora por sessão para não impactar toda requisição
    $key = '_billing_checked_at';
    if (!empty($_SESSION[$key]) && (time() - (int)$_SESSION[$key]) < 3600) {
        return;
    }
    $_SESSION[$key] = time();

    $freeLimit = (int)(dbRow("SELECT value FROM system_settings WHERE `key`='free_quiz_limit'")['value'] ?? 12);
    $now       = date('Y-m-d H:i:s');

    // Empresa Pro sem nenhuma assinatura que ainda garanta acesso:
    // - 'active' com next_billing_at no futuro (ou NULL, por compatibilidade legada), ou
    // - 'overdue' ainda dentro do periodo de carencia (grace_until no futuro)
    // Cobre tanto assinatura recorrente vencida quanto pagamento avulso expirado (30 dias).
    $expired = dbRows("
        SELECT c.id AS company_id
        FROM companies c
        WHERE c.plan = 'pro' AND c.status = 'active'
          AND NOT EXISTS (
              SELECT 1 FROM subscriptions s
              WHERE s.company_id = c.id
                AND (
                    (s.status = 'active'  AND (s.next_billing_at IS NULL OR s.next_billing_at >= ?))
                    OR (s.status = 'overdue' AND s.grace_until IS NOT NULL AND s.grace_until >= ?)
                )
          )
    ", [$now, $now]);

    if (empty($expired)) return;

    foreach ($expired as $row) {
        $cid = (int)$row['company_id'];
        _applyDowngrade($cid, $freeLimit);
    }
}

/**
 * Aplica downgrade em uma empresa específica (usado pelo superadmin e pelo job automático).
 * Retorna número de quizzes inativados.
 */
function _applyDowngrade(int $companyId, int $freeLimit): int {
    $quizzesAtivos = dbRows(
        "SELECT id FROM quizzes WHERE company_id = ? AND active = 1 ORDER BY created_at ASC",
        [$companyId]
    );
    $excess      = array_slice($quizzesAtivos, $freeLimit);
    $inactivated = 0;

    foreach ($excess as $q) {
        dbExec("UPDATE quizzes SET active = 0 WHERE id = ?", [$q['id']]);
        $inactivated++;
    }

    dbExec(
        "UPDATE companies SET plan = 'free', status = 'active', updated_at = NOW() WHERE id = ?",
        [$companyId]
    );
    dbExec(
        "INSERT INTO audit_log (actor_type, actor_id, action, target_company_id, ip, detail)
         VALUES ('system', 0, 'auto_downgrade', ?, '', ?)",
        [$companyId, json_encode(['inactivated' => $inactivated, 'limit' => $freeLimit])]
    );

    $company = dbRow("SELECT name, email FROM companies WHERE id=?", [$companyId]);
    if ($company) {
        notifyDowngradeApplied($company['name'], $company['email'], $inactivated);
    }

    return $inactivated;
}

/**
 * Endpoint de webhook EFI Bank — registra evento e processa pagamento/cancelamento.
 * Chamado em api/efi-webhook.php.
 */
function processEfiWebhookEvent(array $payload): void {
    $eventType = $payload['event'] ?? ($payload['type'] ?? 'unknown');
    $raw       = json_encode($payload);

    // Extrai identificadores EFI
    $efiSubId  = $payload['subscription']['id']  ?? null;
    $efiCharge = $payload['charge']['id']         ?? $payload['data']['id'] ?? null;
    $txid      = $payload['pix'][0]['txid']        ?? $payload['data']['txid'] ?? null;

    // Localiza assinatura pelo subscription_id ou charge_id
    $sub = null;
    if ($efiSubId) {
        $sub = dbRow("SELECT * FROM subscriptions WHERE efi_subscription_id = ?", [$efiSubId]);
    }
    if (!$sub && $efiCharge) {
        $sub = dbRow("SELECT * FROM subscriptions WHERE efi_charge_id = ?", [$efiCharge]);
    }
    if (!$sub && $txid) {
        $sub = dbRow("SELECT * FROM subscriptions WHERE pix_txid = ?", [$txid]);
    }

    $companyId = $sub ? (int)$sub['company_id'] : null;

    // Registra evento bruto (idempotente pelo efi_notification_id)
    $notifId = $payload['notification_id'] ?? $payload['id'] ?? uniqid('evt_');
    try {
        dbExec(
            "INSERT INTO payment_events (company_id, subscription_id, efi_notification_id, event_type, raw_payload, processed)
             VALUES (?, ?, ?, ?, ?, 0)",
            [$companyId, $sub['id'] ?? null, $notifId, $eventType, $raw]
        );
    } catch (\Exception $e) {
        // Evento duplicado (UNIQUE efi_notification_id) — ignora
        return;
    }
    $peId = (int)dbLastId();

    if (!$companyId || !$sub) {
        // Evento sem empresa mapeada — registrado, mas não há ação
        dbExec("UPDATE payment_events SET processed = 1 WHERE id = ?", [$peId]);
        return;
    }

    $now = date('Y-m-d H:i:s');

    // ── Pagamento confirmado ──────────────────────────────────────────────────
    if (in_array($eventType, ['charge.paid','subscription.renewed','pix.paid','payment.created'])) {
        $nextBilling = date('Y-m-d H:i:s', strtotime('+1 month'));
        dbExec(
            "UPDATE subscriptions SET status='active', next_billing_at=?, updated_at=? WHERE id=?",
            [$nextBilling, $now, $sub['id']]
        );
        dbExec(
            "UPDATE companies SET plan='pro', status='active', updated_at=? WHERE id=?",
            [$now, $companyId]
        );
    }

    // ── Cancelamento / inadimplência ─────────────────────────────────────────
    elseif (in_array($eventType, ['subscription.cancelled','charge.cancelled','charge.expired','subscription.overdue'])) {
        // Grace period de 7 dias antes do downgrade
        $graceUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
        dbExec(
            "UPDATE subscriptions SET status='overdue', grace_until=?, updated_at=? WHERE id=?",
            [$graceUntil, $now, $sub['id']]
        );
        dbExec(
            "INSERT INTO audit_log (actor_type, actor_id, action, target_company_id, ip, detail)
             VALUES ('system', 0, 'subscription_overdue', ?, '', ?)",
            [$companyId, json_encode(['event' => $eventType, 'grace_until' => $graceUntil])]
        );

        $company = dbRow("SELECT name, email FROM companies WHERE id=?", [$companyId]);
        if ($company) {
            notifyPaymentOverdue($company['name'], $company['email'], $graceUntil);
        }
    }

    dbExec("UPDATE payment_events SET processed = 1 WHERE id = ?", [$peId]);
}

/**
 * Reprocessa um evento de webhook já registrado (processed=2).
 * Não usa processEfiWebhookEvent() porque esse tenta INSERT e falha
 * na UNIQUE constraint do efi_notification_id no retry.
 */
function reprocessPaymentEvent(int $eventId): bool {
    $event = dbRow("SELECT * FROM payment_events WHERE id=?", [$eventId]);
    if (!$event) return false;

    $eventType = $event['event_type'];
    $companyId = $event['company_id'] ? (int)$event['company_id'] : null;
    $subId     = $event['subscription_id'] ? (int)$event['subscription_id'] : null;
    $sub       = $subId ? dbRow("SELECT * FROM subscriptions WHERE id=?", [$subId]) : null;
    $now       = date('Y-m-d H:i:s');

    if ($companyId && $sub) {
        $company = dbRow("SELECT name, email FROM companies WHERE id=?", [$companyId]);
        if (in_array($eventType, ['charge.paid','subscription.renewed','pix.paid','payment.created'])) {
            $nextBilling = date('Y-m-d H:i:s', strtotime('+1 month'));
            dbExec("UPDATE subscriptions SET status='active', next_billing_at=?, updated_at=? WHERE id=?",
                   [$nextBilling, $now, $sub['id']]);
            dbExec("UPDATE companies SET plan='pro', status='active', updated_at=? WHERE id=?",
                   [$now, $companyId]);
            if ($company) notifyPaymentConfirmed($company['name'], $company['email'], $sub['type'] ?? 'manual');
        } elseif (in_array($eventType, ['subscription.cancelled','charge.cancelled','charge.expired','subscription.overdue'])) {
            $graceUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
            dbExec("UPDATE subscriptions SET status='overdue', grace_until=?, updated_at=? WHERE id=?",
                   [$graceUntil, $now, $sub['id']]);
            if ($company) notifyPaymentOverdue($company['name'], $company['email'], $graceUntil);
        }
    }

    dbExec("UPDATE payment_events SET processed=1 WHERE id=?", [$eventId]);
    return true;
}
