<?php

namespace Piwik\Plugins\AspendoraCompanies\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetCompanies extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraCompanies';
        $this->action = 'getCompanies';
        $this->categoryId = 'General_Visitors';
        $this->subcategoryId = 'AspendoraCompanies_Companies';
        $this->name = Piwik::translate('AspendoraCompanies_Companies');
        $this->documentation = Piwik::translate('AspendoraCompanies_ReportDocumentation');
        $this->metrics = ['nb_visits', 'nb_uniq_visitors', 'nb_actions'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_actions';
        $this->order = 31;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraCompanies_Company'));
        $view->config->addTranslation('nb_visits', Piwik::translate('General_ColumnNbVisits'));
        $view->config->addTranslation('nb_uniq_visitors', Piwik::translate('General_ColumnNbUniqVisitors'));
        $view->config->addTranslation('nb_actions', Piwik::translate('General_ColumnNbActions'));
        $view->config->addTranslation('last_seen', Piwik::translate('AspendoraCompanies_LastSeen'));
        $view->config->columns_to_display = ['label', 'nb_visits', 'nb_uniq_visitors', 'nb_actions', 'last_seen'];
        $view->requestConfig->filter_sort_column = 'nb_actions';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->requestConfig->filter_limit = 25;
    }
}
