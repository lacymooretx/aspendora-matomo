<?php

namespace Piwik\Plugins\AspendoraFunnels;

use Piwik\DataTable;
use Piwik\Piwik;
use Piwik\Plugins\AspendoraUsersFlow\PathLoader;

/**
 * Funnel computation: a visit reaches step N when its pageview sequence
 * matches steps 1..N in order (later pages only). Reuses UsersFlow's
 * PathLoader (bounded 20k pageview rows per query).
 */
class API extends \Piwik\Plugin\API
{
    public function getFunnels($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $paths = (new PathLoader())->loadVisitPaths($idSite, $period, $date, 50);
        $dt = new DataTable();
        foreach (AspendoraFunnels::definitions($idSite) as $def) {
            $steps = array_values($def['steps']);
            $reached = array_fill(0, count($steps), 0);
            foreach ($paths as $pages) {
                $stepIdx = 0;
                foreach ($pages as $page) {
                    if ($stepIdx >= count($steps)) {
                        break;
                    }
                    $pattern = '~' . str_replace('~', '\~', (string) ($steps[$stepIdx]['pattern'] ?? '.')) . '~i';
                    if (@preg_match($pattern, $page) === 1) {
                        $reached[$stepIdx]++;
                        $stepIdx++;
                    }
                }
            }
            $entered = $reached[0] ?: 0;
            foreach ($steps as $i => $step) {
                $n = $reached[$i];
                $prev = $i === 0 ? $entered : $reached[$i - 1];
                $dt->addRowFromSimpleArray([
                    'label'           => $def['name'] . ' — ' . ($i + 1) . '. ' . ($step['label'] ?? $step['pattern'] ?? ''),
                    'nb_visits'       => $n,
                    'step_rate'       => $prev ? round(100 * $n / $prev, 1) : 0,
                    'funnel_rate'     => $entered ? round(100 * $n / $entered, 1) : 0,
                    'nb_dropped'      => max(0, $prev - $n),
                ]);
            }
        }
        return $dt;
    }
}
