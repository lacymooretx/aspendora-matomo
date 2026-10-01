<?php

namespace Piwik\Plugins\AspendoraSearchKeywords\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraSearchKeywords\BingClient;
use Piwik\Plugins\AspendoraSearchKeywords\GscClient;
use Piwik\Plugins\AspendoraSearchKeywords\Importer;

class ImportBing extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-bing:import');
        $this->setDescription('Import Bing Webmaster Tools keywords, pages, crawl stats and crawl issues for all mapped sites');
    }

    protected function doExecute(): int
    {
        $importer = new Importer(new GscClient(), StaticContainer::get(LoggerInterface::class), new BingClient());
        $importer->importBing();
        $this->getOutput()->writeln('<info>Bing import finished (Bing returns full history each run)</info>');
        return self::SUCCESS;
    }
}
