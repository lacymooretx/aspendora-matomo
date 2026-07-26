<?php
/**
 * Aspendora Web Vitals — PageSpeed Insights lab metrics per page, mobile + desktop.
 *
 * Env config (docker compose, never in git):
 *   ASPENDORA_PSI_API_KEY  — Google PageSpeed Insights API key
 *   ASPENDORA_PSI_URL_MAP  — JSON map of Matomo idSite → list of URLs to test daily,
 *       e.g. {"1":["https://www.aspendora.com/","https://www.aspendora.com/it-support-houston/"],
 *             "2":["https://aspendoracompliance.com/"]}
 */

namespace Piwik\Plugins\AspendoraWebVitals;

use Piwik\Common;
use Piwik\Db;

class AspendoraWebVitals extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_web_vitals';

    public function install()
    {
        $table = Common::prefixTable(self::TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL,
            `day` DATE NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `strategy` VARCHAR(10) NOT NULL,
            `perf_score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `lcp_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `cls_x1000` INT UNSIGNED NOT NULL DEFAULT 0,
            `tbt_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `fcp_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `si_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`idsite`, `day`, `url`(180), `strategy`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }

    public static function getUrlsForSite(int $idSite): array
    {
        $map = json_decode(getenv('ASPENDORA_PSI_URL_MAP') ?: '{}', true) ?: [];
        return $map[(string) $idSite] ?? [];
    }
}
