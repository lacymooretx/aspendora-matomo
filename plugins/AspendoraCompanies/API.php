<?php

namespace Piwik\Plugins\AspendoraCompanies;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

/**
 * API for company (reverse-IP) reports: organizations — ISPs and hosting
 * filtered out — whose networks visited the site in the requested period.
 */
class API extends \Piwik\Plugin\API
{
    public function getCompanies($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        $orgs = Common::prefixTable(AspendoraCompanies::TABLE);
        $logVisit = Common::prefixTable('log_visit');
        $rows = Db::fetchAll(
            "SELECT o.org AS label,
                    COUNT(DISTINCT v.idvisit) AS nb_visits,
                    COUNT(DISTINCT v.idvisitor) AS nb_uniq_visitors,
                    COALESCE(SUM(v.visit_total_actions), 0) AS nb_actions,
                    MAX(v.visit_last_action_time) AS last_seen
             FROM `$logVisit` v
             JOIN `$orgs` o ON o.prefix = v.aspendora_org_prefix
             WHERE v.idsite = ? AND o.is_isp = 0 AND o.org IS NOT NULL
               AND v.visit_last_action_time BETWEEN ? AND ?
             GROUP BY o.org
             ORDER BY nb_actions DESC, nb_visits DESC
             LIMIT 500",
            [(int) $idSite, $start . ' 00:00:00', $end . ' 23:59:59']
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'            => $r['label'],
                'nb_visits'        => (int) $r['nb_visits'],
                'nb_uniq_visitors' => (int) $r['nb_uniq_visitors'],
                'nb_actions'       => (int) $r['nb_actions'],
                'last_seen'        => $r['last_seen'],
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
