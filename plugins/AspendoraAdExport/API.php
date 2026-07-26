<?php

namespace Piwik\Plugins\AspendoraAdExport;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugins\AspendoraAttribution\AspendoraAttribution;

/**
 * Offline-conversion export. The site bundle captures ad click IDs on landing as events:
 *   category "AdClick", action "gclid" | "msclkid", name = the click id value.
 * A conversion (goal or configured event, see AspendoraAttribution) inside the same visit —
 * or a later visit of the same identity within 30 days — is exported with that click id.
 *
 * getGoogleAdsExport / getMicrosoftAdsExport return DataTables shaped for each platform's
 * offline-conversion import; request with &format=csv for a ready-to-upload file.
 */
class API extends \Piwik\Plugin\API
{
    private const LOOKBACK_DAYS = 30;

    public function getGoogleAdsExport($idSite, $period, $date, $conversionName = 'Newsletter Signup')
    {
        return $this->export($idSite, $period, $date, 'gclid', [
            'Google Click ID'     => 'click_id',
            'Conversion Name'     => fn() => $conversionName,
            'Conversion Time'     => 'time_formatted',
            'Conversion Value'    => fn() => '0',
            'Conversion Currency' => fn() => 'USD',
        ]);
    }

    public function getMicrosoftAdsExport($idSite, $period, $date, $conversionName = 'Newsletter Signup')
    {
        return $this->export($idSite, $period, $date, 'msclkid', [
            'Microsoft Click ID' => 'click_id',
            'Conversion Name'    => fn() => $conversionName,
            'Conversion Time'    => 'time_formatted',
            'Conversion Value'   => fn() => '0',
            'Conversion Currency' => fn() => 'USD',
        ]);
    }

    private function export($idSite, $period, $date, string $network, array $columns): DataTable
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';
        $clickStart = date('Y-m-d H:i:s', strtotime($start) - self::LOOKBACK_DAYS * 86400);

        $llva = Common::prefixTable('log_link_visit_action');
        $la = Common::prefixTable('log_action');
        $lv = Common::prefixTable('log_visit');
        $lc = Common::prefixTable('log_conversion');

        // click ids per identity (captured on ad landing)
        $clicks = Db::fetchAll(
            "SELECT COALESCE(NULLIF(v.user_id,''), HEX(v.idvisitor)) AS identity,
                    an.name AS click_id, llva.server_time AS t
             FROM `$llva` llva
             JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
             JOIN `$la` aa ON aa.idaction = llva.idaction_event_action
             JOIN `$la` an ON an.idaction = llva.idaction_name
             JOIN `$lv` v ON v.idvisit = llva.idvisit
             WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ?
               AND ac.name = 'AdClick' AND aa.name = ?
             ORDER BY llva.server_time",
            [$idSite, $clickStart, $end, $network]
        );
        $byIdentity = [];
        foreach ($clicks as $c) {
            $byIdentity[$c['identity']][] = $c;
        }
        if (!$byIdentity) {
            return new DataTable();
        }

        // conversions in period (goals + configured events)
        $convs = Db::fetchAll(
            "SELECT COALESCE(NULLIF(v.user_id,''), HEX(v.idvisitor)) AS identity, c.server_time AS t
             FROM `$lc` c JOIN `$lv` v ON v.idvisit = c.idvisit
             WHERE c.idsite = ? AND c.server_time BETWEEN ? AND ?",
            [$idSite, $start, $end]
        );
        $cats = AspendoraAttribution::conversionEventCategories();
        $in = implode(',', array_fill(0, count($cats), '?'));
        $convs = array_merge($convs, Db::fetchAll(
            "SELECT COALESCE(NULLIF(v.user_id,''), HEX(v.idvisitor)) AS identity, llva.server_time AS t
             FROM `$llva` llva
             JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
             JOIN `$lv` v ON v.idvisit = llva.idvisit
             WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ? AND ac.name IN ($in)",
            array_merge([$idSite, $start, $end], $cats)
        ));

        $dt = new DataTable();
        $emitted = [];
        foreach ($convs as $conv) {
            $candidates = $byIdentity[$conv['identity']] ?? [];
            // most recent click id before the conversion, within lookback
            $best = null;
            foreach ($candidates as $c) {
                $ct = strtotime($c['t']);
                $vt = strtotime($conv['t']);
                if ($ct <= $vt && $ct >= $vt - self::LOOKBACK_DAYS * 86400) {
                    $best = $c;
                }
            }
            if (!$best) {
                continue;
            }
            $key = $best['click_id'] . '|' . $conv['t'];
            if (isset($emitted[$key])) {
                continue;
            }
            $emitted[$key] = true;
            $src = [
                'click_id'       => $best['click_id'],
                'time_formatted' => date('Y-m-d H:i:s', strtotime($conv['t'])) . '+00:00',
            ];
            $row = [];
            foreach ($columns as $header => $spec) {
                $row[$header] = is_callable($spec) ? $spec() : $src[$spec];
            }
            // DataTable needs a 'label'; use the click id and keep platform headers as columns
            $dt->addRowFromSimpleArray(array_merge(['label' => $best['click_id']], $row));
        }
        return $dt;
    }
}
