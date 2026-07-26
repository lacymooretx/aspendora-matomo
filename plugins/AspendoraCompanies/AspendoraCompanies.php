<?php
/**
 * Aspendora Companies — reverse-IP B2B company identification.
 *
 * Stored log_visit IPs are anonymized (privacy config), so the OrgPrefix
 * visit dimension captures the /24 (IPv4) or /48 (IPv6) network prefix at
 * tracking time — coarser than a full IP, zero I/O in the tracker path.
 * A nightly task resolves prefixes to organizations and an ISP/hosting
 * keyword filter separates real companies from residential carriers.
 *
 * Configuration (environment variables, never in git):
 *   ASPENDORA_IPINFO_TOKEN — optional ipinfo.io token for ASN/org lookups
 *                            (falls back to rDNS only)
 *   ASPENDORA_ALERT_EMAIL  — optional recipient for the daily hot-activity
 *                            digest (skipped when unset)
 */

namespace Piwik\Plugins\AspendoraCompanies;

use Piwik\Common;
use Piwik\Db;

class AspendoraCompanies extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_ip_org';

    public function install()
    {
        $table = Common::prefixTable(self::TABLE);
        // Inherit log_visit's collation — reports JOIN on the prefix column and
        // MariaDB 11 defaults to utf8mb4_uca1400_ai_ci (mixed '=' is an error).
        $row = Db::fetchRow(
            "SELECT TABLE_COLLATION c FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [Common::prefixTable('log_visit')]
        );
        $collation = preg_replace('/[^a-z0-9_]/', '', $row['c'] ?? '') ?: 'utf8mb4_general_ci';
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `prefix` VARCHAR(26) NOT NULL,
            `org` VARCHAR(190) DEFAULT NULL,
            `rdns` VARCHAR(190) DEFAULT NULL,
            `is_isp` TINYINT(1) NOT NULL DEFAULT 0,
            `resolved_at` DATETIME DEFAULT NULL,
            `first_seen` DATETIME NOT NULL,
            PRIMARY KEY (`prefix`),
            KEY `idx_org` (`is_isp`, `org`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=$collation");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }

    public static function alertEmail(): ?string
    {
        $email = getenv('ASPENDORA_ALERT_EMAIL') ?: '';
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
