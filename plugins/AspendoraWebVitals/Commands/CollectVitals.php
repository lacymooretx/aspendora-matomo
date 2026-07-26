<?php

namespace Piwik\Plugins\AspendoraWebVitals\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraWebVitals\Collector;

class CollectVitals extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-webvitals:collect');
        $this->setDescription('Run PageSpeed Insights for all configured URLs now');
    }

    protected function doExecute(): int
    {
        (new Collector(StaticContainer::get(LoggerInterface::class)))->collectAll();
        $this->getOutput()->writeln('<info>Web Vitals collection finished</info>');
        return self::SUCCESS;
    }
}
