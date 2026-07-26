<?php

namespace Piwik\Plugins\AspendoraAttribution\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class GetChannelAttribution extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraAttribution';
        $this->action = 'getChannelAttribution';
        $this->categoryId = 'Referrers_Referrers';
        $this->subcategoryId = 'AspendoraAttribution_Attribution';
        $this->name = Piwik::translate('AspendoraAttribution_ChannelAttribution');
        $this->documentation = Piwik::translate('AspendoraAttribution_Documentation');
        $this->metrics = ['last', 'last_non_direct', 'first', 'linear', 'position', 'time_decay'];
        $this->processedMetrics = [];
        $this->defaultSortColumn = 'last';
        $this->order = 25;
    }

    public function configureView(ViewDataTable $view)
    {
        $view->config->show_exclude_low_population = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraAttribution_Channel'));
        $view->config->addTranslation('last', 'Last');
        $view->config->addTranslation('last_non_direct', 'Last non-direct');
        $view->config->addTranslation('first', 'First');
        $view->config->addTranslation('linear', 'Linear');
        $view->config->addTranslation('position', 'Position 40/20/40');
        $view->config->addTranslation('time_decay', 'Time decay (7d)');
        $view->config->columns_to_display = ['label', 'last', 'last_non_direct', 'first', 'linear', 'position', 'time_decay'];
    }
}
