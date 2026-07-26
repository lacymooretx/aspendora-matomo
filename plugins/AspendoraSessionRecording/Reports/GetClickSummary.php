<?php

namespace Piwik\Plugins\AspendoraSessionRecording\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetClickSummary extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraSessionRecording';
        $this->action = 'getClickSummary';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraSessionRecording_Engagement';
        $this->name = Piwik::translate('AspendoraSessionRecording_ClickSummary');
        $this->documentation = Piwik::translate('AspendoraSessionRecording_ClickDocs');
        $this->metrics = ['nb_clicks', 'avg_y_px'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_clicks';
        $this->order = 71;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', 'Page');
        $view->config->addTranslation('nb_clicks', 'Clicks');
        $view->config->addTranslation('avg_y_px', 'Avg click depth (px)');
        $view->config->columns_to_display = ['label', 'nb_clicks', 'avg_y_px'];
    }
}
