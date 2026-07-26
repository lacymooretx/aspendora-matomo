<?php

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Exception;

/**
 * Minimal Google Search Console client: OAuth refresh-token flow + Search Analytics query.
 * No SDK — two plain HTTPS calls via curl.
 */
class GscClient
{
    private ?string $accessToken = null;

    public function isConfigured(): bool
    {
        return getenv('ASPENDORA_GSC_CLIENT_ID')
            && getenv('ASPENDORA_GSC_CLIENT_SECRET')
            && getenv('ASPENDORA_GSC_REFRESH_TOKEN');
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }
        $resp = $this->post('https://oauth2.googleapis.com/token', http_build_query([
            'client_id'     => getenv('ASPENDORA_GSC_CLIENT_ID'),
            'client_secret' => getenv('ASPENDORA_GSC_CLIENT_SECRET'),
            'refresh_token' => getenv('ASPENDORA_GSC_REFRESH_TOKEN'),
            'grant_type'    => 'refresh_token',
        ]), ['Content-Type: application/x-www-form-urlencoded']);
        if (empty($resp['access_token'])) {
            throw new Exception('GSC token refresh failed: ' . json_encode($resp));
        }
        return $this->accessToken = $resp['access_token'];
    }

    /**
     * @return array rows of ['keys' => [date, query], 'clicks', 'impressions', 'ctr', 'position']
     */
    public function queryKeywordsByDay(string $property, string $startDate, string $endDate): array
    {
        $token = $this->getAccessToken();
        $url = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            . rawurlencode($property) . '/searchAnalytics/query';
        $all = [];
        $startRow = 0;
        do {
            $resp = $this->post($url, json_encode([
                'startDate'  => $startDate,
                'endDate'    => $endDate,
                'dimensions' => ['date', 'query'],
                'rowLimit'   => 5000,
                'startRow'   => $startRow,
            ]), ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
            if (isset($resp['error'])) {
                throw new Exception('GSC query failed for ' . $property . ': ' . json_encode($resp['error']));
            }
            $rows = $resp['rows'] ?? [];
            $all = array_merge($all, $rows);
            $startRow += count($rows);
        } while (count($rows) === 5000);
        return $all;
    }

    private function post(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception('HTTP request to ' . $url . ' failed: ' . $err);
        }
        return json_decode($raw, true) ?: [];
    }
}
