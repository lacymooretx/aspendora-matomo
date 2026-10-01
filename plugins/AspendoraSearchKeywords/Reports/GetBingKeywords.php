<?php

namespace Piwik\Plugins\AspendoraSearchKeywords\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetBingKeywords extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraSearchKeywords';
        $this->action = 'getBingKeywords';
        $this->categoryId = 'Referrers_Referrers';
        $this->subcategoryId = 'AspendoraSearchKeywords_BingKeywords';
        $this->name = Piwik::translate('AspendoraSearchKeywords_BingKeywordsReport');
        $this->documentation = Piwik::translate('AspendoraSearchKeywords_BingKeywordsDocumentation');
        $this->metrics = ['nb_clicks', 'nb_impressions', 'ctr_pct', 'avg_position'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_clicks';
        $this->order = 21;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraSearchKeywords_Keyword'));
        $view->config->addTranslation('nb_clicks', Piwik::translate('AspendoraSearchKeywords_Clicks'));
        $view->config->addTranslation('nb_impressions', Piwik::translate('AspendoraSearchKeywords_Impressions'));
        $view->config->addTranslation('ctr_pct', 'CTR %');
        $view->config->addTranslation('avg_position', Piwik::translate('AspendoraSearchKeywords_AvgPosition'));
        $view->config->columns_to_display = ['label', 'nb_clicks', 'nb_impressions', 'ctr_pct', 'avg_position'];
        $view->requestConfig->filter_sort_column = 'nb_clicks';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->requestConfig->filter_limit = 25;
    }
}
