<?php

namespace Piwik\Plugins\AspendoraInsights;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->weekly('sendWeeklyDigest', null, self::LOWEST_PRIORITY);
    }

    public function sendWeeklyDigest()
    {
        (new Digest(new ClaudeClient(), StaticContainer::get(LoggerInterface::class)))->send();
    }
}
