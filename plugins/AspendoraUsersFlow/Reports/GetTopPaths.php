<?php

namespace Piwik\Plugins\AspendoraUsersFlow\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetTopPaths extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraUsersFlow';
        $this->action = 'getTopPaths';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraUsersFlow_UsersFlow';
        $this->name = Piwik::translate('AspendoraUsersFlow_TopPaths');
        $this->documentation = Piwik::translate('AspendoraUsersFlow_TopPathsDocumentation');
        $this->metrics = ['nb_visits', 'nb_steps'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_visits';
        $this->order = 40;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraUsersFlow_Path'));
        $view->config->addTranslation('nb_visits', 'Visits');
        $view->config->addTranslation('nb_steps', 'Pages in path');
        $view->config->columns_to_display = ['label', 'nb_visits', 'nb_steps'];
        $view->requestConfig->filter_limit = 25;
    }
}
