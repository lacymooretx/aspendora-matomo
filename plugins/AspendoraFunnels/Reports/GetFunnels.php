<?php

namespace Piwik\Plugins\AspendoraFunnels\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetFunnels extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraFunnels';
        $this->action = 'getFunnels';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraFunnels_Funnels';
        $this->name = Piwik::translate('AspendoraFunnels_Funnels');
        $this->documentation = Piwik::translate('AspendoraFunnels_ReportDocumentation');
        $this->metrics = ['nb_visits', 'nb_dropped'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = '';
        $this->order = 25;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraFunnels_Step'));
        $view->config->addTranslation('nb_visits', Piwik::translate('AspendoraFunnels_Reached'));
        $view->config->addTranslation('step_rate', Piwik::translate('AspendoraFunnels_StepRate'));
        $view->config->addTranslation('funnel_rate', Piwik::translate('AspendoraFunnels_FunnelRate'));
        $view->config->addTranslation('nb_dropped', Piwik::translate('AspendoraFunnels_Dropped'));
        $view->config->columns_to_display = ['label', 'nb_visits', 'step_rate', 'funnel_rate', 'nb_dropped'];
        $view->requestConfig->filter_sort_column = '';
        $view->requestConfig->filter_limit = 100;
        $view->config->disable_row_evolution = true;
    }
}
