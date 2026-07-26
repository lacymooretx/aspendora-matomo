<?php

namespace Piwik\Plugins\AspendoraSessionRecording;

use Piwik\Common;
use Piwik\Db;

class AspendoraSessionRecording extends \Piwik\Plugin
{
    public const TABLE_REC = 'aspendora_recordings';
    public const TABLE_HEAT = 'aspendora_heatmap';

    public function install()
    {
        $rec = Common::prefixTable(self::TABLE_REC);
        Db::exec("CREATE TABLE IF NOT EXISTS `$rec` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `rec_key` VARCHAR(32) NOT NULL,
            `seq` INT UNSIGNED NOT NULL DEFAULT 0,
            `day` DATE NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `started_at` DATETIME NOT NULL,
            `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `nb_events` INT UNSIGNED NOT NULL DEFAULT 0,
            `ua` VARCHAR(300) NOT NULL DEFAULT '',
            `events` LONGBLOB NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_key` (`rec_key`, `seq`),
            KEY `idx_day` (`idsite`, `day`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $heat = Common::prefixTable(self::TABLE_HEAT);
        Db::exec("CREATE TABLE IF NOT EXISTS `$heat` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `day` DATE NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `kind` VARCHAR(10) NOT NULL,
            `x_pct` FLOAT NULL,
            `y_px` INT NULL,
            `doc_h` INT NULL,
            `scroll_pct` TINYINT UNSIGNED NULL,
            `vw` SMALLINT UNSIGNED NULL,
            PRIMARY KEY (`id`),
            KEY `idx_site_day` (`idsite`, `day`),
            KEY `idx_url` (`idsite`, `url`(180), `kind`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE_REC), Common::prefixTable(self::TABLE_HEAT)]);
    }
}
