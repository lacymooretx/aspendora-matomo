<?php

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('importKeywords', null, self::LOW_PRIORITY);
    }

    public function importKeywords()
    {
        $importer = new Importer(new GscClient(), StaticContainer::get(LoggerInterface::class));
        $importer->importAll();
    }
}
