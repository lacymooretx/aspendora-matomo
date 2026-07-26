<?php

namespace Piwik\Plugins\AspendoraFormAnalytics\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetForms extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraFormAnalytics';
        $this->action = 'getForms';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraFormAnalytics_Forms';
        $this->name = Piwik::translate('AspendoraFormAnalytics_FormsFunnel');
        $this->documentation = Piwik::translate('AspendoraFormAnalytics_FormsDocumentation');
        $this->metrics = ['nb_views', 'nb_starts', 'nb_submits', 'nb_abandons', 'start_rate', 'conversion'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_views';
        $this->order = 30;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraFormAnalytics_Form'));
        $view->config->addTranslation('nb_views', 'Views');
        $view->config->addTranslation('nb_starts', 'Starts');
        $view->config->addTranslation('nb_submits', 'Submits');
        $view->config->addTranslation('nb_abandons', 'Abandons');
        $view->config->addTranslation('start_rate', 'Start rate %');
        $view->config->addTranslation('conversion', 'Conversion %');
        $view->config->columns_to_display = ['label', 'nb_views', 'nb_starts', 'nb_submits', 'nb_abandons', 'start_rate', 'conversion'];
    }
}
