<?php

namespace Piwik\Plugins\AspendoraActivityLog;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        if (Piwik::hasUserSuperUserAccess()) {
            $menu->addDiagnosticItem(
                'AspendoraActivityLog_ActivityLog',
                $this->urlForAction('index'),
                $orderId = 30
            );
        }
    }
}
