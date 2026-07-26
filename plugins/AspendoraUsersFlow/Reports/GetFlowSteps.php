<?php

namespace Piwik\Plugins\AspendoraUsersFlow\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetFlowSteps extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraUsersFlow';
        $this->action = 'getFlowSteps';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraUsersFlow_UsersFlow';
        $this->name = Piwik::translate('AspendoraUsersFlow_FlowSteps');
        $this->documentation = Piwik::translate('AspendoraUsersFlow_FlowStepsDocumentation');
        $this->metrics = ['nb_visits', 'nb_proceeded', 'nb_exits', 'exit_rate'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = null;
        $this->order = 41;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraUsersFlow_StepPage'));
        $view->config->addTranslation('nb_visits', 'Visits');
        $view->config->addTranslation('nb_proceeded', 'Proceeded');
        $view->config->addTranslation('nb_exits', 'Exited here');
        $view->config->addTranslation('exit_rate', 'Exit rate %');
        $view->config->columns_to_display = ['label', 'nb_visits', 'nb_proceeded', 'nb_exits', 'exit_rate'];
        $view->requestConfig->filter_limit = 50;
        $view->requestConfig->filter_sort_column = false;
    }
}
