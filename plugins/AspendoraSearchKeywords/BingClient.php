<?php

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Exception;

/**
 * Minimal Bing Webmaster Tools client (JSON API, API-key auth). No SDK — plain curl GETs.
 *
 * Base: https://ssl.bing.com/webmaster/api.svc/json/<Method>?apikey=<KEY>&siteUrl=<url>
 * Responses are wrapped in {"d": [...]}; dates are "/Date(epochMs[+-hhmm])/".
 * siteUrl must match the verified Bing property exactly (e.g. "https://www.aspendora.com/").
 *
 * The API key is never included in exception messages or logs.
 */
class BingClient
{
    private const BASE = 'https://ssl.bing.com/webmaster/api.svc/json/';

    public function isConfigured(): bool
    {
        return (bool) self::apiKey();
    }

    private static function apiKey(): string
    {
        return (string) (getenv('ASPENDORA_BING_API_KEY') ?: '');
    }

    /**
     * Weekly keyword stats (~6+ months).
     * @return array rows of ['week' => Y-m-d, 'keyword', 'clicks', 'impressions', 'position']
     */
    public function getQueryStats(string $siteUrl): array
    {
        return $this->mapQueryRows($this->call('GetQueryStats', $siteUrl), 'keyword');
    }

    /**
     * Weekly per-page stats (same shape as GetQueryStats, Query = page URL).
     * @return array rows of ['week' => Y-m-d, 'page', 'clicks', 'impressions', 'position']
     */
    public function getPageStats(string $siteUrl): array
    {
        return $this->mapQueryRows($this->call('GetPageStats', $siteUrl), 'page');
    }

    /**
     * Daily crawl counters.
     * @return array rows of ['day' => Y-m-d, 'crawled_pages', 'in_index', ...]
     */
    public function getCrawlStats(string $siteUrl): array
    {
        $out = [];
        foreach ($this->call('GetCrawlStats', $siteUrl) as $r) {
            $out[] = [
                'day'                => self::parseDate($r['Date'] ?? ''),
                'crawled_pages'      => (int) ($r['CrawledPages'] ?? 0),
                'in_index'           => (int) ($r['InIndex'] ?? 0),
                'in_links'           => (int) ($r['InLinks'] ?? 0),
                'code_2xx'           => (int) ($r['Code2xx'] ?? 0),
                'code_301'           => (int) ($r['Code301'] ?? 0),
                'code_302'           => (int) ($r['Code302'] ?? 0),
                'code_4xx'           => (int) ($r['Code4xx'] ?? 0),
                'code_5xx'           => (int) ($r['Code5xx'] ?? 0),
                'all_other_codes'    => (int) ($r['AllOtherCodes'] ?? 0),
                'crawl_errors'       => (int) ($r['CrawlErrors'] ?? 0),
                'blocked_by_robots'  => (int) ($r['BlockedByRobotsTxt'] ?? 0),
                'connection_timeout' => (int) ($r['ConnectionTimeout'] ?? 0),
                'dns_failures'       => (int) ($r['DnsFailures'] ?? 0),
                'contains_malware'   => (int) ($r['ContainsMalware'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Current crawl-issue list (a snapshot, not a time series).
     * @return array rows of ['url', 'http_code', 'in_links', 'issues' (bitflag)]
     */
    public function getCrawlIssues(string $siteUrl): array
    {
        $out = [];
        foreach ($this->call('GetCrawlIssues', $siteUrl) as $r) {
            if (empty($r['Url'])) {
                continue;
            }
            $out[] = [
                'url'       => (string) $r['Url'],
                'http_code' => (int) ($r['HttpCode'] ?? 0),
                'in_links'  => (int) ($r['InLinks'] ?? 0),
                'issues'    => (int) ($r['Issues'] ?? 0),
            ];
        }
        return $out;
    }

    /** "/Date(1750982400000)/" or "/Date(1750982400000-0700)/" → "2025-06-27" (UTC). */
    public static function parseDate(string $raw): string
    {
        if (!preg_match('~/?Date\((-?\d+)(?:[+-]\d{4})?\)/?~', $raw, $m)) {
            throw new Exception('Bing: unparseable date ' . json_encode($raw));
        }
        return gmdate('Y-m-d', intdiv((int) $m[1], 1000));
    }

    private function mapQueryRows(array $rows, string $labelKey): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (!isset($r['Query']) || $r['Query'] === '') {
                continue;
            }
            $pos = (float) ($r['AvgImpressionPosition'] ?? 0);
            $out[] = [
                'week'        => self::parseDate($r['Date'] ?? ''),
                $labelKey     => (string) $r['Query'],
                'clicks'      => (int) ($r['Clicks'] ?? 0),
                'impressions' => (int) ($r['Impressions'] ?? 0),
                'position'    => $pos < 0 ? 0.0 : $pos,
            ];
        }
        return $out;
    }

    /** GET a JSON method; returns the unwrapped "d" array. Throws on HTTP or API error. */
    private function call(string $method, string $siteUrl): array
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new Exception('Bing: ASPENDORA_BING_API_KEY not set');
        }
        $url = self::BASE . $method . '?apikey=' . rawurlencode($key) . '&siteUrl=' . rawurlencode($siteUrl);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ctx = 'Bing ' . $method . ' for ' . $siteUrl;
        if ($raw === false) {
            throw new Exception($ctx . ': HTTP request failed: ' . $err);
        }
        $json = json_decode($raw, true);
        if ($code !== 200) {
            $msg = is_array($json) ? ($json['Message'] ?? json_encode($json)) : mb_substr((string) $raw, 0, 300);
            throw new Exception($ctx . ': HTTP ' . $code . ': ' . $msg);
        }
        if (!is_array($json)) {
            throw new Exception($ctx . ': invalid JSON response');
        }
        if (isset($json['ErrorCode']) || (isset($json['Message']) && !array_key_exists('d', $json))) {
            throw new Exception($ctx . ': API error ' . ($json['ErrorCode'] ?? '') . ': ' . ($json['Message'] ?? ''));
        }
        if (!array_key_exists('d', $json)) {
            throw new Exception($ctx . ': response missing "d"');
        }
        return is_array($json['d']) ? $json['d'] : [];
    }
}
