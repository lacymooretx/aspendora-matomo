<?php

namespace Piwik\Plugins\AspendoraCompanies;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log\LoggerInterface;

/**
 * Nightly: resolve unresolved network prefixes to organizations.
 * IPinfo (if ASPENDORA_IPINFO_TOKEN is set) is preferred; rDNS on <prefix>.1
 * is the free fallback for IPv4. Orgs matching the ISP/hosting keyword list
 * are flagged is_isp and excluded from the Companies report.
 */
class Resolver
{
    private const RESOLVE_CAP = 200;

    /** Residential carriers, mobile networks, and hosting/cloud — not companies visiting. */
    private const ISP_KEYWORDS = [
        'comcast', 'xfinity', 'verizon', 'at&t', 'at-t', 'spectrum', 'charter', 'cox comm',
        'centurylink', 'lumen', 't-mobile', 'tmobile', 'sprint', 'frontier', 'windstream',
        'altice', 'optimum', 'suddenlink', 'mediacom', 'sparklight', 'cable one', 'google fiber',
        'starlink', 'spacex', 'hughes', 'viasat', 'cellco', 'us cellular', 'uscellular',
        'cricket', 'metropcs', 'boost mobile', 'dish', 'brightspeed', 'ziply', 'astound', 'rcn',
        'wow!', 'wideopenwest', 'earthlink', 'telefonica', 'vodafone', 'telecom', 'telekom',
        'cellular', 'wireless', 'broadband', 'residential', 'dsl', 'cablevision', 'fibernet',
        'internet service', ' isp', 'telco', 'communications inc',
        'amazon', 'aws', 'azure', 'microsoft corporation', 'google llc', 'google cloud',
        'cloudflare', 'digitalocean', 'linode', 'akamai', 'vultr', 'ovh', 'hetzner',
        'oracle cloud', 'fastly', 'apple inc', 'icloud', 'hosting', 'colocation', 'data center',
        'datacenter', 'server', 'vpn', 'proxy',
    ];

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function run(): void
    {
        $table = Common::prefixTable(AspendoraCompanies::TABLE);
        $logVisit = Common::prefixTable('log_visit');
        // Register newly seen prefixes
        Db::query(
            "INSERT IGNORE INTO `$table` (prefix, first_seen)
             SELECT DISTINCT aspendora_org_prefix, NOW() FROM `$logVisit`
             WHERE aspendora_org_prefix IS NOT NULL AND aspendora_org_prefix != ''
               AND visit_last_action_time > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $rows = Db::fetchAll(
            "SELECT prefix FROM `$table` WHERE resolved_at IS NULL ORDER BY first_seen DESC LIMIT " . self::RESOLVE_CAP
        );
        $resolved = 0;
        foreach ($rows as $r) {
            $prefix = $r['prefix'];
            $ip = strpos($prefix, ':') === false ? $prefix . '.1' : $prefix . '::1';
            $org = null;
            $rdns = null;
            try {
                $org = $this->ipinfoOrg($ip);
            } catch (\Exception $e) {
                $this->logger->warning('AspendoraCompanies: ipinfo failed for {p}: {m}', ['p' => $prefix, 'm' => $e->getMessage()]);
            }
            if (strpos($prefix, ':') === false) {
                $host = @gethostbyaddr($ip);
                if ($host && $host !== $ip) {
                    $rdns = substr($host, 0, 190);
                }
            }
            if ($org === null && $rdns !== null) {
                // derive an org-ish label from the rDNS domain (drop host part + TLD noise)
                $bits = explode('.', $rdns);
                $n = count($bits);
                if ($n >= 2) {
                    $org = $bits[$n - 2] . '.' . $bits[$n - 1];
                }
            }
            $isIsp = $this->looksLikeIsp(($org ?? '') . ' ' . ($rdns ?? '')) ? 1 : 0;
            Db::query(
                "UPDATE `$table` SET org = ?, rdns = ?, is_isp = ?, resolved_at = NOW() WHERE prefix = ?",
                [$org !== null ? substr($org, 0, 190) : null, $rdns, $isIsp, $prefix]
            );
            $resolved++;
        }
        if ($resolved) {
            $this->logger->info('AspendoraCompanies: resolved {n} prefixes', ['n' => $resolved]);
        }
    }

    /** ipinfo.io org lookup ("AS15169 Google LLC" → "Google LLC"); null when unconfigured/unknown. */
    private function ipinfoOrg(string $ip): ?string
    {
        $token = getenv('ASPENDORA_IPINFO_TOKEN');
        if (!$token) {
            return null;
        }
        $ch = curl_init('https://ipinfo.io/' . rawurlencode($ip) . '/json?token=' . rawurlencode($token));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true) ?: [];
        $org = $data['org'] ?? '';
        return $org !== '' ? trim(preg_replace('/^AS\d+\s+/', '', $org)) : null;
    }

    private function looksLikeIsp(string $label): bool
    {
        $label = strtolower($label);
        foreach (self::ISP_KEYWORDS as $kw) {
            if (strpos($label, $kw) !== false) {
                return true;
            }
        }
        return false;
    }
}
