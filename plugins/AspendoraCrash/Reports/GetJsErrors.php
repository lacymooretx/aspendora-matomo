<?php

namespace Piwik\Plugins\AspendoraCrash\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetJsErrors extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraCrash';
        $this->action = 'getJsErrors';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraCrash_JsErrors';
        $this->name = Piwik::translate('AspendoraCrash_JsErrors');
        $this->documentation = Piwik::translate('AspendoraCrash_ReportDocumentation');
        $this->metrics = ['nb_occurrences', 'nb_pages'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_occurrences';
        $this->order = 27;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraCrash_Error'));
        $view->config->addTranslation('nb_occurrences', Piwik::translate('AspendoraCrash_Occurrences'));
        $view->config->addTranslation('nb_pages', Piwik::translate('AspendoraCrash_Pages'));
        $view->config->addTranslation('last_seen', Piwik::translate('AspendoraCrash_LastSeen'));
        $view->config->columns_to_display = ['label', 'nb_occurrences', 'nb_pages', 'last_seen'];
        $view->requestConfig->filter_sort_column = 'nb_occurrences';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->requestConfig->filter_limit = 25;
        $view->config->disable_row_evolution = true;
    }
}
