<?php

namespace Piwik\Plugins\AspendoraAdExport;

use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

/** Adds the aspendora_offline_conversions table (install() is idempotent). */
class Updates_1_1_0 extends PiwikUpdates
{
    public function doUpdate(Updater $updater)
    {
        (new AspendoraAdExport())->install();
    }
}
