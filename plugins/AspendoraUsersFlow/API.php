<?php

namespace Piwik\Plugins\AspendoraUsersFlow;

use Piwik\DataTable;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /** Most common visit paths (first $steps pageviews), e.g. "/ → /compliance/ → /contact-us/". */
    public function getTopPaths($idSite, $period, $date, $steps = 4)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $steps = min(max((int) $steps, 2), 10);
        $paths = (new PathLoader())->loadVisitPaths((int) $idSite, $period, $date, $steps);
        $counts = [];
        foreach ($paths as $pages) {
            $key = implode(' → ', $pages);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $dt = new DataTable();
        foreach (array_slice($counts, 0, 200, true) as $path => $n) {
            $dt->addRowFromSimpleArray([
                'label'     => $path,
                'nb_visits' => $n,
                'nb_steps'  => substr_count($path, ' → ') + 1,
            ]);
        }
        return $dt;
    }

    /** Per-step breakdown: for each interaction step, the top pages, how many proceeded and how many exited there. */
    public function getFlowSteps($idSite, $period, $date, $steps = 5)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $steps = min(max((int) $steps, 2), 10);
        $paths = (new PathLoader())->loadVisitPaths((int) $idSite, $period, $date, $steps);
        $agg = []; // step => page => ['visits' =>, 'exits' =>]
        foreach ($paths as $pages) {
            foreach ($pages as $i => $page) {
                $agg[$i][$page]['visits'] = ($agg[$i][$page]['visits'] ?? 0) + 1;
                if ($i === count($pages) - 1) {
                    $agg[$i][$page]['exits'] = ($agg[$i][$page]['exits'] ?? 0) + 1;
                }
            }
        }
        $dt = new DataTable();
        ksort($agg);
        foreach ($agg as $step => $pages) {
            uasort($pages, fn($a, $b) => ($b['visits'] ?? 0) <=> ($a['visits'] ?? 0));
            foreach (array_slice($pages, 0, 12, true) as $page => $m) {
                $visits = $m['visits'] ?? 0;
                $exits = $m['exits'] ?? 0;
                $dt->addRowFromSimpleArray([
                    'label'        => 'Step ' . ($step + 1) . ': ' . $page,
                    'nb_visits'    => $visits,
                    'nb_exits'     => $exits,
                    'nb_proceeded' => $visits - $exits,
                    'exit_rate'    => $visits ? round(100 * $exits / $visits, 1) : 0,
                ]);
            }
        }
        return $dt;
    }

    /** Sankey data: nodes per step + weighted transitions. Used by the flow visualization page. */
    public function getFlowGraph($idSite, $period, $date, $steps = 5, $topN = 8)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $steps = min(max((int) $steps, 2), 8);
        $topN = min(max((int) $topN, 3), 15);
        $paths = (new PathLoader())->loadVisitPaths((int) $idSite, $period, $date, $steps);

        // top pages per step; everything else buckets into "(other)"
        $stepCounts = [];
        foreach ($paths as $pages) {
            foreach ($pages as $i => $p) {
                $stepCounts[$i][$p] = ($stepCounts[$i][$p] ?? 0) + 1;
            }
        }
        $keep = [];
        foreach ($stepCounts as $i => $pages) {
            arsort($pages);
            $keep[$i] = array_flip(array_keys(array_slice($pages, 0, $topN, true)));
        }
        $node = fn(int $i, string $p) => isset($keep[$i][$p]) ? $p : '(other)';

        $nodes = [];
        $links = [];
        $exits = [];
        foreach ($paths as $pages) {
            $n = count($pages);
            foreach ($pages as $i => $p) {
                $label = $node($i, $p);
                $nodes[$i][$label] = ($nodes[$i][$label] ?? 0) + 1;
                if ($i < $n - 1) {
                    $to = $node($i + 1, $pages[$i + 1]);
                    $links["$i|$label|$to"] = ($links["$i|$label|$to"] ?? 0) + 1;
                } else {
                    $exits[$i][$label] = ($exits[$i][$label] ?? 0) + 1;
                }
            }
        }
        $out = ['steps' => [], 'links' => []];
        foreach ($nodes as $i => $pages) {
            arsort($pages);
            foreach ($pages as $p => $c) {
                $out['steps'][$i][] = ['page' => $p, 'visits' => $c, 'exits' => $exits[$i][$p] ?? 0];
            }
        }
        foreach ($links as $key => $c) {
            [$step, $from, $to] = explode('|', $key, 3);
            $out['links'][] = ['step' => (int) $step, 'from' => $from, 'to' => $to, 'visits' => $c];
        }
        return $out;
    }
}
