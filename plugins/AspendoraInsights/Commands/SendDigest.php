<?php

namespace Piwik\Plugins\AspendoraInsights\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\AspendoraInsights\ClaudeClient;
use Piwik\Plugins\AspendoraInsights\Digest;

class SendDigest extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('aspendora-insights:send');
        $this->setDescription('Generate and email the AI-written weekly analytics digest now');
    }

    protected function doExecute(): int
    {
        (new Digest(new ClaudeClient(), StaticContainer::get(LoggerInterface::class)))->send();
        $this->getOutput()->writeln('<info>Insights digest finished</info>');
        return self::SUCCESS;
    }
}
