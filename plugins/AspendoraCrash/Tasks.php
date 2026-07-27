<?php

namespace Piwik\Plugins\AspendoraCrash;

use Piwik\Common;
use Piwik\Db;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('pruneOldErrors', null, self::LOWEST_PRIORITY);
    }

    public function pruneOldErrors()
    {
        $table = Common::prefixTable(AspendoraCrash::TABLE);
        Db::query(
            "DELETE FROM `$table` WHERE day < DATE_SUB(CURDATE(), INTERVAL " . AspendoraCrash::RETENTION_DAYS . " DAY)"
        );
    }
}
