<?php

namespace Piwik\Plugins\AspendoraRollUp\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetOverview extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraRollUp';
        $this->action = 'getOverview';
        $this->categoryId = 'General_Visitors';
        $this->subcategoryId = 'AspendoraRollUp_AllProperties';
        $this->name = Piwik::translate('AspendoraRollUp_AllPropertiesOverview');
        $this->documentation = Piwik::translate('AspendoraRollUp_Documentation');
        $this->metrics = ['nb_visits', 'nb_identities', 'nb_pageviews', 'nb_events'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = null;
        $this->order = 5;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraRollUp_Property'));
        $view->config->addTranslation('nb_visits', 'Visits');
        $view->config->addTranslation('nb_identities', 'Unique identities');
        $view->config->addTranslation('nb_pageviews', 'Pageviews');
        $view->config->addTranslation('nb_events', 'Events');
        $view->config->columns_to_display = ['label', 'nb_visits', 'nb_identities', 'nb_pageviews', 'nb_events'];
        $view->requestConfig->filter_sort_column = false;
    }
}
