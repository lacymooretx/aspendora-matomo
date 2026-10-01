<?php
/**
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit\Reports;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\AspendoraSiteAudit\RunRepository;

/** Shared settings: all Site Audit reports live in Behaviour (General_Actions) → Site Audit. */
abstract class Base extends Report
{
    protected function init()
    {
        parent::init();
        $this->module = 'AspendoraSiteAudit';
        $this->categoryId = 'General_Actions';
        $this->subcategoryId = 'AspendoraSiteAudit_SiteAudit';
        $this->processedMetrics = [];
    }

    protected function baseView(ViewDataTable $view): void
    {
        $view->config->show_search = true;
        $view->config->show_exclude_low_population = false;
        $view->config->show_table_all_columns = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;
    }

    /** Footer line naming the crawl the report shows ("Crawl finished … · N URLs · start URL"). */
    protected function addRunFooter(ViewDataTable $view): void
    {
        try {
            $idSite = Common::getRequestVar('idSite', 0, 'int');
            $period = Common::getRequestVar('period', 'day', 'string');
            $date = Common::getRequestVar('date', 'today', 'string');
            if (!$idSite) {
                return;
            }
            $run = RunRepository::latestOkRun($idSite, RunRepository::periodEnd($period, $date));
            $view->config->show_footer_message = $run
                ? Piwik::translate('AspendoraSiteAudit_RunFooter', [
                    RunRepository::formatTime($run['finished_at'], $idSite),
                    (int) $run['pages_crawled'],
                    $run['start_url'],
                ])
                : Piwik::translate('AspendoraSiteAudit_NoRunYet');
        } catch (\Throwable $e) {
            // Footer is informational only.
        }
    }
}
