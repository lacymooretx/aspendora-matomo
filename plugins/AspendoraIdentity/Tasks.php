<?php

namespace Piwik\Plugins\AspendoraIdentity;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->hourly('syncIdentities', null, self::LOW_PRIORITY);
    }

    public function syncIdentities()
    {
        $sync = new Sync(new GhlClient(), StaticContainer::get(LoggerInterface::class));
        $sync->run();
    }
}
