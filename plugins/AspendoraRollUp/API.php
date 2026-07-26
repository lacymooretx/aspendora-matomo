<?php

namespace Piwik\Plugins\AspendoraRollUp;

use Piwik\Access;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

/**
 * Cross-property overview. Only sites the requesting user can view are included,
 * so the combined row never leaks data the user couldn't see site-by-site.
 */
class API extends \Piwik\Plugin\API
{
    public function getOverview($period, $date)
    {
        Piwik::checkUserHasSomeViewAccess();
        $sites = Access::doAsSuperUser(fn() => SitesManagerAPI::getInstance()->getAllSites());
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';

        $lv = Common::prefixTable('log_visit');
        $llva = Common::prefixTable('log_link_visit_action');

        $dt = new DataTable();
        $total = ['visits' => 0, 'identities' => 0, 'pageviews' => 0, 'events' => 0];
        foreach ($sites as $site) {
            $idSite = (int) $site['idsite'];
            if (!Piwik::isUserHasViewAccess($idSite)) {
                continue;
            }
            $v = Db::fetchRow(
                "SELECT COUNT(*) AS visits,
                        COUNT(DISTINCT COALESCE(NULLIF(user_id,''), HEX(idvisitor))) AS identities,
                        COALESCE(SUM(visit_total_actions), 0) AS actions
                 FROM `$lv` WHERE idsite = ? AND visit_first_action_time BETWEEN ? AND ?",
                [$idSite, $start, $end]
            );
            $a = Db::fetchRow(
                "SELECT SUM(idaction_url IS NOT NULL AND idaction_event_category IS NULL) AS pageviews,
                        SUM(idaction_event_category IS NOT NULL) AS events
                 FROM `$llva` WHERE idsite = ? AND server_time BETWEEN ? AND ?",
                [$idSite, $start, $end]
            );
            $row = [
                'label'         => $site['name'],
                'nb_visits'     => (int) $v['visits'],
                'nb_identities' => (int) $v['identities'],
                'nb_pageviews'  => (int) ($a['pageviews'] ?? 0),
                'nb_events'     => (int) ($a['events'] ?? 0),
            ];
            foreach (['visits' => 'nb_visits', 'identities' => 'nb_identities', 'pageviews' => 'nb_pageviews', 'events' => 'nb_events'] as $k => $col) {
                $total[$k] += $row[$col];
            }
            $dt->addRowFromSimpleArray($row);
        }
        $dt->addRowFromSimpleArray([
            'label'         => 'ALL PROPERTIES',
            'nb_visits'     => $total['visits'],
            'nb_identities' => $total['identities'],
            'nb_pageviews'  => $total['pageviews'],
            'nb_events'     => $total['events'],
        ]);
        return $dt;
    }
}
