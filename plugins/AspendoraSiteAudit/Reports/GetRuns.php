<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;

class GetRuns extends Base
{
    protected function init()
    {
        parent::init();
        $this->action = 'getRuns';
        $this->name = Piwik::translate('AspendoraSiteAudit_RunHistory');
        $this->documentation = Piwik::translate('AspendoraSiteAudit_RunsDocumentation');
        $this->metrics = ['pages_crawled', 'nb_errors', 'nb_warnings', 'nb_notices'];
        $this->defaultSortColumn = 'sort_order';
        $this->defaultSortOrderDesc = false;
        $this->order = 40;
    }

    public function configureView(ViewDataTable $view)
    {
        $this->baseView($view);
        $view->config->show_search = false;
        $view->config->addTranslation('label', Piwik::translate('AspendoraSiteAudit_Finished'));
        $view->config->addTranslation('status', Piwik::translate('AspendoraSiteAudit_RunStatus'));
        $view->config->addTranslation('pages_crawled', Piwik::translate('AspendoraSiteAudit_PagesCrawled'));
        $view->config->addTranslation('nb_errors', Piwik::translate('AspendoraSiteAudit_Errors'));
        $view->config->addTranslation('nb_warnings', Piwik::translate('AspendoraSiteAudit_Warnings'));
        $view->config->addTranslation('nb_notices', Piwik::translate('AspendoraSiteAudit_Notices'));
        $view->config->addTranslation('note', Piwik::translate('AspendoraSiteAudit_Note'));
        $view->config->columns_to_display = ['label', 'status', 'pages_crawled', 'nb_errors', 'nb_warnings', 'nb_notices', 'note'];
        $view->requestConfig->filter_sort_column = 'sort_order';
        $view->requestConfig->filter_sort_order = 'asc';
        $view->requestConfig->filter_limit = 26;
    }
}
