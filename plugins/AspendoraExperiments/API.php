<?php

namespace Piwik\Plugins\AspendoraExperiments;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugins\AspendoraAttribution\AspendoraAttribution;

class API extends \Piwik\Plugin\API
{
    public function getExperiments($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';

        $llva = Common::prefixTable('log_link_visit_action');
        $la = Common::prefixTable('log_action');
        $lc = Common::prefixTable('log_conversion');

        // experiment exposures: experiment → variant → set of visits
        $rows = Db::fetchAll(
            "SELECT aa.name AS experiment, an.name AS variant, llva.idvisit
             FROM `$llva` llva
             JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
             JOIN `$la` aa ON aa.idaction = llva.idaction_event_action
             JOIN `$la` an ON an.idaction = llva.idaction_name
             WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ?
               AND ac.name = 'Experiment'
             LIMIT 100000",
            [$idSite, $start, $end]
        );
        if (!$rows) {
            return new DataTable();
        }
        $exposure = []; // experiment => variant => [idvisit => true]
        foreach ($rows as $r) {
            $exposure[$r['experiment']][$r['variant']][(int) $r['idvisit']] = true;
        }

        // converting visits: goals + configured conversion events
        $converting = [];
        foreach (Db::fetchAll(
            "SELECT DISTINCT idvisit FROM `$lc` WHERE idsite = ? AND server_time BETWEEN ? AND ?",
            [$idSite, $start, $end]
        ) as $r) {
            $converting[(int) $r['idvisit']] = true;
        }
        $cats = AspendoraAttribution::conversionEventCategories();
        if ($cats) {
            $in = implode(',', array_fill(0, count($cats), '?'));
            foreach (Db::fetchAll(
                "SELECT DISTINCT llva.idvisit
                 FROM `$llva` llva
                 JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
                 WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ? AND ac.name IN ($in)",
                array_merge([$idSite, $start, $end], $cats)
            ) as $r) {
                $converting[(int) $r['idvisit']] = true;
            }
        }

        $dt = new DataTable();
        foreach ($exposure as $experiment => $variants) {
            ksort($variants);
            $baseline = null; // first variant = control
            foreach ($variants as $variant => $visits) {
                $n = count($visits);
                $conv = count(array_intersect_key($visits, $converting));
                $rate = $n ? $conv / $n : 0;
                if ($baseline === null) {
                    $baseline = ['n' => $n, 'conv' => $conv];
                    $z = null;
                } else {
                    $z = self::twoProportionZ($baseline['conv'], $baseline['n'], $conv, $n);
                }
                $dt->addRowFromSimpleArray([
                    'label'         => $experiment . ' — ' . $variant,
                    'nb_visits'     => $n,
                    'nb_conversions' => $conv,
                    'conversion_rate' => round(100 * $rate, 2),
                    'confidence'    => $z === null ? '(control)' : self::confidenceLabel($z),
                ]);
            }
        }
        return $dt;
    }

    /** Two-proportion z-score (variant vs control); null when undefined. */
    private static function twoProportionZ(int $c1, int $n1, int $c2, int $n2): ?float
    {
        if ($n1 < 1 || $n2 < 1) {
            return null;
        }
        $p = ($c1 + $c2) / ($n1 + $n2);
        $se = sqrt($p * (1 - $p) * (1 / $n1 + 1 / $n2));
        if ($se == 0.0) {
            return null;
        }
        return (($c2 / $n2) - ($c1 / $n1)) / $se;
    }

    private static function confidenceLabel(?float $z): string
    {
        if ($z === null) {
            return '—';
        }
        $abs = abs($z);
        $dir = $z > 0 ? '↑' : '↓';
        if ($abs >= 2.576) {
            return $dir . ' 99%+';
        }
        if ($abs >= 1.96) {
            return $dir . ' 95%+';
        }
        if ($abs >= 1.645) {
            return $dir . ' 90%+';
        }
        return 'not significant';
    }
}
