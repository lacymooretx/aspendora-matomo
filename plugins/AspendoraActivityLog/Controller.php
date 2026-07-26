<?php

namespace Piwik\Plugins\AspendoraActivityLog;

use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\View;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function index()
    {
        Piwik::checkUserHasSuperUserAccess();
        $table = Common::prefixTable(AspendoraActivityLog::TABLE);
        $entries = Db::fetchAll("SELECT ts, login, event, detail FROM `$table` ORDER BY id DESC LIMIT 300");
        $view = new View('@AspendoraActivityLog/index');
        $this->setBasicVariablesAdminView($view);
        $view->entries = $entries;
        return $view->render();
    }
}
