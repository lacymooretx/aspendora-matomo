<?php

namespace Piwik\Plugins\AspendoraCohorts;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

/**
 * Weekly retention cohorts. Identity = user_id when set, else the visitor ID.
 *
 * Honest caveat baked into the report docs: with cookieless tracking the anonymous
 * visitor ID rotates daily, so anonymous cross-week retention under-reports — the
 * matrix is most meaningful for identified (userId) visitors and as a lower bound.
 */
class API extends \Piwik\Plugin\API
{
    public function getCohorts($idSite, $period, $date, $weeks = 8)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $weeks = min(max((int) $weeks, 2), 16);
        $periodObj = PeriodFactory::build($period, $date);
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';
        // look back far enough to seed the oldest cohort
        $start = date('Y-m-d 00:00:00', strtotime($periodObj->getDateStart()->toString('Y-m-d') . " -{$weeks} weeks"));

        $lv = Common::prefixTable('log_visit');
        $rows = Db::fetchAll(
            "SELECT COALESCE(NULLIF(user_id, ''), HEX(idvisitor)) AS identity,
                    YEARWEEK(visit_first_action_time, 3) AS yw
             FROM `$lv`
             WHERE idsite = ? AND visit_first_action_time BETWEEN ? AND ?
             GROUP BY identity, yw
             LIMIT 100000",
            [(int) $idSite, $start, $end]
        );

        // first-seen week per identity, then presence per subsequent week
        $firstWeek = [];
        $present = [];
        foreach ($rows as $r) {
            $id = $r['identity'];
            $yw = (int) $r['yw'];
            if (!isset($firstWeek[$id]) || $yw < $firstWeek[$id]) {
                $firstWeek[$id] = $yw;
            }
            $present[$id][$yw] = true;
        }

        $cohorts = []; // yearweek => ['size' => n, 'ret' => [offset => count]]
        foreach ($firstWeek as $id => $cw) {
            $cohorts[$cw]['size'] = ($cohorts[$cw]['size'] ?? 0) + 1;
            foreach ($present[$id] as $yw => $_) {
                $offset = $this->weekDiff($cw, $yw);
                if ($offset > 0 && $offset <= $weeks) {
                    $cohorts[$cw]['ret'][$offset] = ($cohorts[$cw]['ret'][$offset] ?? 0) + 1;
                }
            }
        }
        ksort($cohorts);

        $dt = new DataTable();
        foreach ($cohorts as $yw => $c) {
            $row = [
                'label'   => $this->weekLabel($yw),
                'nb_new'  => $c['size'],
            ];
            for ($i = 1; $i <= $weeks; $i++) {
                $row['w' . $i] = $c['size'] ? round(100 * ($c['ret'][$i] ?? 0) / $c['size'], 1) : 0;
            }
            $dt->addRowFromSimpleArray($row);
        }
        return $dt;
    }

    private function weekDiff(int $fromYw, int $toYw): int
    {
        $fy = intdiv($fromYw, 100); $fw = $fromYw % 100;
        $ty = intdiv($toYw, 100); $tw = $toYw % 100;
        return ($ty - $fy) * 52 + ($tw - $fw);
    }

    private function weekLabel(int $yw): string
    {
        $y = intdiv($yw, 100); $w = $yw % 100;
        $d = new \DateTime();
        $d->setISODate($y, $w);
        return 'Week of ' . $d->format('M j, Y');
    }
}
