<?php

namespace Piwik\Plugins\AspendoraIdentity;

use Exception;

/**
 * Minimal EspoCRM REST client for the identity bridge — plain curl, no SDK.
 *
 * EspoCRM (crm.aspendora.com) is the CRM of record for the stack; GHL is the
 * legacy migration source (its push in Sync.php stays env-gated until cutover).
 *
 * Env: ASPENDORA_ESPO_URL (e.g. https://crm.aspendora.com), ASPENDORA_ESPO_API_KEY.
 * The API-key user's role needs read/create on Lead, read on Contact, and
 * create on AspPageView, plus edit on Lead/Contact for the engagement fields.
 */
class EspoClient
{
    public function isConfigured(): bool
    {
        return (bool) (getenv('ASPENDORA_ESPO_URL') && getenv('ASPENDORA_ESPO_API_KEY'));
    }

    /**
     * Find the CRM record for an email. Contacts win over Leads (a converted
     * person should collect page views on their Contact, not a duplicate Lead).
     *
     * @return array{type:string, id:string, pageViewCount:int}|null
     */
    public function findTargetByEmail(string $email): ?array
    {
        foreach (['Contact', 'Lead'] as $type) {
            $resp = $this->request('GET', '/' . $type, [
                'maxSize'             => 1,
                'select'              => 'id,aspPageViewCount',
                'where[0][type]'      => 'equals',
                'where[0][attribute]' => 'emailAddress',
                'where[0][value]'     => $email,
            ]);
            if (!empty($resp['list'][0]['id'])) {
                return [
                    'type'          => $type,
                    'id'            => $resp['list'][0]['id'],
                    'pageViewCount' => (int) ($resp['list'][0]['aspPageViewCount'] ?? 0),
                ];
            }
        }
        return null;
    }

    /** @return array{type:string, id:string, pageViewCount:int} */
    public function createLead(string $email, ?string $firstName, ?string $lastName): array
    {
        $resp = $this->request('POST', '/Lead', null, [
            'emailAddress' => $email,
            'firstName'    => $firstName ?: null,
            'lastName'     => $lastName ?: $email,
            'source'       => 'Web Site',
            'description'  => 'Created by Matomo identity bridge (identified website visitor).',
        ]);
        if (empty($resp['id'])) {
            throw new Exception('Espo lead create failed for ' . $email);
        }
        return ['type' => 'Lead', 'id' => $resp['id'], 'pageViewCount' => 0];
    }

    public function createPageView(string $targetType, string $targetId, string $url, string $title, string $viewedAtUtc): void
    {
        $this->request('POST', '/AspPageView', null, [
            'name'       => mb_substr($title !== '' ? $title : $url, 0, 150),
            'url'        => mb_substr($url, 0, 255),
            'title'      => mb_substr($title, 0, 255),
            'targetType' => $targetType,
            'targetId'   => $targetId,
        ]);
    }

    /** Updates the engagement fields Espo's lifecycle/scoring hooks react to. */
    public function touchEngagement(string $targetType, string $targetId, string $lastSeenUtc, int $pageViewCount): void
    {
        $this->request('PUT', '/' . $targetType . '/' . $targetId, null, [
            'aspLastPageViewAt' => $lastSeenUtc,
            'aspLastEngagedAt'  => $lastSeenUtc,
            'aspPageViewCount'  => $pageViewCount,
        ]);
    }

    private function request(string $method, string $path, ?array $query = null, ?array $body = null): array
    {
        $url = rtrim(getenv('ASPENDORA_ESPO_URL'), '/') . '/api/v1' . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'X-Api-Key: ' . getenv('ASPENDORA_ESPO_API_KEY'),
                'Content-Type: application/json',
            ],
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode(array_filter($body, fn($v) => $v !== null));
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception("Espo $method $path failed: $err");
        }
        if ($code >= 400) {
            throw new Exception("Espo $method $path HTTP $code: " . substr($raw, 0, 200));
        }
        return json_decode($raw, true) ?: [];
    }
}
