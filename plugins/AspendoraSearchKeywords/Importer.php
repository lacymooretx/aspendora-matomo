<?php

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Access;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

class Importer
{
    private GscClient $client;
    private LoggerInterface $logger;
    private BingClient $bing;

    public function __construct(GscClient $client, LoggerInterface $logger, ?BingClient $bing = null)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->bing = $bing ?? new BingClient();
    }

    /**
     * Import the last $days days (ending 3 days ago — GSC data lags) for every
     * site that has a property mapping. Upserts, so re-imports are safe.
     */
    public function importAll(int $days = 5): void
    {
        if (!$this->client->isConfigured()) {
            $this->logger->warning('AspendoraSearchKeywords: GSC env credentials not set; skipping import');
            return;
        }
        $end = date('Y-m-d', strtotime('-3 days'));
        $start = date('Y-m-d', strtotime("-" . ($days + 2) . " days"));
        $siteIds = Access::doAsSuperUser(function () {
            return SitesManagerAPI::getInstance()->getAllSitesId();
        });
        foreach ($siteIds as $siteId) {
            $idSite = (int) $siteId;
            $property = AspendoraSearchKeywords::getPropertyForSite($idSite);
            if (!$property) {
                continue;
            }
            try {
                $rows = $this->client->queryKeywordsByDay($property, $start, $end);
                $this->store($idSite, $rows);
                $this->logger->info('AspendoraSearchKeywords: imported {n} rows for site {s} ({p}, {a}..{b})', [
                    'n' => count($rows), 's' => $idSite, 'p' => $property, 'a' => $start, 'b' => $end,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('AspendoraSearchKeywords: import failed for site {s}: {m}', [
                    's' => $idSite, 'm' => $e->getMessage(),
                ]);
            }
        }
    }

    private function store(int $idSite, array $rows): void
    {
        $table = Common::prefixTable(AspendoraSearchKeywords::TABLE);
        foreach (array_chunk($rows, 200) as $chunk) {
            $values = [];
            $binds = [];
            foreach ($chunk as $r) {
                [$day, $query] = $r['keys'];
                $values[] = '(?,?,?,?,?,?)';
                array_push($binds, $idSite, $day, mb_substr($query, 0, 255),
                    (int) $r['clicks'], (int) $r['impressions'], round((float) $r['position'], 2));
            }
            Db::query(
                "INSERT INTO `$table` (idsite, day, keyword, clicks, impressions, position) VALUES "
                . implode(',', $values)
                . " ON DUPLICATE KEY UPDATE clicks=VALUES(clicks), impressions=VALUES(impressions), position=VALUES(position)",
                $binds
            );
        }
    }

    /**
     * Import Bing Webmaster data for every site in ASPENDORA_BING_SITE_MAP.
     * Bing returns its full history on every call (~6 months of weekly query/page rows,
     * ~6 months of daily crawl rows), so each run upserts everything — re-runs are safe.
     * Crawl issues are a point-in-time snapshot and are replaced wholesale per site.
     * Each site and each dataset fails independently.
     */
    public function importBing(): void
    {
        if (!$this->bing->isConfigured()) {
            $this->logger->warning('AspendoraSearchKeywords: ASPENDORA_BING_API_KEY not set; skipping Bing import');
            return;
        }
        $siteIds = Access::doAsSuperUser(function () {
            return SitesManagerAPI::getInstance()->getAllSitesId();
        });
        $mapped = 0;
        foreach ($siteIds as $siteId) {
            $idSite = (int) $siteId;
            $siteUrl = AspendoraSearchKeywords::getBingSiteForSite($idSite);
            if (!$siteUrl) {
                continue;
            }
            $mapped++;
            $steps = [
                'keywords' => function () use ($idSite, $siteUrl) {
                    return $this->storeBingWeekly(AspendoraSearchKeywords::BING_KEYWORDS_TABLE, $idSite,
                        $this->bing->getQueryStats($siteUrl), 'keyword');
                },
                'pages' => function () use ($idSite, $siteUrl) {
                    return $this->storeBingWeekly(AspendoraSearchKeywords::BING_PAGES_TABLE, $idSite,
                        $this->bing->getPageStats($siteUrl), 'page');
                },
                'crawl' => function () use ($idSite, $siteUrl) {
                    return $this->storeBingCrawl($idSite, $this->bing->getCrawlStats($siteUrl));
                },
                'crawl issues' => function () use ($idSite, $siteUrl) {
                    return $this->replaceBingIssues($idSite, $this->bing->getCrawlIssues($siteUrl));
                },
            ];
            foreach ($steps as $name => $step) {
                try {
                    $n = $step();
                    $this->logger->info('AspendoraSearchKeywords: Bing {d}: stored {n} rows for site {s} ({u})', [
                        'd' => $name, 'n' => $n, 's' => $idSite, 'u' => $siteUrl,
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('AspendoraSearchKeywords: Bing {d} import failed for site {s}: {m}', [
                        'd' => $name, 's' => $idSite, 'm' => $e->getMessage(),
                    ]);
                }
            }
        }
        if ($mapped === 0) {
            $this->logger->warning('AspendoraSearchKeywords: ASPENDORA_BING_SITE_MAP maps no existing sites; nothing imported from Bing');
        }
    }

    /** Upsert weekly query/page rows. Duplicates within a batch are merged first. */
    private function storeBingWeekly(string $tableConst, int $idSite, array $rows, string $labelKey): int
    {
        $isPage = $labelKey === 'page';
        $agg = [];
        foreach ($rows as $r) {
            $label = $isPage ? mb_substr($r['page'], 0, 2048) : mb_substr($r['keyword'], 0, 255);
            $k = $r['week'] . "\0" . ($isPage ? $label : mb_strtolower($label));
            if (!isset($agg[$k])) {
                $agg[$k] = ['week' => $r['week'], 'label' => $label, 'clicks' => 0, 'impressions' => 0, 'pw' => 0.0, 'pwn' => 0];
            }
            $agg[$k]['clicks'] += $r['clicks'];
            $agg[$k]['impressions'] += $r['impressions'];
            $agg[$k]['pw'] += $r['position'] * max(1, $r['impressions']);
            $agg[$k]['pwn'] += max(1, $r['impressions']);
        }
        $table = Common::prefixTable($tableConst);
        foreach (array_chunk(array_values($agg), 200) as $chunk) {
            $values = [];
            $binds = [];
            foreach ($chunk as $r) {
                $pos = round($r['pw'] / $r['pwn'], 2);
                if ($isPage) {
                    $values[] = '(?,?,?,?,?,?,?)';
                    array_push($binds, $idSite, $r['week'], sha1($r['label']), $r['label'],
                        $r['clicks'], $r['impressions'], $pos);
                } else {
                    $values[] = '(?,?,?,?,?,?)';
                    array_push($binds, $idSite, $r['week'], $r['label'], $r['clicks'], $r['impressions'], $pos);
                }
            }
            $cols = $isPage
                ? 'idsite, week, url_hash, url, clicks, impressions, position'
                : 'idsite, week, keyword, clicks, impressions, position';
            Db::query(
                "INSERT INTO `$table` ($cols) VALUES " . implode(',', $values)
                . " ON DUPLICATE KEY UPDATE clicks=VALUES(clicks), impressions=VALUES(impressions), position=VALUES(position)",
                $binds
            );
        }
        return count($agg);
    }

    private const CRAWL_COLS = ['crawled_pages', 'in_index', 'in_links', 'code_2xx', 'code_301', 'code_302',
        'code_4xx', 'code_5xx', 'all_other_codes', 'crawl_errors', 'blocked_by_robots', 'connection_timeout',
        'dns_failures', 'contains_malware'];

    private function storeBingCrawl(int $idSite, array $rows): int
    {
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['day']] = $r; // last row for a day wins
        }
        $table = Common::prefixTable(AspendoraSearchKeywords::BING_CRAWL_TABLE);
        $cols = self::CRAWL_COLS;
        $placeholder = '(' . implode(',', array_fill(0, count($cols) + 2, '?')) . ')';
        $update = implode(', ', array_map(function ($c) {
            return "`$c`=VALUES(`$c`)";
        }, $cols));
        foreach (array_chunk(array_values($byDay), 200) as $chunk) {
            $values = [];
            $binds = [];
            foreach ($chunk as $r) {
                $values[] = $placeholder;
                $binds[] = $idSite;
                $binds[] = $r['day'];
                foreach ($cols as $c) {
                    $binds[] = (int) $r[$c];
                }
            }
            Db::query(
                "INSERT INTO `$table` (idsite, day, `" . implode('`, `', $cols) . "`) VALUES "
                . implode(',', $values) . " ON DUPLICATE KEY UPDATE $update",
                $binds
            );
        }
        return count($byDay);
    }

    /** Replace the site's crawl-issue snapshot. Only runs after a successful fetch. */
    private function replaceBingIssues(int $idSite, array $rows): int
    {
        $table = Common::prefixTable(AspendoraSearchKeywords::BING_ISSUES_TABLE);
        $now = gmdate('Y-m-d H:i:s');
        $byHash = [];
        foreach ($rows as $r) {
            $url = mb_substr($r['url'], 0, 2048);
            $byHash[sha1($url)] = ['url' => $url] + $r;
        }
        $db = Db::get();
        $db->beginTransaction();
        try {
            Db::query("DELETE FROM `$table` WHERE idsite = ?", [$idSite]);
            foreach (array_chunk($byHash, 200, true) as $chunk) {
                $values = [];
                $binds = [];
                foreach ($chunk as $hash => $r) {
                    $values[] = '(?,?,?,?,?,?,?)';
                    array_push($binds, $idSite, $hash, $r['url'], $r['http_code'], $r['in_links'], $r['issues'], $now);
                }
                Db::query(
                    "INSERT INTO `$table` (idsite, url_hash, url, http_code, in_links, issues, fetched_at) VALUES "
                    . implode(',', $values),
                    $binds
                );
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return count($byHash);
    }
}
