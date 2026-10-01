<?php
/**
 * Aspendora Site Audit — orchestration: for each mapped site insert a run row, crawl, analyze,
 * store issues + pages, finalize counts, prune to the last KEEP_RUNS runs.
 *
 * Locking / scheduling rules (Matomo here has no cron; the scheduler is triggered by web requests and a
 * host cron runs `./console aspendora-audit:run` weekly):
 *   - A 'running' row older than 2 h is stale → marked 'failed' before anything else.
 *   - A 'running' row younger than 2 h is a lock → the site is skipped (the command can pass --force).
 *   - Scheduled task only: skip a site whose last 'ok' run finished less than 6 days ago.
 *
 * All timestamps are stored in UTC (Matomo convention).
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

use Piwik\Access;
use Piwik\Common;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

class Auditor
{
    public const LOCK_SECONDS = 7200;
    public const SCHEDULED_MIN_AGE_SECONDS = 6 * 86400;

    private LoggerInterface $logger;
    /** @var callable|null */
    private $progress;

    public function __construct(LoggerInterface $logger, ?callable $progress = null)
    {
        $this->logger = $logger;
        $this->progress = $progress;
    }

    /**
     * @param int|null $onlySite  restrict to one idSite (must be in ASPENDORA_AUDIT_SITE_MAP)
     * @param int|null $maxPages  override ASPENDORA_AUDIT_MAX_PAGES
     * @param bool     $scheduled apply the 6-day skip (scheduled task)
     * @param bool     $force     ignore the 'running' lock
     * @return array[] one summary per mapped site: idsite, start_url, status (ok|failed|skipped), reason,
     *                 idrun, pages, counts (per severity), by_code (distinct URLs per code), duration_s
     */
    public function runAll(?int $onlySite = null, ?int $maxPages = null, bool $scheduled = false, bool $force = false): array
    {
        @set_time_limit(0);
        $map = AspendoraSiteAudit::getSiteMap();
        if (!$map) {
            $this->logger->warning('AspendoraSiteAudit: ASPENDORA_AUDIT_SITE_MAP not set; nothing to audit');
            return [];
        }
        $existing = Access::doAsSuperUser(function () {
            return array_map('intval', SitesManagerAPI::getInstance()->getAllSitesId());
        });
        if ($onlySite !== null) {
            if (!isset($map[$onlySite])) {
                throw new \InvalidArgumentException("idSite $onlySite is not in ASPENDORA_AUDIT_SITE_MAP");
            }
            $map = [$onlySite => $map[$onlySite]];
        }
        $max = $maxPages ?: AspendoraSiteAudit::getMaxPages();
        $out = [];
        foreach ($map as $idSite => $startUrl) {
            if (!in_array($idSite, $existing, true)) {
                $this->logger->warning('AspendoraSiteAudit: idSite {s} in the site map does not exist; skipping', ['s' => $idSite]);
                $out[] = $this->skipped($idSite, $startUrl, 'site does not exist');
                continue;
            }
            $out[] = $this->runSite($idSite, $startUrl, $max, $scheduled, $force);
        }
        return $out;
    }

    public function runSite(int $idSite, string $startUrl, int $maxPages, bool $scheduled = false, bool $force = false): array
    {
        $runTable = Common::prefixTable(AspendoraSiteAudit::TABLE_RUN);
        $now = time();

        $stale = Db::query(
            "UPDATE `$runTable` SET status = 'failed', finished_at = ?, error_text = 'interrupted: still running after 2 hours'
             WHERE idsite = ? AND status = 'running' AND started_at < ?",
            [gmdate('Y-m-d H:i:s', $now), $idSite, gmdate('Y-m-d H:i:s', $now - self::LOCK_SECONDS)]
        )->rowCount();
        if ($stale) {
            $this->logger->warning('AspendoraSiteAudit: marked {n} stale running audit(s) of site {s} as failed', ['n' => $stale, 's' => $idSite]);
        }

        if (!$force) {
            $running = Db::fetchOne(
                "SELECT started_at FROM `$runTable` WHERE idsite = ? AND status = 'running' ORDER BY idrun DESC LIMIT 1",
                [$idSite]
            );
            if ($running) {
                return $this->skipped($idSite, $startUrl, "another audit is running (started $running UTC)");
            }
        }
        if ($scheduled) {
            $lastOk = Db::fetchOne(
                "SELECT MAX(finished_at) FROM `$runTable` WHERE idsite = ? AND status = 'ok'",
                [$idSite]
            );
            if ($lastOk && strtotime($lastOk . ' UTC') > $now - self::SCHEDULED_MIN_AGE_SECONDS) {
                return $this->skipped($idSite, $startUrl, "last successful audit finished $lastOk UTC (< 6 days ago)");
            }
        }

        Db::query(
            "INSERT INTO `$runTable` (idsite, start_url, started_at, status) VALUES (?, ?, ?, 'running')",
            [$idSite, mb_substr($startUrl, 0, 2048), gmdate('Y-m-d H:i:s')]
        );
        $idRun = (int) Db::get()->lastInsertId();
        $this->logger->info('AspendoraSiteAudit: run {r} started for site {s} ({u}, max {m} pages)',
            ['r' => $idRun, 's' => $idSite, 'u' => $startUrl, 'm' => $maxPages]);

        try {
            $crawler = new Crawler($startUrl, $maxPages, 4, function ($msg) use ($idSite) {
                $this->say("site $idSite: $msg");
            });
            $crawl = $crawler->crawl();
            $issues = (new Analyzer($crawl, AspendoraSiteAudit::getOrphanOk($idSite)))->analyze();
            $counts = Analyzer::countBySeverity($issues);

            $this->storeIssues($idRun, $idSite, $issues);
            $this->storePages($idRun, $idSite, $crawl['pages']);
            Db::query(
                "UPDATE `$runTable` SET finished_at = ?, pages_crawled = ?, nb_errors = ?, nb_warnings = ?, nb_notices = ?,
                        status = 'ok', error_text = ? WHERE idrun = ?",
                [gmdate('Y-m-d H:i:s'), count($crawl['pages']), $counts['error'], $counts['warning'], $counts['notice'],
                 $crawl['truncated'] ? "stopped at max pages ($maxPages)" : null, $idRun]
            );
            $this->prune($idSite);
            $this->logger->info('AspendoraSiteAudit: run {r} for site {s} finished: {p} URLs, {e} errors, {w} warnings, {n} notices', [
                'r' => $idRun, 's' => $idSite, 'p' => count($crawl['pages']),
                'e' => $counts['error'], 'w' => $counts['warning'], 'n' => $counts['notice'],
            ]);
            return [
                'idsite' => $idSite, 'start_url' => $startUrl, 'status' => 'ok', 'reason' => null, 'idrun' => $idRun,
                'pages' => count($crawl['pages']), 'counts' => $counts, 'by_code' => Analyzer::countByCode($issues),
                'duration_s' => $crawl['duration_s'], 'truncated' => $crawl['truncated'],
            ];
        } catch (\Throwable $e) {
            Db::query(
                "UPDATE `$runTable` SET status = 'failed', finished_at = ?, error_text = ? WHERE idrun = ?",
                [gmdate('Y-m-d H:i:s'), mb_substr(get_class($e) . ': ' . $e->getMessage(), 0, 2000), $idRun]
            );
            $this->logger->error('AspendoraSiteAudit: run {r} for site {s} failed: {m}', [
                'r' => $idRun, 's' => $idSite, 'm' => $e->getMessage(),
            ]);
            return [
                'idsite' => $idSite, 'start_url' => $startUrl, 'status' => 'failed', 'reason' => $e->getMessage(),
                'idrun' => $idRun, 'pages' => 0, 'counts' => null, 'by_code' => [], 'duration_s' => null,
            ];
        }
    }

    private function storeIssues(int $idRun, int $idSite, array $issues): void
    {
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_ISSUE);
        foreach (array_chunk($issues, 200) as $chunk) {
            $values = [];
            $binds = [];
            foreach ($chunk as $i) {
                $values[] = '(?,?,?,?,?,?,?)';
                array_push($binds, $idRun, $idSite, $i['code'], $i['severity'],
                    mb_substr($i['url'], 0, 2048), sha1($i['url']), mb_substr($i['detail'], 0, 20000));
            }
            Db::query("INSERT INTO `$table` (idrun, idsite, code, severity, url, url_hash, detail) VALUES "
                . implode(',', $values), $binds);
        }
    }

    private function storePages(int $idRun, int $idSite, array $pages): void
    {
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_PAGE);
        $cut = fn ($v, int $n) => $v === null ? null : mb_substr((string) $v, 0, $n);
        $bool = fn ($v) => $v === null ? null : ($v ? 1 : 0);
        foreach (array_chunk($pages, 100) as $chunk) {
            $values = [];
            $binds = [];
            foreach ($chunk as $p) {
                $values[] = '(' . implode(',', array_fill(0, 22, '?')) . ')';
                array_push($binds, $idRun, $idSite, $cut($p['url'], 2048), sha1($p['url']), (int) $p['status'],
                    $cut($p['error'], 255), $cut($p['location'], 2048), $cut($p['content_type'], 100),
                    (int) $p['response_ms'], $p['depth'], $p['in_sitemap'] ? 1 : 0, $cut($p['title'], 500),
                    $cut($p['description'], 5000), $cut($p['canonical'], 2048), $cut($p['meta_robots'], 255),
                    $cut($p['x_robots_tag'], 255), $p['h1_count'], $p['word_count'], $bool($p['og_title']),
                    $bool($p['og_image']), count($p['links']), $p['nofollow'] ? 1 : 0);
            }
            Db::query("INSERT INTO `$table` (idrun, idsite, url, url_hash, status, error_text, location, content_type,
                    response_ms, depth, in_sitemap, title, description, canonical, meta_robots, x_robots_tag, h1_count,
                    word_count, og_title, og_image, nb_links, nofollow) VALUES " . implode(',', $values)
                . ' ON DUPLICATE KEY UPDATE status = VALUES(status)', $binds);
        }
    }

    /** Keep the newest KEEP_RUNS runs per site (any status); delete older runs with their issues and pages. */
    private function prune(int $idSite): void
    {
        $runTable = Common::prefixTable(AspendoraSiteAudit::TABLE_RUN);
        $old = Db::fetchAll(
            "SELECT idrun FROM `$runTable` WHERE idsite = ? ORDER BY idrun DESC LIMIT " . AspendoraSiteAudit::KEEP_RUNS . ', 1000000',
            [$idSite]
        );
        $ids = array_map('intval', array_column($old, 'idrun'));
        if (!$ids) {
            return;
        }
        $in = implode(',', $ids);
        Db::query('DELETE FROM `' . Common::prefixTable(AspendoraSiteAudit::TABLE_ISSUE) . "` WHERE idrun IN ($in)");
        Db::query('DELETE FROM `' . Common::prefixTable(AspendoraSiteAudit::TABLE_PAGE) . "` WHERE idrun IN ($in)");
        Db::query("DELETE FROM `$runTable` WHERE idrun IN ($in)");
        $this->logger->info('AspendoraSiteAudit: pruned {n} old run(s) of site {s}', ['n' => count($ids), 's' => $idSite]);
    }

    private function skipped(int $idSite, string $startUrl, string $reason): array
    {
        $this->logger->info('AspendoraSiteAudit: site {s} skipped: {r}', ['s' => $idSite, 'r' => $reason]);
        return ['idsite' => $idSite, 'start_url' => $startUrl, 'status' => 'skipped', 'reason' => $reason,
                'idrun' => null, 'pages' => 0, 'counts' => null, 'by_code' => [], 'duration_s' => null];
    }

    private function say(string $msg): void
    {
        $this->logger->info('AspendoraSiteAudit: ' . $msg);
        if ($this->progress) {
            ($this->progress)($msg);
        }
    }
}
