<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;

class GetIssueSummary extends Base
{
    protected function init()
    {
        parent::init();
        $this->action = 'getIssueSummary';
        $this->name = Piwik::translate('AspendoraSiteAudit_IssueSummary');
        $this->documentation = Piwik::translate('AspendoraSiteAudit_IssueSummaryDocumentation');
        $this->metrics = ['nb_urls', 'nb_new', 'nb_fixed'];
        $this->defaultSortColumn = 'sort_order';
        $this->defaultSortOrderDesc = false;
        $this->order = 10;
    }

    public function configureView(ViewDataTable $view)
    {
        $this->baseView($view);
        $view->config->addTranslation('label', Piwik::translate('AspendoraSiteAudit_Issue'));
        $view->config->addTranslation('severity', Piwik::translate('AspendoraSiteAudit_Severity'));
        $view->config->addTranslation('nb_urls', Piwik::translate('AspendoraSiteAudit_Urls'));
        $view->config->addTranslation('nb_new', Piwik::translate('AspendoraSiteAudit_New'));
        $view->config->addTranslation('nb_fixed', Piwik::translate('AspendoraSiteAudit_Fixed'));
        $view->config->columns_to_display = ['label', 'severity', 'nb_urls', 'nb_new', 'nb_fixed'];
        $view->requestConfig->filter_sort_column = 'sort_order';
        $view->requestConfig->filter_sort_order = 'asc';
        $view->requestConfig->filter_limit = 50;
        $this->addRunFooter($view);
    }
}
