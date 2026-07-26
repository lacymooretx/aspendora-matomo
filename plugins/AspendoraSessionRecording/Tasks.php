<?php

namespace Piwik\Plugins\AspendoraSessionRecording;

use Piwik\Common;
use Piwik\Db;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('purgeOld', null, self::LOW_PRIORITY);
    }

    public function purgeOld()
    {
        Db::query('DELETE FROM `' . Common::prefixTable(AspendoraSessionRecording::TABLE_REC)
            . '` WHERE day < DATE_SUB(CURDATE(), INTERVAL 60 DAY)');
        Db::query('DELETE FROM `' . Common::prefixTable(AspendoraSessionRecording::TABLE_HEAT)
            . '` WHERE day < DATE_SUB(CURDATE(), INTERVAL 180 DAY)');
    }
}
