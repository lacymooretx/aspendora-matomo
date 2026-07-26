<?php

namespace Piwik\Plugins\AspendoraCustomReports\Widgets;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\View;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class ReportsViewer extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('General_Actions');
        $config->setSubcategoryId('AspendoraCustomReports_CustomReports');
        $config->setName('AspendoraCustomReports_CustomReports');
        $config->setOrder(5);
        $config->setIsNotWidgetizable();
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasViewAccess($idSite);
        $period = Common::getRequestVar('period', 'month', 'string');
        $date = Common::getRequestVar('date', 'today', 'string');

        $reports = Request::processRequest('AspendoraCustomReports.getReports', []);
        $rendered = [];
        foreach ($reports as $def) {
            $dt = Request::processRequest('AspendoraCustomReports.getReportData', [
                'idReport' => $def['id'], 'idSite' => $idSite, 'period' => $period, 'date' => $date,
            ]);
            $rows = [];
            $columns = [];
            foreach ($dt->getRows() as $row) {
                $r = $row->getColumns();
                if (!$columns) {
                    $columns = array_keys($r);
                }
                $rows[] = $r;
            }
            $rendered[] = ['def' => $def, 'columns' => $columns, 'rows' => $rows];
        }
        $view = new View('@AspendoraCustomReports/viewer');
        $view->reports = $rendered;
        $view->isSuperUser = Piwik::hasUserSuperUserAccess();
        return $view->render();
    }
}
