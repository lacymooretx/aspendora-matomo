<?php

namespace Piwik\Plugins\AspendoraSearchKeywords\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraSearchKeywords\GscClient;
use Piwik\Plugins\AspendoraSearchKeywords\Importer;

class ImportGsc extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-gsc:import');
        $this->setDescription('Import Google Search Console keyword data for all mapped sites');
        $this->addOptionalValueOption('days', null, 'How many days back to import (ending 3 days ago)', 5);
    }

    protected function doExecute(): int
    {
        $days = (int) $this->getInput()->getOption('days');
        $importer = new Importer(new GscClient(), StaticContainer::get(LoggerInterface::class));
        $importer->importAll($days);
        $this->getOutput()->writeln('<info>GSC import finished (' . $days . ' days)</info>');
        return self::SUCCESS;
    }
}
