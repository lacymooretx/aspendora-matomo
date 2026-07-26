<?php

namespace Piwik\Plugins\AspendoraAdExport\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraAdExport\WonOpportunities;

class ImportWonOpps extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-adexport:import-won');
        $this->setDescription('Import won GoHighLevel opportunities and match them to ad click ids');
    }

    protected function doExecute(): int
    {
        (new WonOpportunities(StaticContainer::get(LoggerInterface::class)))->import();
        $this->getOutput()->writeln('<info>Won-opportunity import finished</info>');
        return self::SUCCESS;
    }
}
