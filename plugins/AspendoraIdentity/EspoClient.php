<?php

namespace Piwik\Plugins\AspendoraIdentity;

use Exception;

/**
 * EspoCRM identity-bridge client.
 *
 * EspoCRM (crm.aspendora.com) is the CRM of record for the stack; GHL is the
 * legacy migration source (its push in Sync.php stays env-gated until cutover).
 *
 * All ingestion goes through the CRM's AspBridgeIngest entry point, which feeds
 * Espo's own TrackingService — page-view counters, engagement touches, lifecycle
 * and scoring behave exactly as if Espo's native snippet had reported (those
 * fields are readOnly over plain REST by design, so REST writes are NOT an option).
 *
 * Env: ASPENDORA_ESPO_URL (e.g. https://crm.aspendora.com),
 *      ASPENDORA_ESPO_BRIDGE_KEY (CRM Settings param aspBridgeIngestKey).
 */
class EspoClient
{
    public function isConfigured(): bool
    {
        return (bool) (getenv('ASPENDORA_ESPO_URL') && getenv('ASPENDORA_ESPO_BRIDGE_KEY'));
    }

    /**
     * @param array<int, array{url:string, title:string}> $views
     * @return array{targetType:string, targetId:string, recorded:int}
     */
    public function ingest(string $email, ?string $firstName, ?string $lastName, array $views): array
    {
        $url = rtrim(getenv('ASPENDORA_ESPO_URL'), '/')
            . '/?entryPoint=AspBridgeIngest&key=' . rawurlencode(getenv('ASPENDORA_ESPO_BRIDGE_KEY'));
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([
                'email'     => $email,
                'firstName' => (string) $firstName,
                'lastName'  => (string) $lastName,
                'views'     => array_map(fn($v) => ['url' => $v['url'], 'title' => $v['title']], $views),
            ]),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception('Espo bridge ingest failed: ' . $err);
        }
        if ($code >= 400) {
            throw new Exception('Espo bridge ingest HTTP ' . $code . ': ' . substr($raw, 0, 200));
        }
        $resp = json_decode($raw, true) ?: [];
        if (($resp['status'] ?? '') !== 'ok') {
            throw new Exception('Espo bridge ingest rejected: ' . substr($raw, 0, 200));
        }
        return $resp;
    }
}
