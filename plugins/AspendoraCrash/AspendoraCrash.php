<?php
/**
 * Aspendora Crash — JavaScript error tracking.
 *
 * bundle.js reports window.onerror / unhandledrejection (max 10 per page)
 * to hub.php ('err' beacons, daily cap 2000), which inserts into this
 * table. Report under Behaviour groups by message + source. A daily task
 * prunes rows older than 60 days (same retention as session recordings).
 */

namespace Piwik\Plugins\AspendoraCrash;

use Piwik\Common;
use Piwik\Db;

class AspendoraCrash extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_js_errors';
    public const RETENTION_DAYS = 60;

    public function install()
    {
        $table = Common::prefixTable(self::TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `day` DATE NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `message` VARCHAR(300) NOT NULL DEFAULT '',
            `source` VARCHAR(200) NOT NULL DEFAULT '',
            `line` INT UNSIGNED NOT NULL DEFAULT 0,
            `col` INT UNSIGNED NOT NULL DEFAULT 0,
            `stack` TEXT,
            `ua` VARCHAR(300) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_site_day` (`idsite`, `day`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }
}
