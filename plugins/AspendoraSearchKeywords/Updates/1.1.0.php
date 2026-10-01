<?php

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

/** Adds the aspendora_bing_* tables (install() is idempotent). */
class Updates_1_1_0 extends PiwikUpdates
{
    public function doUpdate(Updater $updater)
    {
        (new AspendoraSearchKeywords())->install();
    }
}
