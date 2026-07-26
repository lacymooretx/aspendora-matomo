<?php

namespace Piwik\Plugins\AspendoraExperiments\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetExperiments extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraExperiments';
        $this->action = 'getExperiments';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraExperiments_Experiments';
        $this->name = Piwik::translate('AspendoraExperiments_Experiments');
        $this->documentation = Piwik::translate('AspendoraExperiments_ReportDocumentation');
        $this->metrics = ['nb_visits', 'nb_conversions'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = '';
        $this->order = 26;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_search = false;
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraExperiments_Variant'));
        $view->config->addTranslation('nb_visits', Piwik::translate('General_ColumnNbVisits'));
        $view->config->addTranslation('nb_conversions', Piwik::translate('AspendoraExperiments_Conversions'));
        $view->config->addTranslation('conversion_rate', Piwik::translate('AspendoraExperiments_ConversionRate'));
        $view->config->addTranslation('confidence', Piwik::translate('AspendoraExperiments_Confidence'));
        $view->config->columns_to_display = ['label', 'nb_visits', 'nb_conversions', 'conversion_rate', 'confidence'];
        $view->requestConfig->filter_sort_column = '';
        $view->requestConfig->filter_limit = 100;
        $view->config->disable_row_evolution = true;
    }
}
