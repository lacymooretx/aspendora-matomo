<?php

use Piwik\DI;
use Piwik\Plugins\AspendoraWhiteLabel\TranslationLoader;

return [
    // See TranslationLoader: without this the rebranded strings lose to the core plugins they
    // override, because translation directories are merged in alphabetical order.
    'Piwik\Translation\Loader\LoaderInterface' => DI::decorate(function ($previous) {
        return new TranslationLoader($previous);
    }),
];
