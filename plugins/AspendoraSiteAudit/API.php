<?php
/**
 * Aspendora Site Audit API. Reads stored crawl runs; never crawls.
 *
 * Which run is shown: the latest run with status 'ok' that finished on or before the END date of the
 * requested period. "new" = URL has this issue now but not in the previous ok run; "fixed" = had it in
 * the previous ok run, not in this one (both 0 when there is no previous run).
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    private const MAX_ISSUE_ROWS = 10000;

    /** One row per issue type: distinct URLs affected, new and fixed since the previous run. */
    public function getIssueSummary($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $dt = new DataTable();
        $run = RunRepository::latestOkRun($idSite, RunRepository::periodEnd($period, $date));
        if (!$run) {
            return $dt;
        }
        $prev = RunRepository::previousOkRun($run);
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_ISSUE);
        $count = fn (int $idRun) => array_column(Db::fetchAll(
            "SELECT code, COUNT(DISTINCT url_hash) AS n FROM `$table` WHERE idrun = ? GROUP BY code", [$idRun]
        ), 'n', 'code');
        $diff = fn (int $a, int $b) => array_column(Db::fetchAll(
            "SELECT i.code, COUNT(DISTINCT i.url_hash) AS n FROM `$table` i
             WHERE i.idrun = ? AND NOT EXISTS (
                 SELECT 1 FROM `$table` p WHERE p.idrun = ? AND p.code = i.code AND p.url_hash = i.url_hash)
             GROUP BY i.code", [$a, $b]
        ), 'n', 'code');

        $now = $count((int) $run['idrun']);
        $new = $fixed = [];
        $codes = array_keys($now);
        if ($prev) {
            $new = $diff((int) $run['idrun'], (int) $prev['idrun']);
            $fixed = $diff((int) $prev['idrun'], (int) $run['idrun']);
            $codes = array_unique(array_merge($codes, array_keys($fixed)));
        }
        $rows = [];
        foreach ($codes as $code) {
            $severity = Analyzer::ISSUES[$code][0] ?? 'notice';
            $rows[] = [
                'label'    => RunRepository::issueName($code),
                'code'     => $code,
                'severity' => RunRepository::severityName($severity),
                'nb_urls'  => (int) ($now[$code] ?? 0),
                'nb_new'   => (int) ($new[$code] ?? 0),
                'nb_fixed' => (int) ($fixed[$code] ?? 0),
                '_sev'     => Analyzer::SEVERITY_ORDER[$severity] ?? 9,
            ];
        }
        usort($rows, fn ($a, $b) => [$a['_sev'], -$a['nb_urls'], -$a['nb_fixed'], $a['code']]
            <=> [$b['_sev'], -$b['nb_urls'], -$b['nb_fixed'], $b['code']]);
        foreach ($rows as $i => $r) {
            unset($r['_sev']);
            $r['sort_order'] = $i + 1;
            $dt->addRowFromSimpleArray($r);
        }
        $dt->setMetadata('site_audit_run', $this->runMetadata($run, $prev));
        return $dt;
    }

    /** Flat list of every issue in the run: label = URL. Optional $code filters to one issue type. */
    public function getIssues($idSite, $period, $date, $code = '')
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $dt = new DataTable();
        $run = RunRepository::latestOkRun($idSite, RunRepository::periodEnd($period, $date));
        if (!$run) {
            return $dt;
        }
        $prev = RunRepository::previousOkRun($run);
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_ISSUE);
        $where = 'idrun = ?';
        $binds = [(int) $run['idrun']];
        if ($code !== '' && $code !== null) {
            $where .= ' AND code = ?';
            $binds[] = (string) $code;
        }
        $prevKeys = [];
        if ($prev) {
            foreach (Db::fetchAll("SELECT DISTINCT code, url_hash FROM `$table` WHERE idrun = ?", [(int) $prev['idrun']]) as $p) {
                $prevKeys[$p['code'] . ' ' . $p['url_hash']] = true;
            }
        }
        $rows = Db::fetchAll(
            "SELECT code, severity, url, url_hash, detail FROM `$table` WHERE $where
             ORDER BY FIELD(severity, 'error', 'warning', 'notice'), code, url LIMIT " . self::MAX_ISSUE_ROWS,
            $binds
        );
        foreach ($rows as $i => $r) {
            $dt->addRowFromSimpleArray([
                'label'      => $r['url'],
                'issue'      => RunRepository::issueName($r['code']),
                'code'       => $r['code'],
                'severity'   => RunRepository::severityName($r['severity']),
                'detail'     => (string) $r['detail'],
                'is_new'     => $prev && !isset($prevKeys[$r['code'] . ' ' . $r['url_hash']]) ? 1 : 0,
                'sort_order' => $i + 1,
            ]);
        }
        $dt->setMetadata('site_audit_run', $this->runMetadata($run, $prev));
        return $dt;
    }

    /** Every crawled URL of the run with its status and on-page fields. */
    public function getPages($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $dt = new DataTable();
        $run = RunRepository::latestOkRun($idSite, RunRepository::periodEnd($period, $date));
        if (!$run) {
            return $dt;
        }
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_PAGE);
        foreach (Db::fetchAll("SELECT * FROM `$table` WHERE idrun = ? ORDER BY url", [(int) $run['idrun']]) as $p) {
            $isHtml = (int) $p['status'] === 200 && $p['h1_count'] !== null; // h1_count is only set for parsed HTML
            $rec = ['url' => $p['url'], 'status' => (int) $p['status'], 'is_html' => $isHtml,
                    'meta_robots' => $p['meta_robots'], 'x_robots_tag' => $p['x_robots_tag'], 'canonical' => $p['canonical']];
            $dt->addRowFromSimpleArray([
                'label'        => $p['url'],
                'status'       => (int) $p['status'],
                'response_ms'  => (int) $p['response_ms'],
                'depth'        => $p['depth'] === null ? '' : (int) $p['depth'],
                'in_sitemap'   => (int) $p['in_sitemap'],
                'indexable'    => Analyzer::isIndexable($rec) ? 1 : 0,
                'title'        => (string) $p['title'],
                'title_length' => $p['title'] === null ? 0 : mb_strlen($p['title']),
                'desc_length'  => $p['description'] === null ? 0 : mb_strlen($p['description']),
                'h1_count'     => (int) $p['h1_count'],
                'word_count'   => (int) $p['word_count'],
                'nb_links'     => (int) $p['nb_links'],
                'location'     => (string) $p['location'],
                'content_type' => (string) $p['content_type'],
            ]);
        }
        $dt->setMetadata('site_audit_run', $this->runMetadata($run, null));
        return $dt;
    }

    /** Run history (newest first) for runs started on or before the period end date. */
    public function getRuns($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $end = RunRepository::periodEnd($period, $date);
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_RUN);
        $dt = new DataTable();
        $rows = Db::fetchAll(
            "SELECT * FROM `$table` WHERE idsite = ? AND started_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY idrun DESC",
            [$idSite, $end]
        );
        foreach ($rows as $i => $r) {
            $dt->addRowFromSimpleArray([
                'label'         => $r['finished_at']
                    ? RunRepository::formatTime($r['finished_at'], $idSite)
                    : RunRepository::formatTime($r['started_at'], $idSite) . ' (started)',
                'status'        => $r['status'],
                'pages_crawled' => (int) $r['pages_crawled'],
                'nb_errors'     => (int) $r['nb_errors'],
                'nb_warnings'   => (int) $r['nb_warnings'],
                'nb_notices'    => (int) $r['nb_notices'],
                'note'          => (string) $r['error_text'],
                'idrun'         => (int) $r['idrun'],
                'sort_order'    => $i + 1,
            ]);
        }
        return $dt;
    }

    private function runMetadata(array $run, ?array $prev): array
    {
        return [
            'idrun'           => (int) $run['idrun'],
            'start_url'       => $run['start_url'],
            'started_at_utc'  => $run['started_at'],
            'finished_at_utc' => $run['finished_at'],
            'pages_crawled'   => (int) $run['pages_crawled'],
            'previous_idrun'  => $prev ? (int) $prev['idrun'] : null,
        ];
    }
}
