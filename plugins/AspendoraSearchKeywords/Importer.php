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

    public function __construct(GscClient $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
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
}
