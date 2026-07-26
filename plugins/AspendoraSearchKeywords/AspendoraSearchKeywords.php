<?php
/**
 * Aspendora Search Keywords — GSC import.
 *
 * Configuration comes from environment variables (set in docker compose, never in git):
 *   ASPENDORA_GSC_CLIENT_ID / ASPENDORA_GSC_CLIENT_SECRET / ASPENDORA_GSC_REFRESH_TOKEN
 *   ASPENDORA_GSC_PROPERTY_MAP  — JSON map of Matomo idSite → GSC property,
 *                                 e.g. {"1":"sc-domain:aspendora.com","2":"sc-domain:aspendoracompliance.com"}
 */

namespace Piwik\Plugins\AspendoraSearchKeywords;

use Piwik\Common;
use Piwik\Db;

class AspendoraSearchKeywords extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_gsc_keywords';

    public function install()
    {
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
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }

    public static function getPropertyForSite(int $idSite): ?string
    {
        $map = json_decode(getenv('ASPENDORA_GSC_PROPERTY_MAP') ?: '{}', true) ?: [];
        return $map[(string) $idSite] ?? null;
    }
}
