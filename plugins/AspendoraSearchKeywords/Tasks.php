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

    /** GSC then Bing. A failure in either source never stops the other. */
    public function importKeywords()
    {
        $logger = StaticContainer::get(LoggerInterface::class);
        $importer = new Importer(new GscClient(), $logger, new BingClient());
        try {
            $importer->importAll();
        } catch (\Throwable $e) {
            $logger->error('AspendoraSearchKeywords: GSC import aborted: {m}', ['m' => $e->getMessage()]);
        }
        try {
            $importer->importBing();
        } catch (\Throwable $e) {
            $logger->error('AspendoraSearchKeywords: Bing import aborted: {m}', ['m' => $e->getMessage()]);
        }
    }
}
