<?php

namespace Piwik\Plugins\AspendoraFormAnalytics;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /** Per-form funnel: views → starts → submits (+ abandons, rates). */
    public function getForms($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $rows = $this->fetchEvents((int) $idSite, $period, $date,
            "aa.name AS ev_action,
             SUBSTRING_INDEX(an.name, '::', 1) AS form_id,
             COUNT(*) AS c",
            "GROUP BY ev_action, form_id");

        $forms = [];
        foreach ($rows as $r) {
            $forms[$r['form_id']][$r['ev_action']] = ($forms[$r['form_id']][$r['ev_action']] ?? 0) + (int) $r['c'];
        }
        $dt = new DataTable();
        foreach ($forms as $formId => $m) {
            $views = $m['view'] ?? 0;
            $starts = $m['start'] ?? 0;
            $submits = $m['submit'] ?? 0;
            $abandons = $m['abandon'] ?? 0;
            $dt->addRowFromSimpleArray([
                'label'         => AspendoraFormAnalytics::formDisplayName($formId),
                'nb_views'      => $views,
                'nb_starts'     => $starts,
                'nb_submits'    => $submits,
                'nb_abandons'   => $abandons,
                'start_rate'    => $views ? round(100 * $starts / $views, 1) : 0,
                'conversion'    => $starts ? round(100 * $submits / $starts, 1) : 0,
            ]);
        }
        $dt->filter('Sort', ['nb_views', 'desc']);
        return $dt;
    }

    /** Per-field: interactions, average hesitation time, and last-field-before-abandon counts. */
    public function getFormFields($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $fieldRows = $this->fetchEvents((int) $idSite, $period, $date,
            "an.name AS full_name,
             COUNT(*) AS c,
             AVG(llva.custom_float) AS avg_secs",
            "AND aa.name = 'field' GROUP BY full_name");
        $abandonRows = $this->fetchEvents((int) $idSite, $period, $date,
            "an.name AS full_name, COUNT(*) AS c",
            "AND aa.name = 'abandon' GROUP BY full_name");

        $abandons = [];
        foreach ($abandonRows as $r) {
            $abandons[$r['full_name']] = (int) $r['c'];
        }
        $dt = new DataTable();
        $seen = [];
        foreach ($fieldRows as $r) {
            $seen[$r['full_name']] = true;
            $formId = explode('::', $r['full_name'], 2)[0];
            $label = str_replace($formId, AspendoraFormAnalytics::formDisplayName($formId), $r['full_name']);
            $dt->addRowFromSimpleArray([
                'label'           => $label,
                'nb_interactions' => (int) $r['c'],
                'avg_seconds'     => round((float) $r['avg_secs'], 1),
                'nb_abandons'     => $abandons[$r['full_name']] ?? 0,
            ]);
        }
        // abandon-only rows (field never blurred but was last touched)
        foreach ($abandons as $name => $c) {
            if (!isset($seen[$name])) {
                $formId = explode('::', $name, 2)[0];
                $dt->addRowFromSimpleArray([
                    'label'           => str_replace($formId, AspendoraFormAnalytics::formDisplayName($formId), $name),
                    'nb_interactions' => 0,
                    'avg_seconds'     => 0,
                    'nb_abandons'     => $c,
                ]);
            }
        }
        $dt->filter('Sort', ['nb_interactions', 'desc']);
        return $dt;
    }

    private function fetchEvents(int $idSite, $period, $date, string $select, string $tail): array
    {
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';
        $llva = Common::prefixTable('log_link_visit_action');
        $la = Common::prefixTable('log_action');
        return Db::fetchAll(
            "SELECT $select
             FROM `$llva` llva
             JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
             JOIN `$la` aa ON aa.idaction = llva.idaction_event_action
             JOIN `$la` an ON an.idaction = llva.idaction_name
             WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ?
               AND ac.name = 'FormAnalytics'
             $tail",
            [$idSite, $start, $end]
        );
    }
}
