<?php

namespace Piwik\Plugins\AspendoraSessionRecording;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /** Per-page scroll depth: average max-scroll % and how many reached 25/50/75/100. */
    public function getScrollDepth($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->range($period, $date);
        $heat = Common::prefixTable(AspendoraSessionRecording::TABLE_HEAT);
        $rows = Db::fetchAll(
            "SELECT url,
                    COUNT(*) AS views,
                    ROUND(AVG(scroll_pct)) AS avg_depth,
                    SUM(scroll_pct >= 25) AS r25, SUM(scroll_pct >= 50) AS r50,
                    SUM(scroll_pct >= 75) AS r75, SUM(scroll_pct >= 95) AS r100
             FROM `$heat`
             WHERE idsite = ? AND kind = 'scroll' AND day BETWEEN ? AND ?
             GROUP BY url ORDER BY views DESC LIMIT 200",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'      => $this->path($r['url']),
                'nb_views'   => (int) $r['views'],
                'avg_depth'  => (int) $r['avg_depth'],
                'reached_50' => (int) $r['r50'],
                'reached_75' => (int) $r['r75'],
                'reached_end' => (int) $r['r100'],
            ]);
        }
        return $dt;
    }

    /** Per-page click volume (heatmap detail lives in the admin Click Map page). */
    public function getClickSummary($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->range($period, $date);
        $heat = Common::prefixTable(AspendoraSessionRecording::TABLE_HEAT);
        $rows = Db::fetchAll(
            "SELECT url, COUNT(*) AS clicks, ROUND(AVG(y_px)) AS avg_y
             FROM `$heat`
             WHERE idsite = ? AND kind = 'click' AND day BETWEEN ? AND ?
             GROUP BY url ORDER BY clicks DESC LIMIT 200",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'     => $this->path($r['url']),
                'nb_clicks' => (int) $r['clicks'],
                'avg_y_px'  => (int) $r['avg_y'],
            ]);
        }
        return $dt;
    }

    private function range($period, $date): array
    {
        $p = PeriodFactory::build($period, $date);
        return [$p->getDateStart()->toString('Y-m-d'), $p->getDateEnd()->toString('Y-m-d')];
    }

    private function path(string $url): string
    {
        $p = parse_url($url);
        return ($p['path'] ?? '/') ?: '/';
    }
}
