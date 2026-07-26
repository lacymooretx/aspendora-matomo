<?php

namespace Piwik\Plugins\AspendoraCustomReports;

use Exception;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /** List defined reports (id, name, dimension, metrics, filters). */
    public function getReports()
    {
        Piwik::checkUserHasSomeViewAccess();
        return Db::fetchAll('SELECT id, name, dimension, metrics, filters FROM `'
            . Common::prefixTable(AspendoraCustomReports::TABLE) . '` ORDER BY name');
    }

    /** Dimension + metric catalog for building the UI. */
    public function getCatalog()
    {
        Piwik::checkUserHasSomeViewAccess();
        $strip = fn($items) => array_map(fn($d) => ['scope' => $d['scope'], 'label' => $d['label']], $items);
        return ['dimensions' => $strip(Catalog::dimensions()), 'metrics' => $strip(Catalog::metrics())];
    }

    /**
     * @param string $metrics comma-separated metric keys
     * @param string $filters JSON list of {"dimension": key, "value": string}
     */
    public function addReport($name, $dimension, $metrics, $filters = '')
    {
        Piwik::checkUserHasSuperUserAccess();
        $this->validate($dimension, $metrics, $filters);
        Db::query(
            'INSERT INTO `' . Common::prefixTable(AspendoraCustomReports::TABLE)
            . '` (name, dimension, metrics, filters, created_by, created_at) VALUES (?,?,?,?,?,NOW())',
            [mb_substr(trim($name), 0, 150), $dimension, $metrics, $filters, Piwik::getCurrentUserLogin()]
        );
        return ['result' => 'success'];
    }

    public function deleteReport($idReport)
    {
        Piwik::checkUserHasSuperUserAccess();
        Db::query('DELETE FROM `' . Common::prefixTable(AspendoraCustomReports::TABLE) . '` WHERE id = ?', [(int) $idReport]);
        return ['result' => 'success'];
    }

    /** Run a defined report for a site/period. */
    public function getReportData($idReport, $idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $def = Db::fetchRow('SELECT * FROM `' . Common::prefixTable(AspendoraCustomReports::TABLE) . '` WHERE id = ?', [(int) $idReport]);
        if (!$def) {
            throw new Exception('Unknown report');
        }
        return $this->run($def, (int) $idSite, $period, $date);
    }

    private function validate(string $dimension, string $metrics, string $filters): void
    {
        $dims = Catalog::dimensions();
        $mets = Catalog::metrics();
        if (!isset($dims[$dimension])) {
            throw new Exception('Unknown dimension: ' . $dimension);
        }
        $scope = $dims[$dimension]['scope'];
        $keys = array_filter(array_map('trim', explode(',', $metrics)));
        if (!$keys) {
            throw new Exception('At least one metric required');
        }
        foreach ($keys as $k) {
            if (!isset($mets[$k])) {
                throw new Exception('Unknown metric: ' . $k);
            }
            if ($mets[$k]['scope'] !== $scope) {
                throw new Exception("Metric '$k' is " . $mets[$k]['scope'] . '-scope but dimension is ' . $scope . '-scope');
            }
        }
        if ($filters !== '') {
            $list = json_decode($filters, true);
            if (!is_array($list)) {
                throw new Exception('Filters must be a JSON list');
            }
            foreach ($list as $f) {
                if (!isset($f['dimension'], $f['value']) || !isset($dims[$f['dimension']])) {
                    throw new Exception('Invalid filter');
                }
                if ($dims[$f['dimension']]['scope'] !== $scope) {
                    throw new Exception('Filter dimension scope mismatch');
                }
            }
        }
    }

    private function run(array $def, int $idSite, $period, $date): DataTable
    {
        $dims = Catalog::dimensions();
        $mets = Catalog::metrics();
        $dim = $dims[$def['dimension']];
        $scope = $dim['scope'];
        $metricKeys = array_filter(array_map('trim', explode(',', $def['metrics'])));

        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';

        $selects = [$dim['sql'] . ' AS label'];
        foreach ($metricKeys as $k) {
            $selects[] = $mets[$k]['sql'] . ' AS `' . $k . '`';
        }

        $where = [];
        $binds = [$idSite, $start, $end];
        $filters = $def['filters'] ? (json_decode($def['filters'], true) ?: []) : [];
        foreach ($filters as $f) {
            $where[] = '(' . $dims[$f['dimension']]['sql'] . ') = ?';
            $binds[] = $f['value'];
        }
        $whereSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

        if ($scope === 'visit') {
            $lv = Common::prefixTable('log_visit');
            $sql = 'SELECT ' . implode(', ', $selects)
                . " FROM `$lv` v WHERE v.idsite = ? AND v.visit_first_action_time BETWEEN ? AND ?"
                . $whereSql . ' GROUP BY label ORDER BY 2 DESC LIMIT 500';
        } else {
            $llva = Common::prefixTable('log_link_visit_action');
            $la = Common::prefixTable('log_action');
            $sql = 'SELECT ' . implode(', ', $selects)
                . " FROM `$llva` a
                   LEFT JOIN `$la` u ON u.idaction = a.idaction_url
                   LEFT JOIN `$la` t ON t.idaction = a.idaction_name AND a.idaction_event_category IS NULL
                   LEFT JOIN `$la` ec ON ec.idaction = a.idaction_event_category
                   LEFT JOIN `$la` ea ON ea.idaction = a.idaction_event_action
                   LEFT JOIN `$la` en ON en.idaction = a.idaction_name AND a.idaction_event_category IS NOT NULL
                   WHERE a.idsite = ? AND a.server_time BETWEEN ? AND ?"
                . $whereSql . ' GROUP BY label ORDER BY 2 DESC LIMIT 500';
        }

        $dt = new DataTable();
        foreach (Db::fetchAll($sql, $binds) as $r) {
            if ($r['label'] === null) {
                continue;
            }
            $dt->addRowFromSimpleArray($r);
        }
        return $dt;
    }
}
