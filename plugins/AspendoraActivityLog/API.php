<?php

namespace Piwik\Plugins\AspendoraActivityLog;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /**
     * Recent activity entries, newest first. Super-user only.
     */
    public function getActivity($limit = 200)
    {
        Piwik::checkUserHasSuperUserAccess();
        $limit = min(max((int) $limit, 1), 1000);
        $table = Common::prefixTable(AspendoraActivityLog::TABLE);
        $rows = Db::fetchAll("SELECT ts, login, event, detail FROM `$table` ORDER BY id DESC LIMIT " . $limit);
        $dt = new DataTable();
        foreach ($rows as $r) {
            $dt->addRowFromSimpleArray([
                'label'  => $r['ts'],
                'login'  => $r['login'],
                'event'  => $r['event'],
                'detail' => (string) $r['detail'],
            ]);
        }
        return $dt;
    }
}
