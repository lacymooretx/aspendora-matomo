<?php

namespace Piwik\Plugins\AspendoraSessionRecording;

use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\View;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function index()
    {
        Piwik::checkUserHasSuperUserAccess();
        $rec = Common::prefixTable(AspendoraSessionRecording::TABLE_REC);
        $recordings = Db::fetchAll(
            "SELECT rec_key, idsite, MIN(started_at) AS started_at, MAX(url) AS url,
                    SUM(nb_events) AS nb_events, MAX(duration_ms) AS duration_ms,
                    MAX(ua) AS ua, COUNT(*) AS chunks
             FROM `$rec`
             GROUP BY rec_key, idsite
             ORDER BY started_at DESC
             LIMIT 200"
        );
        $view = new View('@AspendoraSessionRecording/index');
        $this->setBasicVariablesAdminView($view);
        $view->recordings = $recordings;
        return $view->render();
    }

    public function player()
    {
        Piwik::checkUserHasSuperUserAccess();
        $view = new View('@AspendoraSessionRecording/player');
        $this->setBasicVariablesAdminView($view);
        $view->recKey = preg_replace('/[^a-f0-9]/', '', Common::getRequestVar('recKey', '', 'string'));
        return $view->render();
    }

    public function events()
    {
        Piwik::checkUserHasSuperUserAccess();
        $recKey = preg_replace('/[^a-f0-9]/', '', Common::getRequestVar('recKey', '', 'string'));
        $rec = Common::prefixTable(AspendoraSessionRecording::TABLE_REC);
        $rows = Db::fetchAll("SELECT events FROM `$rec` WHERE rec_key = ? ORDER BY seq", [$recKey]);
        $all = [];
        foreach ($rows as $r) {
            $chunk = json_decode(gzuncompress($r['events']), true);
            if (is_array($chunk)) {
                $all = array_merge($all, $chunk);
            }
        }
        Common::sendHeader('Content-Type: application/json');
        return json_encode($all);
    }

    public function heatmap()
    {
        Piwik::checkUserHasSuperUserAccess();
        $url = Common::getRequestVar('pageUrl', '', 'string');
        $heat = Common::prefixTable(AspendoraSessionRecording::TABLE_HEAT);
        $clicks = $url === '' ? [] : Db::fetchAll(
            "SELECT x_pct, y_px, doc_h FROM `$heat` WHERE url = ? AND kind = 'click' ORDER BY id DESC LIMIT 5000",
            [$url]
        );
        $urls = Db::fetchAll(
            "SELECT url, COUNT(*) AS n FROM `$heat` WHERE kind = 'click' GROUP BY url ORDER BY n DESC LIMIT 100"
        );
        $view = new View('@AspendoraSessionRecording/heatmap');
        $this->setBasicVariablesAdminView($view);
        $view->pageUrl = $url;
        $view->urls = $urls;
        $view->clicksJson = json_encode($clicks);
        $view->reqIdSite = Common::getRequestVar('idSite', 1, 'int');
        $view->reqPeriod = Common::getRequestVar('period', 'day', 'string');
        $view->reqDate = Common::getRequestVar('date', 'today', 'string');
        return $view->render();
    }
}
