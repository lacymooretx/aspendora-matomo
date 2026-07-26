<?php

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

/**
 * API for GSC keyword data. Aggregates the raw per-day rows over the requested period.
 * Position is impression-weighted; CTR is recomputed from the sums.
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

    private function periodToRange($period, $date): array
    {
        $periodObj = PeriodFactory::build($period, $date);
        return [
            $periodObj->getDateStart()->toString('Y-m-d'),
            $periodObj->getDateEnd()->toString('Y-m-d'),
        ];
    }
}
