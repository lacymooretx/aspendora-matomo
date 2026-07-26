<?php

namespace Piwik\Plugins\AspendoraWebVitals;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Access;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

class Collector
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function collectAll(): void
    {
        $key = getenv('ASPENDORA_PSI_API_KEY');
        if (!$key) {
            $this->logger->warning('AspendoraWebVitals: ASPENDORA_PSI_API_KEY not set; skipping');
            return;
        }
        $day = date('Y-m-d');
        $siteIds = Access::doAsSuperUser(function () {
            return SitesManagerAPI::getInstance()->getAllSitesId();
        });
        foreach ($siteIds as $siteId) {
            $idSite = (int) $siteId;
            foreach (AspendoraWebVitals::getUrlsForSite($idSite) as $url) {
                foreach (['mobile', 'desktop'] as $strategy) {
                    try {
                        $m = $this->runPsi($key, $url, $strategy);
                        $this->store($idSite, $day, $url, $strategy, $m);
                        $this->logger->info('AspendoraWebVitals: {u} [{s}] score {p}', [
                            'u' => $url, 's' => $strategy, 'p' => $m['perf_score'],
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('AspendoraWebVitals: {u} [{s}] failed: {m}', [
                            'u' => $url, 's' => $strategy, 'm' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    private function runPsi(string $key, string $url, string $strategy): array
    {
        $api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?'
            . http_build_query(['url' => $url, 'strategy' => $strategy, 'key' => $key, 'category' => 'performance']);
        $ch = curl_init($api);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new \Exception('PSI request failed: ' . $err);
        }
        $d = json_decode($raw, true);
        if (isset($d['error'])) {
            throw new \Exception('PSI error: ' . ($d['error']['message'] ?? 'unknown'));
        }
        $audits = $d['lighthouseResult']['audits'] ?? [];
        $num = fn($id) => (float) ($audits[$id]['numericValue'] ?? 0);
        return [
            'perf_score' => (int) round(100 * ($d['lighthouseResult']['categories']['performance']['score'] ?? 0)),
            'lcp_ms'     => (int) round($num('largest-contentful-paint')),
            'cls_x1000'  => (int) round(1000 * $num('cumulative-layout-shift')),
            'tbt_ms'     => (int) round($num('total-blocking-time')),
            'fcp_ms'     => (int) round($num('first-contentful-paint')),
            'si_ms'      => (int) round($num('speed-index')),
        ];
    }

    private function store(int $idSite, string $day, string $url, string $strategy, array $m): void
    {
        $table = Common::prefixTable(AspendoraWebVitals::TABLE);
        Db::query(
            "INSERT INTO `$table` (idsite, day, url, strategy, perf_score, lcp_ms, cls_x1000, tbt_ms, fcp_ms, si_ms)
             VALUES (?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE perf_score=VALUES(perf_score), lcp_ms=VALUES(lcp_ms),
               cls_x1000=VALUES(cls_x1000), tbt_ms=VALUES(tbt_ms), fcp_ms=VALUES(fcp_ms), si_ms=VALUES(si_ms)",
            [$idSite, $day, mb_substr($url, 0, 500), $strategy,
             $m['perf_score'], $m['lcp_ms'], $m['cls_x1000'], $m['tbt_ms'], $m['fcp_ms'], $m['si_ms']]
        );
    }
}
