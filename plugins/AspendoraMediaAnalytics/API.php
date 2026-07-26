<?php

namespace Piwik\Plugins\AspendoraMediaAnalytics;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

/**
 * Media engagement from the event log. The site bundle pushes category "Media" events:
 *   action "play" | "p25" | "p50" | "p75" | "finish" | "time"  → name = media title/src
 *   "time" events carry seconds watched in the event value.
 */
class API extends \Piwik\Plugin\API
{
    public function getMedia($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';
        $llva = Common::prefixTable('log_link_visit_action');
        $la = Common::prefixTable('log_action');
        $rows = Db::fetchAll(
            "SELECT an.name AS media, aa.name AS act, COUNT(*) AS c, SUM(llva.custom_float) AS val
             FROM `$llva` llva
             JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
             JOIN `$la` aa ON aa.idaction = llva.idaction_event_action
             JOIN `$la` an ON an.idaction = llva.idaction_name
             WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ? AND ac.name = 'Media'
             GROUP BY media, act",
            [(int) $idSite, $start, $end]
        );
        $media = [];
        foreach ($rows as $r) {
            $media[$r['media']][$r['act']] = ['c' => (int) $r['c'], 'val' => (float) $r['val']];
        }
        $dt = new DataTable();
        foreach ($media as $name => $m) {
            $plays = $m['play']['c'] ?? 0;
            $finishes = $m['finish']['c'] ?? 0;
            $dt->addRowFromSimpleArray([
                'label'           => $name,
                'nb_plays'        => $plays,
                'nb_p50'          => $m['p50']['c'] ?? 0,
                'nb_finishes'     => $finishes,
                'completion_rate' => $plays ? round(100 * $finishes / $plays, 1) : 0,
                'watch_minutes'   => round(($m['time']['val'] ?? 0) / 60, 1),
            ]);
        }
        $dt->filter('Sort', ['nb_plays', 'desc']);
        return $dt;
    }
}
