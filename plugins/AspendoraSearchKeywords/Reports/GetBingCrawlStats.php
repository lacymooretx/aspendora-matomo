<?php

namespace Piwik\Plugins\AspendoraSearchKeywords\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetBingCrawlStats extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraSearchKeywords';
        $this->action = 'getBingCrawlStats';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraSearchKeywords_BingCrawl';
        $this->name = Piwik::translate('AspendoraSearchKeywords_BingCrawlStatsReport');
        $this->documentation = Piwik::translate('AspendoraSearchKeywords_BingCrawlStatsDocumentation');
        $this->metrics = ['crawled_pages', 'in_index', 'in_links', 'code_2xx', 'code_301', 'code_302',
            'code_4xx', 'code_5xx', 'crawl_errors', 'blocked_by_robots'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'label';
        $this->order = 60;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraSearchKeywords_Day'));
        $view->config->addTranslation('crawled_pages', 'Crawled');
        $view->config->addTranslation('in_index', 'In Index');
        $view->config->addTranslation('in_links', 'Inbound Links');
        $view->config->addTranslation('code_2xx', '2xx');
        $view->config->addTranslation('code_301', '301');
        $view->config->addTranslation('code_302', '302');
        $view->config->addTranslation('code_4xx', '4xx');
        $view->config->addTranslation('code_5xx', '5xx');
        $view->config->addTranslation('crawl_errors', 'Crawl Errors');
        $view->config->addTranslation('blocked_by_robots', 'Blocked (robots.txt)');
        $view->config->columns_to_display = ['label', 'crawled_pages', 'in_index', 'in_links', 'code_2xx',
            'code_301', 'code_302', 'code_4xx', 'code_5xx', 'crawl_errors', 'blocked_by_robots'];
        $view->requestConfig->filter_sort_column = 'label';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->requestConfig->filter_limit = 31;
    }
}
