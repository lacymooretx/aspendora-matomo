<?php

namespace Piwik\Plugins\AspendoraFormAnalytics\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetFormFields extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraFormAnalytics';
        $this->action = 'getFormFields';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraFormAnalytics_Forms';
        $this->name = Piwik::translate('AspendoraFormAnalytics_FieldPerformance');
        $this->documentation = Piwik::translate('AspendoraFormAnalytics_FieldsDocumentation');
        $this->metrics = ['nb_interactions', 'avg_seconds', 'nb_abandons'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_interactions';
        $this->order = 31;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraFormAnalytics_Field'));
        $view->config->addTranslation('nb_interactions', 'Interactions');
        $view->config->addTranslation('avg_seconds', 'Avg time (s)');
        $view->config->addTranslation('nb_abandons', 'Last field before abandon');
        $view->config->columns_to_display = ['label', 'nb_interactions', 'avg_seconds', 'nb_abandons'];
    }
}
