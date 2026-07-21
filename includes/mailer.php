<?php
require_once __DIR__ . '/db.php';

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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
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
