<?php
/**
 * Aspendora Search Keywords — Google Search Console + Bing Webmaster Tools import.
 *
 * Configuration comes from environment variables (set in docker compose, never in git):
 *   ASPENDORA_GSC_CLIENT_ID / ASPENDORA_GSC_CLIENT_SECRET / ASPENDORA_GSC_REFRESH_TOKEN
 *   ASPENDORA_GSC_PROPERTY_MAP  — JSON map of Matomo idSite → GSC property,
 *                                 e.g. {"1":"sc-domain:aspendora.com","2":"sc-domain:aspendoracompliance.com"}
 *   ASPENDORA_BING_API_KEY      — Bing Webmaster Tools API key (account-level)
 *   ASPENDORA_BING_SITE_MAP     — JSON map of Matomo idSite → verified Bing siteUrl,
 *                                 e.g. {"1":"https://www.aspendora.com/","2":"https://aspendoracompliance.com/"}
 */

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Piwik\Common;
use Piwik\Db;

class AspendoraSearchKeywords extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_gsc_keywords';
    public const BING_KEYWORDS_TABLE = 'aspendora_bing_keywords';
    public const BING_PAGES_TABLE = 'aspendora_bing_pages';
    public const BING_CRAWL_TABLE = 'aspendora_bing_crawl';
    public const BING_ISSUES_TABLE = 'aspendora_bing_crawl_issues';

    /** Idempotent — also called from Updates/1.1.0.php. */
    public function install()
    {
        $opts = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

        $table = Common::prefixTable(self::TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL,
            `day` DATE NOT NULL,
            `keyword` VARCHAR(255) NOT NULL,
            `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
            `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
            `position` FLOAT NOT NULL DEFAULT 0,
            PRIMARY KEY (`idsite`, `day`, `keyword`),
            KEY `idx_site_day` (`idsite`, `day`)
        ) $opts");

        $table = Common::prefixTable(self::BING_KEYWORDS_TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL,
            `week` DATE NOT NULL,
            `keyword` VARCHAR(255) NOT NULL,
            `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
            `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
            `position` FLOAT NOT NULL DEFAULT 0,
            PRIMARY KEY (`idsite`, `week`, `keyword`),
            KEY `idx_site_week` (`idsite`, `week`)
        ) $opts");

        $table = Common::prefixTable(self::BING_PAGES_TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL,
            `week` DATE NOT NULL,
            `url_hash` CHAR(40) NOT NULL,
            `url` VARCHAR(2048) NOT NULL,
            `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
            `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
            `position` FLOAT NOT NULL DEFAULT 0,
            PRIMARY KEY (`idsite`, `week`, `url_hash`),
            KEY `idx_site_week` (`idsite`, `week`)
        ) $opts");

        $table = Common::prefixTable(self::BING_CRAWL_TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL,
            `day` DATE NOT NULL,
            `crawled_pages` INT UNSIGNED NOT NULL DEFAULT 0,
            `in_index` INT UNSIGNED NOT NULL DEFAULT 0,
            `in_links` INT UNSIGNED NOT NULL DEFAULT 0,
            `code_2xx` INT UNSIGNED NOT NULL DEFAULT 0,
            `code_301` INT UNSIGNED NOT NULL DEFAULT 0,
            `code_302` INT UNSIGNED NOT NULL DEFAULT 0,
            `code_4xx` INT UNSIGNED NOT NULL DEFAULT 0,
            `code_5xx` INT UNSIGNED NOT NULL DEFAULT 0,
            `all_other_codes` INT UNSIGNED NOT NULL DEFAULT 0,
            `crawl_errors` INT UNSIGNED NOT NULL DEFAULT 0,
            `blocked_by_robots` INT UNSIGNED NOT NULL DEFAULT 0,
            `connection_timeout` INT UNSIGNED NOT NULL DEFAULT 0,
            `dns_failures` INT UNSIGNED NOT NULL DEFAULT 0,
            `contains_malware` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`idsite`, `day`)
        ) $opts");

        $table = Common::prefixTable(self::BING_ISSUES_TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL,
            `url_hash` CHAR(40) NOT NULL,
            `url` VARCHAR(2048) NOT NULL,
            `http_code` INT NOT NULL DEFAULT 0,
            `in_links` INT UNSIGNED NOT NULL DEFAULT 0,
            `issues` INT UNSIGNED NOT NULL DEFAULT 0,
            `fetched_at` DATETIME NOT NULL,
            PRIMARY KEY (`idsite`, `url_hash`)
        ) $opts");
    }

    public function uninstall()
    {
        Db::dropTables([
            Common::prefixTable(self::TABLE),
            Common::prefixTable(self::BING_KEYWORDS_TABLE),
            Common::prefixTable(self::BING_PAGES_TABLE),
            Common::prefixTable(self::BING_CRAWL_TABLE),
            Common::prefixTable(self::BING_ISSUES_TABLE),
        ]);
    }

    public static function getPropertyForSite(int $idSite): ?string
    {
        $map = json_decode(getenv('ASPENDORA_GSC_PROPERTY_MAP') ?: '{}', true) ?: [];
        return $map[(string) $idSite] ?? null;
    }

    public static function getBingSiteForSite(int $idSite): ?string
    {
        $map = json_decode(getenv('ASPENDORA_BING_SITE_MAP') ?: '{}', true) ?: [];
        return $map[(string) $idSite] ?? null;
    }
}
