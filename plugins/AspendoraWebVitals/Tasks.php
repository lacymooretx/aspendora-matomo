<?php

namespace Piwik\Plugins\AspendoraWebVitals;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('collectVitals', null, self::LOW_PRIORITY);
    }

    public function collectVitals()
    {
        (new Collector(StaticContainer::get(LoggerInterface::class)))->collectAll();
    }
}
