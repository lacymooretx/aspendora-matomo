<?php

namespace Piwik\Plugins\AspendoraIdentity;

use Exception;

/**
 * Minimal GoHighLevel (LeadConnector) v2 client — plain curl, no SDK.
 * Uses only endpoints documented in ~/code/apis/highlevel-api/endpoints.md:
 * upsert (dedupes by email, returns the contact), get-by-id, and add-tags.
 * The v2 search endpoint's body shape is undocumented there, so upsert
 * doubles as our lookup: a form submitter belongs in the CRM anyway.
 */
class GhlClient
{
    private const BASE = 'https://services.leadconnectorhq.com';
    private const VERSION = '2021-07-28';

    public function isConfigured(): bool
    {
        return getenv('ASPENDORA_GHL_TOKEN') && getenv('ASPENDORA_GHL_LOCATION_ID');
    }

    /**
     * Create-or-fetch a contact by email. Returns the contact array
     * (id, firstName, lastName, email, companyName, tags, …) or null.
     */
    public function upsertContactByEmail(string $email): ?array
    {
        $resp = $this->request('POST', '/contacts/upsert', [
            'locationId' => getenv('ASPENDORA_GHL_LOCATION_ID'),
            'email'      => $email,
            'source'     => 'Website (Matomo Identity)',
        ]);
        return $resp['contact'] ?? null;
    }

    public function getContact(string $contactId): ?array
    {
        $resp = $this->request('GET', '/contacts/' . rawurlencode($contactId));
        return $resp['contact'] ?? null;
    }

    /** Adds tags to a contact (existing tags are kept). */
    public function addTags(string $contactId, array $tags): void
    {
        $this->request('POST', '/contacts/' . rawurlencode($contactId) . '/tags', ['tags' => array_values($tags)]);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(self::BASE . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . getenv('ASPENDORA_GHL_TOKEN'),
                'Content-Type: application/json',
                'Version: ' . self::VERSION,
            ],
            CURLOPT_TIMEOUT        => 30,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception('GHL request ' . $method . ' ' . $path . ' failed: ' . $err);
        }
        if ($code === 404) {
            return [];
        }
        if ($code >= 400) {
            throw new Exception('GHL ' . $method . ' ' . $path . ' returned HTTP ' . $code . ': ' . substr($raw, 0, 300));
        }
        return json_decode($raw, true) ?: [];
    }
}
