<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;

class GetIssues extends Base
{
    protected function init()
    {
        parent::init();
        $this->action = 'getIssues';
        $this->name = Piwik::translate('AspendoraSiteAudit_AllIssues');
        $this->documentation = Piwik::translate('AspendoraSiteAudit_IssuesDocumentation');
        $this->metrics = ['is_new'];
        $this->defaultSortColumn = 'sort_order';
        $this->defaultSortOrderDesc = false;
        $this->order = 20;
    }

    public function configureView(ViewDataTable $view)
    {
        $this->baseView($view);
        $view->config->addTranslation('label', Piwik::translate('AspendoraSiteAudit_Url'));
        $view->config->addTranslation('issue', Piwik::translate('AspendoraSiteAudit_Issue'));
        $view->config->addTranslation('severity', Piwik::translate('AspendoraSiteAudit_Severity'));
        $view->config->addTranslation('detail', Piwik::translate('AspendoraSiteAudit_Detail'));
        $view->config->addTranslation('is_new', Piwik::translate('AspendoraSiteAudit_IsNew'));
        $view->config->columns_to_display = ['label', 'issue', 'severity', 'detail', 'is_new'];
        $view->requestConfig->filter_sort_column = 'sort_order';
        $view->requestConfig->filter_sort_order = 'asc';
        $view->requestConfig->filter_limit = 100;
        $this->addRunFooter($view);
    }
}
