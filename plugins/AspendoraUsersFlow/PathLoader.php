<?php

namespace Piwik\Plugins\AspendoraUsersFlow;

use Piwik\Common;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;

/**
 * Loads per-visit pageview sequences (URL paths, in order) from the action log.
 * Bounded: at most 20k pageview rows per query — far above this site's traffic.
 */
class PathLoader
{
    /** @return array<int, string[]> idvisit => ordered list of page paths */
    public function loadVisitPaths(int $idSite, $period, $date, int $maxSteps): array
    {
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';
        $llva = Common::prefixTable('log_link_visit_action');
        $la = Common::prefixTable('log_action');
        $rows = Db::fetchAll(
            "SELECT llva.idvisit, la.name AS url
             FROM `$llva` llva
             JOIN `$la` la ON la.idaction = llva.idaction_url
             WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ?
               AND llva.idaction_url IS NOT NULL
               AND llva.idaction_event_category IS NULL
             ORDER BY llva.idvisit, llva.server_time
             LIMIT 20000",
            [$idSite, $start, $end]
        );
        $paths = [];
        foreach ($rows as $r) {
            $vid = (int) $r['idvisit'];
            if (isset($paths[$vid]) && count($paths[$vid]) >= $maxSteps) {
                continue;
            }
            $paths[$vid][] = self::toPath($r['url']);
        }
        return $paths;
    }

    /** log_action URL names have no protocol, e.g. "www.aspendora.com/blog/?x=1" */
    public static function toPath(string $urlName): string
    {
        $slash = strpos($urlName, '/');
        $path = $slash === false ? '/' : substr($urlName, $slash);
        $q = strpos($path, '?');
        if ($q !== false) {
            $path = substr($path, 0, $q);
        }
        return $path === '' ? '/' : $path;
    }
}
