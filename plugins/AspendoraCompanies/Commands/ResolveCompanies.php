<?php

namespace Piwik\Plugins\AspendoraCompanies\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraCompanies\Alerts;
use Piwik\Plugins\AspendoraCompanies\Resolver;

class ResolveCompanies extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-companies:resolve');
        $this->setDescription('Resolve visitor network prefixes to organizations; optionally send the hot-activity digest');
        $this->addNoValueOption('send-digest', null, 'Also send the hot-activity email digest');
    }

    protected function doExecute(): int
    {
        $logger = StaticContainer::get(LoggerInterface::class);
        (new Resolver($logger))->run();
        if ($this->getInput()->getOption('send-digest')) {
            (new Alerts($logger))->send();
        }
        $this->getOutput()->writeln('<info>Company resolution finished</info>');
        return self::SUCCESS;
    }
}
