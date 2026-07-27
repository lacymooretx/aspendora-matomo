<?php

namespace Piwik\Plugins\AspendoraCrash;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    public function getJsErrors($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        [$start, $end] = $this->periodToRange($period, $date);
        $table = Common::prefixTable(AspendoraCrash::TABLE);
        $rows = Db::fetchAll(
            "SELECT message, source,
                    COUNT(*) AS nb_occurrences,
                    COUNT(DISTINCT url) AS nb_pages,
                    MAX(line) AS sample_line,
                    MAX(created_at) AS last_seen
             FROM `$table`
             WHERE idsite = ? AND day BETWEEN ? AND ?
             GROUP BY message, source
             ORDER BY nb_occurrences DESC
             LIMIT 500",
            [(int) $idSite, $start, $end]
        );
        $dt = new DataTable();
        foreach ($rows as $r) {
            $src = $r['source'] !== '' ? basename(parse_url($r['source'], PHP_URL_PATH) ?: $r['source']) : '';
            $dt->addRowFromSimpleArray([
                'label'          => $r['message'] . ($src !== '' ? ' (' . $src . ':' . $r['sample_line'] . ')' : ''),
                'nb_occurrences' => (int) $r['nb_occurrences'],
                'nb_pages'       => (int) $r['nb_pages'],
                'last_seen'      => $r['last_seen'],
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
