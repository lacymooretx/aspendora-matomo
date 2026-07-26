<?php

namespace Piwik\Plugins\AspendoraWebVitals;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /**
     * Latest scores per URL+strategy within the period (most recent day wins).
     */
    public function getWebVitals($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d');
        $end = $periodObj->getDateEnd()->toString('Y-m-d');
        $table = Common::prefixTable(AspendoraWebVitals::TABLE);
        $rows = Db::fetchAll(
            "SELECT t.url, t.strategy, t.perf_score, t.lcp_ms, t.cls_x1000, t.tbt_ms, t.fcp_ms, t.si_ms
             FROM `$table` t
             JOIN (SELECT url, strategy, MAX(day) AS maxday FROM `$table`
                   WHERE idsite = ? AND day BETWEEN ? AND ? GROUP BY url, strategy) latest
               ON latest.url = t.url AND latest.strategy = t.strategy AND latest.maxday = t.day
             WHERE t.idsite = ?
             ORDER BY t.strategy, t.perf_score ASC",
            [(int) $idSite, $start, $end, (int) $idSite]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $path = parse_url($r['url'], PHP_URL_PATH) ?: '/';
            $dt->addRowFromSimpleArray([
                'label'      => $path . ' [' . $r['strategy'] . ']',
                'perf_score' => (int) $r['perf_score'],
                'lcp_s'      => round($r['lcp_ms'] / 1000, 2),
                'cls'        => round($r['cls_x1000'] / 1000, 3),
                'tbt_ms'     => (int) $r['tbt_ms'],
                'fcp_s'      => round($r['fcp_ms'] / 1000, 2),
                'si_s'       => round($r['si_ms'] / 1000, 2),
            ]);
        }
        return $dt;
    }

    /** Perf score per day for one URL/strategy or the site-wide mobile average — evolution data. */
    public function getScoreEvolution($idSite, $period, $date, $strategy = 'mobile')
    {
        Piwik::checkUserHasViewAccess($idSite);
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d');
        $end = $periodObj->getDateEnd()->toString('Y-m-d');
        $table = Common::prefixTable(AspendoraWebVitals::TABLE);
        $rows = Db::fetchAll(
            "SELECT day, ROUND(AVG(perf_score)) AS perf_score, ROUND(AVG(lcp_ms)/1000, 2) AS lcp_s
             FROM `$table` WHERE idsite = ? AND strategy = ? AND day BETWEEN ? AND ?
             GROUP BY day ORDER BY day",
            [(int) $idSite, $strategy === 'desktop' ? 'desktop' : 'mobile', $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'      => $r['day'],
                'perf_score' => (int) $r['perf_score'],
                'lcp_s'      => (float) $r['lcp_s'],
            ]);
        }
        return $dt;
    }
}
