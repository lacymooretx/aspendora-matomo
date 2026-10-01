<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;

class GetPages extends Base
{
    protected function init()
    {
        parent::init();
        $this->action = 'getPages';
        $this->name = Piwik::translate('AspendoraSiteAudit_CrawledPages');
        $this->documentation = Piwik::translate('AspendoraSiteAudit_PagesDocumentation');
        $this->metrics = ['status', 'response_ms', 'depth', 'title_length', 'desc_length', 'h1_count', 'word_count', 'nb_links'];
        $this->defaultSortColumn = 'response_ms';
        $this->order = 30;
    }

    public function configureView(ViewDataTable $view)
    {
        $this->baseView($view);
        $view->config->addTranslation('label', Piwik::translate('AspendoraSiteAudit_Url'));
        $view->config->addTranslation('status', Piwik::translate('AspendoraSiteAudit_HttpStatus'));
        $view->config->addTranslation('response_ms', Piwik::translate('AspendoraSiteAudit_ResponseMs'));
        $view->config->addTranslation('depth', Piwik::translate('AspendoraSiteAudit_Depth'));
        $view->config->addTranslation('in_sitemap', Piwik::translate('AspendoraSiteAudit_InSitemap'));
        $view->config->addTranslation('indexable', Piwik::translate('AspendoraSiteAudit_Indexable'));
        $view->config->addTranslation('title_length', Piwik::translate('AspendoraSiteAudit_TitleLength'));
        $view->config->addTranslation('desc_length', Piwik::translate('AspendoraSiteAudit_DescLength'));
        $view->config->addTranslation('h1_count', 'H1');
        $view->config->addTranslation('word_count', Piwik::translate('AspendoraSiteAudit_Words'));
        $view->config->addTranslation('nb_links', Piwik::translate('AspendoraSiteAudit_InternalLinks'));
        $view->config->columns_to_display = ['label', 'status', 'response_ms', 'depth', 'in_sitemap', 'indexable',
            'title_length', 'desc_length', 'h1_count', 'word_count', 'nb_links'];
        $view->requestConfig->filter_sort_column = 'response_ms';
        $view->requestConfig->filter_sort_order = 'desc';
        $view->requestConfig->filter_limit = 100;
        $this->addRunFooter($view);
    }
}
