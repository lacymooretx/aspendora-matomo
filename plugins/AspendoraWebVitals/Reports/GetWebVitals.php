<?php

namespace Piwik\Plugins\AspendoraWebVitals\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetWebVitals extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraWebVitals';
        $this->action = 'getWebVitals';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraWebVitals_WebVitals';
        $this->name = Piwik::translate('AspendoraWebVitals_WebVitals');
        $this->documentation = Piwik::translate('AspendoraWebVitals_ReportDocumentation');
        $this->metrics = ['perf_score', 'lcp_s', 'cls', 'tbt_ms', 'fcp_s', 'si_s'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'perf_score';
        $this->order = 50;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraWebVitals_Page'));
        $view->config->addTranslation('perf_score', 'Perf Score');
        $view->config->addTranslation('lcp_s', 'LCP (s)');
        $view->config->addTranslation('cls', 'CLS');
        $view->config->addTranslation('tbt_ms', 'TBT (ms)');
        $view->config->addTranslation('fcp_s', 'FCP (s)');
        $view->config->addTranslation('si_s', 'Speed Index (s)');
        $view->config->columns_to_display = ['label', 'perf_score', 'lcp_s', 'cls', 'tbt_ms', 'fcp_s', 'si_s'];
        $view->requestConfig->filter_sort_column = 'perf_score';
        $view->requestConfig->filter_sort_order = 'asc';
        $view->requestConfig->filter_limit = 50;
    }
}
