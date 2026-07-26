<?php

namespace Piwik\Plugins\AspendoraIdentity;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

/**
 * API for the known-visitor identity graph. Lists identities that were
 * active (last_seen) within the requested period, best leads first.
 */
class API extends \Piwik\Plugin\API
{
    public function getKnownVisitors($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        $table = Common::prefixTable(AspendoraIdentity::TABLE);
        $rows = Db::fetchAll(
            "SELECT user_id, email, ghl_contact_id, first_name, last_name, company,
                    nb_visits, nb_actions, lead_score, is_hot_intent, last_seen
             FROM `$table`
             WHERE idsite = ? AND last_seen BETWEEN ? AND ?
             ORDER BY lead_score DESC, last_seen DESC
             LIMIT 1000",
            [(int) $idSite, $start . ' 00:00:00', $end . ' 23:59:59']
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            $label = $name !== '' ? $name : ($r['email'] ?: $r['user_id']);
            $dt->addRowFromSimpleArray([
                'label'       => $label,
                'email'       => $r['email'] ?: '',
                'company'     => $r['company'] ?: '',
                'nb_visits'   => (int) $r['nb_visits'],
                'nb_actions'  => (int) $r['nb_actions'],
                'lead_score'  => (int) $r['lead_score'],
                'hot_intent'  => $r['is_hot_intent'] ? '🔥' : '',
                'last_seen'   => $r['last_seen'],
                'ghl_contact' => $r['ghl_contact_id'] ?: '',
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
