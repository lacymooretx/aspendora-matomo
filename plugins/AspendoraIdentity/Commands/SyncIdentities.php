<?php

namespace Piwik\Plugins\AspendoraIdentity\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraIdentity\GhlClient;
use Piwik\Plugins\AspendoraIdentity\Sync;

class SyncIdentities extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-identity:sync');
        $this->setDescription('Aggregate identified visitors, score them, and sync with GoHighLevel');
    }

    protected function doExecute(): int
    {
        $sync = new Sync(new GhlClient(), StaticContainer::get(LoggerInterface::class));
        $sync->run();
        $this->getOutput()->writeln('<info>Identity sync finished</info>');
        return self::SUCCESS;
    }
}
