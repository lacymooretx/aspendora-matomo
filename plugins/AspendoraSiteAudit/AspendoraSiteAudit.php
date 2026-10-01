<?php
/**
 * Aspendora Site Audit — weekly SEO/technical crawl of our websites (self-hosted replacement for
 * Ahrefs Site Audit). Crawler.php / Analyzer.php / Url.php are framework-independent; Auditor.php,
 * API.php, Reports/ and the task/command are the Matomo glue.
 *
 * Configuration comes from environment variables (set in docker compose, never in git):
 *   ASPENDORA_AUDIT_SITE_MAP   — JSON map of Matomo idSite → start URL; sites not in the map are skipped,
 *                                e.g. {"1":"https://www.aspendora.com/","2":"https://aspendoracompliance.com/"}
 *   ASPENDORA_AUDIT_MAX_PAGES  — crawl limit per site (default 2000)
 *   ASPENDORA_AUDIT_ORPHAN_OK  — optional JSON map idSite → list of path regexes (no delimiters) for pages
 *                                that are intentionally unlinked, e.g. {"1":["^/(sitting-duck|aspirin)/$"]}
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

use Piwik\Common;
use Piwik\Db;

class AspendoraSiteAudit extends \Piwik\Plugin
{
    public const TABLE_RUN = 'aspendora_site_audit_run';
    public const TABLE_ISSUE = 'aspendora_site_audit_issue';
    public const TABLE_PAGE = 'aspendora_site_audit_page';
    public const KEEP_RUNS = 26;
    public const DEFAULT_MAX_PAGES = 2000;

    public function install()
    {
        $run = Common::prefixTable(self::TABLE_RUN);
        Db::exec("CREATE TABLE IF NOT EXISTS `$run` (
            `idrun` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `start_url` VARCHAR(2048) NOT NULL DEFAULT '',
            `started_at` DATETIME NOT NULL,
            `finished_at` DATETIME NULL DEFAULT NULL,
            `pages_crawled` INT UNSIGNED NOT NULL DEFAULT 0,
            `nb_errors` INT UNSIGNED NOT NULL DEFAULT 0,
            `nb_warnings` INT UNSIGNED NOT NULL DEFAULT 0,
            `nb_notices` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(10) NOT NULL DEFAULT 'running',
            `error_text` TEXT NULL,
            PRIMARY KEY (`idrun`),
            KEY `idx_site_status_finished` (`idsite`, `status`, `finished_at`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $issue = Common::prefixTable(self::TABLE_ISSUE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$issue` (
            `idissue` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idrun` INT UNSIGNED NOT NULL,
            `idsite` INT UNSIGNED NOT NULL,
            `code` VARCHAR(40) NOT NULL,
            `severity` VARCHAR(10) NOT NULL,
            `url` VARCHAR(2048) NOT NULL,
            `url_hash` CHAR(40) NOT NULL,
            `detail` TEXT NULL,
            PRIMARY KEY (`idissue`),
            KEY `idx_run_code_url` (`idrun`, `code`, `url_hash`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $page = Common::prefixTable(self::TABLE_PAGE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$page` (
            `idrun` INT UNSIGNED NOT NULL,
            `idsite` INT UNSIGNED NOT NULL,
            `url` VARCHAR(2048) NOT NULL,
            `url_hash` CHAR(40) NOT NULL,
            `status` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `error_text` VARCHAR(255) NULL,
            `location` VARCHAR(2048) NULL,
            `content_type` VARCHAR(100) NULL,
            `response_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `depth` SMALLINT NULL,
            `in_sitemap` TINYINT(1) NOT NULL DEFAULT 0,
            `title` VARCHAR(500) NULL,
            `description` TEXT NULL,
            `canonical` VARCHAR(2048) NULL,
            `meta_robots` VARCHAR(255) NULL,
            `x_robots_tag` VARCHAR(255) NULL,
            `h1_count` SMALLINT UNSIGNED NULL,
            `word_count` INT UNSIGNED NULL,
            `og_title` TINYINT(1) NULL,
            `og_image` TINYINT(1) NULL,
            `nb_links` INT UNSIGNED NOT NULL DEFAULT 0,
            `nofollow` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`idrun`, `url_hash`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function uninstall()
    {
        Db::dropTables([
            Common::prefixTable(self::TABLE_RUN),
            Common::prefixTable(self::TABLE_ISSUE),
            Common::prefixTable(self::TABLE_PAGE),
        ]);
    }

    /** @return array<int,string> idSite → start URL */
    public static function getSiteMap(): array
    {
        $map = json_decode(getenv('ASPENDORA_AUDIT_SITE_MAP') ?: '{}', true) ?: [];
        $out = [];
        foreach ($map as $idSite => $url) {
            if ((int) $idSite > 0 && is_string($url) && $url !== '') {
                $out[(int) $idSite] = $url;
            }
        }
        return $out;
    }

    public static function getMaxPages(): int
    {
        $n = (int) (getenv('ASPENDORA_AUDIT_MAX_PAGES') ?: self::DEFAULT_MAX_PAGES);
        return $n > 0 ? $n : self::DEFAULT_MAX_PAGES;
    }

    /** @return string[] path regexes (no delimiters) for pages intentionally without internal links */
    public static function getOrphanOk(int $idSite): array
    {
        $map = json_decode(getenv('ASPENDORA_AUDIT_ORPHAN_OK') ?: '{}', true) ?: [];
        $list = $map[(string) $idSite] ?? [];
        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }
}
