<?php

namespace Piwik\Plugins\AspendoraSearchKeywords\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetBingCrawlIssues extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraSearchKeywords';
        $this->action = 'getBingCrawlIssues';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraSearchKeywords_BingCrawl';
        $this->name = Piwik::translate('AspendoraSearchKeywords_BingCrawlIssuesReport');
        $this->documentation = Piwik::translate('AspendoraSearchKeywords_BingCrawlIssuesDocumentation');
        $this->metrics = ['http_code', 'in_links'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'in_links';
        $this->order = 61;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraSearchKeywords_Url'));
        $view->config->addTranslation('http_code', 'HTTP Code');
        $view->config->addTranslation('in_links', 'Inbound Links');
        $view->config->addTranslation('issues_text', Piwik::translate('AspendoraSearchKeywords_Issues'));
        $view->config->columns_to_display = ['label', 'http_code', 'in_links', 'issues_text'];
        $view->requestConfig->filter_sort_column = 'in_links';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->requestConfig->filter_limit = 50;
    }
}
