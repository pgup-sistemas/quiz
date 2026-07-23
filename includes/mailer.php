<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Envia um e-mail transacional.
 * Usa a API Resend (via curl) se `resend_api_key` estiver configurado;
 * caso contrário cai no PHP mail() nativo.
 *
 * @param string      $to      Endereço de destino
 * @param string      $subject Assunto
 * @param string      $html    Corpo HTML
 * @param string|null $toName  Nome do destinatário (opcional)
 * @return bool
 */
function sendMail(string $to, string $subject, string $html, ?string $toName = null): bool {
    $settings = [];
    foreach (dbRows("SELECT `key`, value FROM system_settings WHERE `key` IN ('resend_api_key','mail_from','mail_from_name','app_name')") as $r) {
        $settings[$r['key']] = $r['value'];
    }

    $fromEmail = $settings['mail_from']      ?? 'noreply@quiz.pageup.net.br';
    $fromName  = $settings['mail_from_name'] ?? ($settings['app_name'] ?? 'PageQuiz');
    $apiKey    = $settings['resend_api_key'] ?? '';

    if ($apiKey) {
        return _sendViaResend($apiKey, $fromEmail, $fromName, $to, $toName ?? $to, $subject, $html);
    }

    return _sendViaNativeMail($fromEmail, $fromName, $to, $toName, $subject, $html);
}

function _sendViaResend(string $apiKey, string $fromEmail, string $fromName, string $to, string $toName, string $subject, string $html): bool {
    $payload = json_encode([
        'from'    => "$fromName <$fromEmail>",
        'to'      => $toName && $toName !== $to ? ["$toName <$to>"] : [$to],
        'subject' => $subject,
        'html'    => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer $apiKey",
        ],
    ]);
    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = $code >= 200 && $code < 300;
    if (!$ok) {
        error_log("[mailer] Falha ao enviar via Resend (to=$to, http=$code): " . ($curlErr ?: $resp));
    }
    return $ok;
}

function _sendViaNativeMail(string $fromEmail, string $fromName, string $to, ?string $toName, string $subject, string $html): bool {
    $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject)  . '?=';
    $toLine         = $toName ? "\"$toName\" <$to>" : $to;

    $boundary = bin2hex(random_bytes(8));
    $headers  = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "From: $encodedFrom <$fromEmail>",
        "Reply-To: $fromEmail",
        "X-Mailer: PageQuiz/1.0",
    ]);

    // Plain-text fallback (strip tags)
    $text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));

    $body = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text)) . "\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . "--$boundary--";

    return @mail($toLine, $encodedSubject, $body, $headers);
}

// ── Templates ──────────────────────────────────────────────────────────────────

function mailTemplate(string $title, string $body, string $footer = ''): string {
    $settings = [];
    foreach (dbRows("SELECT `key`, value FROM system_settings WHERE `key` IN ('app_name','mail_from')") as $r) {
        $settings[$r['key']] = $r['value'];
    }
    $appName = htmlspecialchars($settings['app_name'] ?? 'PageQuiz');

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>$title</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Helvetica Neue',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%">
  <!-- Logo / Header -->
  <tr><td style="background:#023047;border-radius:12px 12px 0 0;padding:28px 32px;text-align:center">
    <div style="font-size:22px;font-weight:700;color:#fff;letter-spacing:-.5px">
      Page<span style="color:#FFB703">Quiz</span>
    </div>
    <div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:4px;letter-spacing:.5px">by PageUp Sistemas</div>
  </td></tr>
  <!-- Body -->
  <tr><td style="background:#fff;padding:36px 32px;color:#334155;font-size:15px;line-height:1.7;border-radius:0 0 12px 12px">
    <h2 style="font-size:18px;font-weight:700;color:#023047;margin:0 0 20px">$title</h2>
    $body
    <div style="margin-top:32px;padding-top:24px;border-top:1px solid #e8eef2;font-size:12px;color:#94a3b8;text-align:center">
      $appName &mdash; Plataforma de treinamento corporativo<br/>
      $footer
    </div>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
}

function mailBtnHtml(string $url, string $label): string {
    $url   = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return <<<HTML
<div style="text-align:center;margin:28px 0">
  <a href="$url" style="display:inline-block;padding:14px 32px;background:#219EBC;color:#fff;font-size:15px;font-weight:700;border-radius:10px;text-decoration:none;letter-spacing:.3px">$label</a>
</div>
HTML;
}

/**
 * Dispara as duas notificações de uma solicitação de plano Pro
 * (equipe PageUp + confirmação para a empresa solicitante).
 * Usada tanto no cadastro inicial (cadastro.php) quanto no upgrade
 * manual pelo painel admin (admin/upgrade.php). Best-effort: falha de
 * envio não deve impedir o fluxo — companies.status já é a fonte de
 * verdade e fica visível para o superadmin.
 */
function notifyProRequest(string $companyName, string $companyEmail, string $supportEmail): void {
    try {
        $adminUrl = absoluteUrl('superadmin/companies.php?status=pending_payment');
        sendMail(
            $supportEmail,
            'Nova solicitação de plano Pro — ' . $companyName,
            mailTemplate(
                'Nova solicitação de upgrade Pro',
                '<p><strong>Empresa:</strong> ' . e($companyName) . '</p>'
              . '<p><strong>E-mail de contato:</strong> ' . e($companyEmail) . '</p>'
              . '<p>Acesse o painel de superadmin para aprovar a ativação.</p>'
              . mailBtnHtml($adminUrl, 'Ver solicitação')
            )
        );
        if ($companyEmail) {
            sendMail(
                $companyEmail,
                'Recebemos sua solicitação do plano Pro — PageQuiz',
                mailTemplate(
                    'Solicitação recebida!',
                    '<p>Olá, ' . e($companyName) . '!</p>'
                  . '<p>Recebemos sua solicitação de upgrade para o plano <strong>Pro</strong>. Nossa equipe vai confirmar os detalhes e ativar o plano em até 1 dia útil.</p>'
                ),
                $companyName
            );
        }
    } catch (\Throwable $e) {
        error_log('[notifyProRequest] Falha ao enviar e-mail de solicitação Pro: ' . $e->getMessage());
    }
}

/**
 * Notifica a empresa que um pagamento foi confirmado e o Pro foi ativado.
 * Chamada por efiActivatePro() em includes/efi.php. Best-effort.
 */
function notifyPaymentConfirmed(string $companyName, string $companyEmail, string $type): void {
    if (!$companyEmail) return;
    $typeLabels = [
        'pix'            => 'PIX',
        'card_once'      => 'cartão (cobrança única)',
        'card_recurring' => 'cartão (assinatura recorrente)',
        'payment_link'   => 'link de pagamento',
        'manual'         => 'ativação manual',
    ];
    $label = $typeLabels[$type] ?? $type;
    try {
        sendMail(
            $companyEmail,
            'Pagamento confirmado — Pro ativado! · PageQuiz',
            mailTemplate(
                'Pro ativado com sucesso!',
                '<p>Olá, ' . e($companyName) . '!</p>'
              . '<p>Recebemos seu pagamento via <strong>' . e($label) . '</strong> e o plano <strong>Pro</strong> já está ativo, com quizzes ilimitados, certificado personalizado e todos os recursos liberados.</p>'
            ),
            $companyName
        );
    } catch (\Throwable $e) {
        error_log('[notifyPaymentConfirmed] Falha ao enviar e-mail: ' . $e->getMessage());
    }
}

/**
 * Notifica a empresa que o pagamento está em atraso e o Pro entrou em
 * período de carência. Chamada pelo processamento de webhook em
 * includes/billing.php e includes/efi.php.
 */
function notifyPaymentOverdue(string $companyName, string $companyEmail, string $graceUntil): void {
    if (!$companyEmail) return;
    try {
        sendMail(
            $companyEmail,
            'Pagamento pendente — regularize até ' . date('d/m/Y', strtotime($graceUntil)) . ' · PageQuiz',
            mailTemplate(
                'Pagamento não identificado',
                '<p>Olá, ' . e($companyName) . '!</p>'
              . '<p>Não conseguimos confirmar seu último pagamento do plano <strong>Pro</strong>. Você tem até <strong>' . date('d/m/Y', strtotime($graceUntil)) . '</strong> para regularizar antes que o plano seja rebaixado para Free (o que pode desativar quizzes que excedam o limite gratuito).</p>'
              . mailBtnHtml(absoluteUrl('admin/billing.php'), 'Regularizar pagamento')
            ),
            $companyName
        );
    } catch (\Throwable $e) {
        error_log('[notifyPaymentOverdue] Falha ao enviar e-mail: ' . $e->getMessage());
    }
}

/**
 * Notifica a empresa que o downgrade automático Pro → Free foi aplicado
 * por falta de pagamento. Chamada por _applyDowngrade() em includes/billing.php.
 */
function notifyDowngradeApplied(string $companyName, string $companyEmail, int $inactivated): void {
    if (!$companyEmail) return;
    try {
        sendMail(
            $companyEmail,
            'Plano Pro expirado — conta rebaixada para Free · PageQuiz',
            mailTemplate(
                'Plano rebaixado para Free',
                '<p>Olá, ' . e($companyName) . '!</p>'
              . '<p>Seu plano <strong>Pro</strong> expirou por falta de pagamento e sua conta foi rebaixada para o plano <strong>Free</strong>.</p>'
              . ($inactivated > 0
                    ? '<p><strong>' . $inactivated . ' quiz(zes)</strong> que excediam o limite do plano Free foram desativados automaticamente — eles continuam salvos e podem ser reativados assim que você fizer upgrade novamente.</p>'
                    : '')
              . mailBtnHtml(absoluteUrl('admin/upgrade.php'), 'Reativar o Pro')
            ),
            $companyName
        );
    } catch (\Throwable $e) {
        error_log('[notifyDowngradeApplied] Falha ao enviar e-mail: ' . $e->getMessage());
    }
}
