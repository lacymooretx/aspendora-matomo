<?php

namespace Piwik\Plugins\AspendoraWhiteLabel;

class AspendoraWhiteLabel extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return [
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJavaScriptFiles',
        ];
    }

    public function getStylesheetFiles(&$files)
    {
        $files[] = 'plugins/AspendoraWhiteLabel/stylesheets/whitelabel.css';
    }

    public function getJavaScriptFiles(&$files)
    {
        $files[] = 'plugins/AspendoraWhiteLabel/javascripts/whitelabel.js';
    }
}
