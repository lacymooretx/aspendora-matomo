<?php

namespace Piwik\Plugins\AspendoraInsights;

use Exception;
use Piwik\Mail;

/**
 * Outbound mail for Aspendora plugins. The container has no MTA, so when
 * ASPENDORA_SMTP2GO_API_KEY is set mail goes out via the SMTP2GO HTTP API
 * (sender must be verified in the SMTP2GO dashboard; override with
 * ASPENDORA_MAIL_FROM, default analytics@aspendora.com). Falls back to
 * Piwik\Mail otherwise (works once [mail] SMTP is configured in Matomo).
 */
class Mailer
{
    public static function send(string $to, string $subject, string $html): void
    {
        $key = getenv('ASPENDORA_SMTP2GO_API_KEY');
        if (!$key) {
            $mail = new Mail();
            $mail->setDefaultFromPiwik();
            $mail->addTo($to);
            $mail->setSubject($subject);
            $mail->setWrappedHtmlBody($html);
            $mail->send();
            return;
        }
        $ch = curl_init('https://api.smtp2go.com/v3/email/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Smtp2go-Api-Key: ' . $key,
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                'sender'    => getenv('ASPENDORA_MAIL_FROM') ?: 'analytics@aspendora.com',
                'to'        => [$to],
                'subject'   => $subject,
                'html_body' => $html,
            ]),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception('SMTP2GO request failed: ' . $err);
        }
        $resp = json_decode($raw, true) ?: [];
        if ($code >= 400 || (int) ($resp['data']['succeeded'] ?? 0) < 1) {
            throw new Exception('SMTP2GO send failed (HTTP ' . $code . '): ' . substr($raw, 0, 300));
        }
    }
}
