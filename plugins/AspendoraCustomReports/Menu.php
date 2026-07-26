<?php

namespace Piwik\Plugins\AspendoraCustomReports;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess()) {
            $menu->addDiagnosticItem('AspendoraCustomReports_CustomReports', $this->urlForAction('index'), 31);
        }
    }
}
