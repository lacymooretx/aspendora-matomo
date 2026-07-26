<?php

namespace Piwik\Plugins\AspendoraSessionRecording;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess()) {
            $menu->addDiagnosticItem('AspendoraSessionRecording_SessionRecordings', $this->urlForAction('index'), 32);
            $menu->addDiagnosticItem('AspendoraSessionRecording_ClickMap', $this->urlForAction('heatmap'), 33);
        }
    }
}
