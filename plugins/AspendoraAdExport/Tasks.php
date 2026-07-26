<?php

namespace Piwik\Plugins\AspendoraAdExport;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('importWonOpportunities', null, self::LOW_PRIORITY);
    }

    public function importWonOpportunities()
    {
        (new WonOpportunities(StaticContainer::get(LoggerInterface::class)))->import();
    }
}
