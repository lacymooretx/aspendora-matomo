<?php

namespace Piwik\Plugins\AspendoraMediaAnalytics\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetMedia extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraMediaAnalytics';
        $this->action = 'getMedia';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraMediaAnalytics_Media';
        $this->name = Piwik::translate('AspendoraMediaAnalytics_MediaEngagement');
        $this->documentation = Piwik::translate('AspendoraMediaAnalytics_Documentation');
        $this->metrics = ['nb_plays', 'nb_p50', 'nb_finishes', 'completion_rate', 'watch_minutes'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'nb_plays';
        $this->order = 60;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraMediaAnalytics_MediaTitle'));
        $view->config->addTranslation('nb_plays', 'Plays');
        $view->config->addTranslation('nb_p50', 'Reached 50%');
        $view->config->addTranslation('nb_finishes', 'Finishes');
        $view->config->addTranslation('completion_rate', 'Completion %');
        $view->config->addTranslation('watch_minutes', 'Watch time (min)');
        $view->config->columns_to_display = ['label', 'nb_plays', 'nb_p50', 'nb_finishes', 'completion_rate', 'watch_minutes'];
    }
}
