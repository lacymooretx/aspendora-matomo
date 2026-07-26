<?php

namespace Piwik\Plugins\AspendoraSessionRecording\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetScrollDepth extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraSessionRecording';
        $this->action = 'getScrollDepth';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraSessionRecording_Engagement';
        $this->name = Piwik::translate('AspendoraSessionRecording_ScrollDepth');
        $this->documentation = Piwik::translate('AspendoraSessionRecording_ScrollDocs');
        $this->metrics = ['nb_views', 'avg_depth', 'reached_50', 'reached_75', 'reached_end'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_views';
        $this->order = 70;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', 'Page');
        $view->config->addTranslation('nb_views', 'Tracked views');
        $view->config->addTranslation('avg_depth', 'Avg depth %');
        $view->config->addTranslation('reached_50', 'Reached 50%');
        $view->config->addTranslation('reached_75', 'Reached 75%');
        $view->config->addTranslation('reached_end', 'Reached end');
        $view->config->columns_to_display = ['label', 'nb_views', 'avg_depth', 'reached_50', 'reached_75', 'reached_end'];
    }
}
