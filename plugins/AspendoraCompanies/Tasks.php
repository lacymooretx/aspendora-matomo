<?php

namespace Piwik\Plugins\AspendoraCompanies;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('resolveCompanies', null, self::LOW_PRIORITY);
        $this->daily('sendHotActivityDigest', null, self::LOWEST_PRIORITY);
    }

    public function resolveCompanies()
    {
        (new Resolver(StaticContainer::get(LoggerInterface::class)))->run();
    }

    public function sendHotActivityDigest()
    {
        (new Alerts(StaticContainer::get(LoggerInterface::class)))->send();
    }
}
