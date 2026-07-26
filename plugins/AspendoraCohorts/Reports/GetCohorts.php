<?php

namespace Piwik\Plugins\AspendoraCohorts\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetCohorts extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraCohorts';
        $this->action = 'getCohorts';
        $this->categoryId = 'General_Visitors';
        $this->subcategoryId = 'AspendoraCohorts_Cohorts';
        $this->name = Piwik::translate('AspendoraCohorts_WeeklyCohorts');
        $this->documentation = Piwik::translate('AspendoraCohorts_Documentation');
        $this->metrics = ['nb_new', 'w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7', 'w8'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = null;
        $this->order = 45;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraCohorts_CohortWeek'));
        $view->config->addTranslation('nb_new', 'New identities');
        for ($i = 1; $i <= 8; $i++) {
            $view->config->addTranslation('w' . $i, '+' . $i . 'w %');
        }
        $view->config->columns_to_display = ['label', 'nb_new', 'w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7', 'w8'];
        $view->requestConfig->filter_sort_column = false;
        $view->requestConfig->filter_limit = 20;
    }
}
