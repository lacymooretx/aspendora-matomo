<?php
/**
 * Aspendora Identity — known-visitor graph + GoHighLevel CRM sync.
 *
 * The site bundle (bundle.js) sets the Matomo User ID from form submits
 * (email) or decorated email-campaign links (ghl:<contactId>). The hourly
 * sync task aggregates identified visits from log_visit, enriches each
 * identity against GoHighLevel, computes a lead score, and pushes tags back.
 *
 * Configuration comes from environment variables (set in docker compose, never in git):
 *   ASPENDORA_GHL_TOKEN        — GHL Private Integration token (pit-…); scopes:
 *                                contacts.readonly + contacts.write
 *   ASPENDORA_GHL_LOCATION_ID  — GHL sub-account (location) id
 *   ASPENDORA_IDENTITY_HOT_PAGES — optional REGEXP for high-intent URLs
 *                                  (default: pricing|contact|quote|demo|compliance)
 *   ASPENDORA_IDENTITY_HOT_SCORE — optional hot-lead threshold (default 50)
 */

namespace Piwik\Plugins\AspendoraIdentity;

use Piwik\Common;
use Piwik\Db;

class AspendoraIdentity extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_identity';

    public const TAG_VISITOR = 'website-visitor';
    public const TAG_HOT = 'website-hot-lead';

    public function install()
    {
        $table = Common::prefixTable(self::TABLE);
        // Match log_visit's collation — the sync joins on user_id, and MariaDB 11
        // defaults new DBs to utf8mb4_uca1400_ai_ci (mixed-collation '=' is an error).
        $row = Db::fetchRow(
            "SELECT TABLE_COLLATION c FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [Common::prefixTable('log_visit')]
        );
        $collation = preg_replace('/[^a-z0-9_]/', '', $row['c'] ?? '') ?: 'utf8mb4_general_ci';
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL,
            `user_id` VARCHAR(200) NOT NULL,
            `email` VARCHAR(190) DEFAULT NULL,
            `ghl_contact_id` VARCHAR(64) DEFAULT NULL,
            `first_name` VARCHAR(100) DEFAULT NULL,
            `last_name` VARCHAR(100) DEFAULT NULL,
            `company` VARCHAR(150) DEFAULT NULL,
            `first_seen` DATETIME DEFAULT NULL,
            `last_seen` DATETIME DEFAULT NULL,
            `nb_visits` INT UNSIGNED NOT NULL DEFAULT 0,
            `nb_actions` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_hot_intent` TINYINT(1) NOT NULL DEFAULT 0,
            `lead_score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `ghl_checked_at` DATETIME DEFAULT NULL,
            `ghl_hot_pushed` TINYINT(1) NOT NULL DEFAULT 0,
            `espo_target` VARCHAR(90) DEFAULT NULL,
            `espo_synced_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`idsite`, `user_id`),
            KEY `idx_site_score` (`idsite`, `lead_score`),
            KEY `idx_site_seen` (`idsite`, `last_seen`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=$collation");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }

    public static function hotPagesPattern(): string
    {
        return getenv('ASPENDORA_IDENTITY_HOT_PAGES') ?: 'pricing|contact|quote|demo|compliance';
    }

    public static function hotScoreThreshold(): int
    {
        $v = (int) (getenv('ASPENDORA_IDENTITY_HOT_SCORE') ?: 0);
        return $v > 0 ? $v : 50;
    }
}
