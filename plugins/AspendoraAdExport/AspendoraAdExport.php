<?php
/**
 * Aspendora Ad Export — offline-conversion exports for Google/Microsoft Ads.
 *
 * Two conversion sources:
 *  - Event/goal conversions matched to ad click ids in Matomo logs (API.php).
 *  - Won GoHighLevel opportunities with real revenue (WonOpportunities.php),
 *    imported daily into aspendora_offline_conversions and exported via
 *    getGoogleAdsSalesExport / getMicrosoftAdsSalesExport.
 *
 * Env (shared with AspendoraIdentity): ASPENDORA_GHL_TOKEN (PIT — must ALSO
 * include the opportunities.readonly scope), ASPENDORA_GHL_LOCATION_ID.
 */

namespace Piwik\Plugins\AspendoraAdExport;

use Piwik\Common;
use Piwik\Db;

class AspendoraAdExport extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_offline_conversions';

    public function install()
    {
        $table = Common::prefixTable(self::TABLE);
        // Inherit log_visit's collation (MariaDB 11 defaults differ from ours;
        // the importer joins identities against log tables).
        $row = Db::fetchRow(
            "SELECT TABLE_COLLATION c FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [Common::prefixTable('log_visit')]
        );
        $collation = preg_replace('/[^a-z0-9_]/', '', $row['c'] ?? '') ?: 'utf8mb4_general_ci';
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `opp_id` VARCHAR(64) NOT NULL,
            `contact_id` VARCHAR(64) DEFAULT NULL,
            `email` VARCHAR(190) DEFAULT NULL,
            `opp_name` VARCHAR(190) DEFAULT NULL,
            `monetary_value` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `won_at` DATETIME NOT NULL,
            `network` VARCHAR(10) DEFAULT NULL,
            `click_id` VARCHAR(200) DEFAULT NULL,
            `imported_at` DATETIME NOT NULL,
            PRIMARY KEY (`opp_id`),
            KEY `idx_won` (`won_at`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=$collation");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }
}
