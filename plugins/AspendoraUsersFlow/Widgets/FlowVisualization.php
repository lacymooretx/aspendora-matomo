<?php

namespace Piwik\Plugins\AspendoraUsersFlow\Widgets;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\View;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class FlowVisualization extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('General_Actions');
        $config->setSubcategoryId('AspendoraUsersFlow_UsersFlow');
        $config->setName('AspendoraUsersFlow_FlowVisualization');
        $config->setOrder(5);
        $config->setIsNotWidgetizable();
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasViewAccess($idSite);
        $graph = Request::processRequest('AspendoraUsersFlow.getFlowGraph', [
            'idSite' => $idSite,
            'period' => Common::getRequestVar('period', 'month', 'string'),
            'date'   => Common::getRequestVar('date', 'today', 'string'),
            'steps'  => Common::getRequestVar('flowSteps', 5, 'int'),
        ]);
        $view = new View('@AspendoraUsersFlow/flow');
        $view->graphJson = json_encode($graph);
        return $view->render();
    }
}
