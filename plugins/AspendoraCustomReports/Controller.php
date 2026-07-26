<?php

namespace Piwik\Plugins\AspendoraCustomReports;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\View;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    private const NONCE = 'AspendoraCustomReports.manage';

    public function index()
    {
        Piwik::checkUserHasSuperUserAccess();
        $view = new View('@AspendoraCustomReports/manage');
        $this->setBasicVariablesAdminView($view);
        $view->reports = Request::processRequest('AspendoraCustomReports.getReports', []);
        $view->catalog = Request::processRequest('AspendoraCustomReports.getCatalog', []);
        $view->nonce = Nonce::getNonce(self::NONCE);
        $view->message = Common::getRequestVar('message', '', 'string');
        return $view->render();
    }

    public function save()
    {
        Piwik::checkUserHasSuperUserAccess();
        Nonce::checkNonce(self::NONCE, Common::getRequestVar('nonce', '', 'string'));
        $filters = [];
        $fDim = Common::getRequestVar('filter_dimension', '', 'string');
        $fVal = Common::getRequestVar('filter_value', '', 'string');
        if ($fDim !== '' && $fVal !== '') {
            $filters[] = ['dimension' => $fDim, 'value' => $fVal];
        }
        try {
            Request::processRequest('AspendoraCustomReports.addReport', [
                'name'      => Common::getRequestVar('name', '', 'string'),
                'dimension' => Common::getRequestVar('dimension', '', 'string'),
                'metrics'   => implode(',', Common::getRequestVar('metrics', [], 'array')),
                'filters'   => $filters ? json_encode($filters) : '',
            ]);
            $msg = 'saved';
        } catch (\Exception $e) {
            $msg = 'error: ' . $e->getMessage();
        }
        $this->redirectToIndex('AspendoraCustomReports', 'index', null, null, null, ['message' => $msg]);
    }

    public function remove()
    {
        Piwik::checkUserHasSuperUserAccess();
        Nonce::checkNonce(self::NONCE, Common::getRequestVar('nonce', '', 'string'));
        Request::processRequest('AspendoraCustomReports.deleteReport', [
            'idReport' => Common::getRequestVar('idReport', 0, 'int'),
        ]);
        $this->redirectToIndex('AspendoraCustomReports', 'index', null, null, null, ['message' => 'deleted']);
    }
}
