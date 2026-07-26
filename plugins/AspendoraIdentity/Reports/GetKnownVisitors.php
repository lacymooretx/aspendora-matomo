<?php

namespace Piwik\Plugins\AspendoraIdentity\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetKnownVisitors extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraIdentity';
        $this->action = 'getKnownVisitors';
        $this->categoryId = 'General_Visitors';
        $this->subcategoryId = 'AspendoraIdentity_KnownVisitors';
        $this->name = Piwik::translate('AspendoraIdentity_KnownVisitors');
        $this->documentation = Piwik::translate('AspendoraIdentity_ReportDocumentation');
        $this->metrics = ['nb_visits', 'nb_actions', 'lead_score'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'lead_score';
        $this->order = 30;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraIdentity_Visitor'));
        $view->config->addTranslation('email', Piwik::translate('AspendoraIdentity_Email'));
        $view->config->addTranslation('company', Piwik::translate('AspendoraIdentity_Company'));
        $view->config->addTranslation('nb_visits', Piwik::translate('General_ColumnNbVisits'));
        $view->config->addTranslation('nb_actions', Piwik::translate('General_ColumnNbActions'));
        $view->config->addTranslation('lead_score', Piwik::translate('AspendoraIdentity_LeadScore'));
        $view->config->addTranslation('hot_intent', Piwik::translate('AspendoraIdentity_HotIntent'));
        $view->config->addTranslation('last_seen', Piwik::translate('AspendoraIdentity_LastSeen'));
        $view->config->columns_to_display = [
            'label', 'email', 'company', 'nb_visits', 'nb_actions', 'lead_score', 'hot_intent', 'last_seen',
        ];
        $view->requestConfig->filter_sort_column = 'lead_score';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->requestConfig->filter_limit = 25;
    }
}
