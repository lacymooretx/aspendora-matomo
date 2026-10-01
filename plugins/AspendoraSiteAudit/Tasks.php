<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->weekly('runAudit', null, self::LOW_PRIORITY);
    }

    /**
     * Weekly crawl. This Matomo has no cron (the scheduler runs off web requests) and a host cron also runs
     * `./console aspendora-audit:run`, so sites audited successfully in the last 6 days are skipped here.
     */
    public function runAudit()
    {
        (new Auditor(StaticContainer::get(LoggerInterface::class)))->runAll(null, null, true, false);
    }
}
