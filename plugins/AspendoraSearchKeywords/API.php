<?php

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

/**
 * API for GSC (daily) and Bing Webmaster (weekly) keyword data. Aggregates the raw rows over
 * the requested period. Position is impression-weighted; CTR is recomputed from the sums.
 *
 * Bing note: Bing only publishes keyword/page stats in weekly buckets. A Bing row is included
 * when its bucket date (as returned by Bing — currently a Friday) falls inside the period, so a
 * day period (or any range that contains no bucket date) returns an empty table. Use week,
 * month or longer periods for Bing.
 */
class API extends \Piwik\Plugin\API
{
    public function getKeywords($idSite, $period, $date, $flat = false)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        $table = Common::prefixTable(AspendoraSearchKeywords::TABLE);
        $rows = Db::fetchAll(
            "SELECT keyword,
                    SUM(clicks) AS nb_clicks,
                    SUM(impressions) AS nb_impressions,
                    ROUND(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 1) AS avg_position
             FROM `$table`
             WHERE idsite = ? AND day BETWEEN ? AND ?
             GROUP BY keyword
             ORDER BY nb_clicks DESC, nb_impressions DESC
             LIMIT 1000",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $clicks = (int) $r['nb_clicks'];
            $impr = (int) $r['nb_impressions'];
            $dt->addRowFromSimpleArray([
                'label'          => $r['keyword'],
                'nb_clicks'      => $clicks,
                'nb_impressions' => $impr,
                'ctr_pct'        => $impr ? round(100 * $clicks / $impr, 2) : 0,
                'avg_position'   => $r['avg_position'] === null ? 0 : (float) $r['avg_position'],
            ]);
        }
        return $dt;
    }

    /** Totals per day — used for the evolution graph. */
    public function getKeywordsEvolution($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        $table = Common::prefixTable(AspendoraSearchKeywords::TABLE);
        $rows = Db::fetchAll(
            "SELECT day, SUM(clicks) AS nb_clicks, SUM(impressions) AS nb_impressions
             FROM `$table` WHERE idsite = ? AND day BETWEEN ? AND ? GROUP BY day ORDER BY day",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'          => $r['day'],
                'nb_clicks'      => (int) $r['nb_clicks'],
                'nb_impressions' => (int) $r['nb_impressions'],
            ]);
        }
        return $dt;
    }

    /**
     * Bing keywords for the period (weekly granularity — see class doc; short periods may be empty).
     * Same columns as getKeywords: label, nb_clicks, nb_impressions, ctr_pct, avg_position.
     */
    public function getBingKeywords($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        return $this->bingWeeklyTable(AspendoraSearchKeywords::BING_KEYWORDS_TABLE, 'keyword', $idSite, $start, $end);
    }

    /** Bing top pages for the period (weekly granularity). label = page URL. */
    public function getBingPages($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        return $this->bingWeeklyTable(AspendoraSearchKeywords::BING_PAGES_TABLE, 'url', $idSite, $start, $end);
    }

    /** Bing keyword totals per weekly bucket (label = bucket date). */
    public function getBingKeywordsEvolution($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        $table = Common::prefixTable(AspendoraSearchKeywords::BING_KEYWORDS_TABLE);
        $rows = Db::fetchAll(
            "SELECT week, SUM(clicks) AS nb_clicks, SUM(impressions) AS nb_impressions
             FROM `$table` WHERE idsite = ? AND week BETWEEN ? AND ? GROUP BY week ORDER BY week",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'          => $r['week'],
                'nb_clicks'      => (int) $r['nb_clicks'],
                'nb_impressions' => (int) $r['nb_impressions'],
            ]);
        }
        return $dt;
    }

    /** Bing daily crawl counters within the period; one row per day, label = date (Y-m-d). */
    public function getBingCrawlStats($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        $table = Common::prefixTable(AspendoraSearchKeywords::BING_CRAWL_TABLE);
        $rows = Db::fetchAll(
            "SELECT * FROM `$table` WHERE idsite = ? AND day BETWEEN ? AND ? ORDER BY day DESC",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $row = ['label' => $r['day']];
            foreach ($r as $col => $val) {
                if ($col !== 'idsite' && $col !== 'day') {
                    $row[$col] = (int) $val;
                }
            }
            $dt->addRowFromSimpleArray($row);
        }
        return $dt;
    }

    /**
     * Latest Bing crawl-issue snapshot (not period-based). label = URL.
     * issues_text decodes Bing's CrawlIssues bitflag.
     */
    public function getBingCrawlIssues($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $table = Common::prefixTable(AspendoraSearchKeywords::BING_ISSUES_TABLE);
        $rows = Db::fetchAll(
            "SELECT url, http_code, in_links, issues, fetched_at FROM `$table`
             WHERE idsite = ? ORDER BY in_links DESC, url LIMIT 5000",
            [(int) $idSite]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'       => $r['url'],
                'http_code'   => (int) $r['http_code'],
                'in_links'    => (int) $r['in_links'],
                'issues'      => (int) $r['issues'],
                'issues_text' => self::decodeBingIssues((int) $r['issues']),
                'fetched_at'  => $r['fetched_at'],
            ]);
        }
        return $dt;
    }

    /** Bing Webmaster CrawlIssues flag enumeration. */
    private const BING_ISSUE_FLAGS = [
        1   => '301',
        2   => '302',
        4   => '4xx',
        8   => '5xx',
        16  => 'Blocked by robots.txt',
        32  => 'Contains malware',
        64  => 'Important URL blocked by robots.txt',
        128 => 'DNS error',
        256 => 'Timeout',
    ];

    private static function decodeBingIssues(int $flags): string
    {
        $out = [];
        foreach (self::BING_ISSUE_FLAGS as $bit => $name) {
            if ($flags & $bit) {
                $out[] = $name;
                $flags &= ~$bit;
            }
        }
        if ($flags) {
            $out[] = 'other (' . $flags . ')';
        }
        return implode(', ', $out);
    }

    private function bingWeeklyTable(string $tableConst, string $labelCol, $idSite, string $start, string $end): DataTable
    {
        $table = Common::prefixTable($tableConst);
        $rows = Db::fetchAll(
            "SELECT `$labelCol` AS label,
                    SUM(clicks) AS nb_clicks,
                    SUM(impressions) AS nb_impressions,
                    ROUND(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 1) AS avg_position
             FROM `$table`
             WHERE idsite = ? AND week BETWEEN ? AND ?
             GROUP BY `$labelCol`
             ORDER BY nb_clicks DESC, nb_impressions DESC
             LIMIT 1000",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $clicks = (int) $r['nb_clicks'];
            $impr = (int) $r['nb_impressions'];
            $dt->addRowFromSimpleArray([
                'label'          => $r['label'],
                'nb_clicks'      => $clicks,
                'nb_impressions' => $impr,
                'ctr_pct'        => $impr ? round(100 * $clicks / $impr, 2) : 0,
                'avg_position'   => $r['avg_position'] === null ? 0 : (float) $r['avg_position'],
            ]);
        }
        return $dt;
    }

    private function periodToRange($period, $date): array
    {
        $periodObj = PeriodFactory::build($period, $date);
        return [
            $periodObj->getDateStart()->toString('Y-m-d'),
            $periodObj->getDateEnd()->toString('Y-m-d'),
        ];
    }
}
