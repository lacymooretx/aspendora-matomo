<?php

namespace Piwik\Plugins\AspendoraCustomReports;

use Piwik\Common;
use Piwik\Db;

class AspendoraCustomReports extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_custom_reports';

    public function install()
    {
        $table = Common::prefixTable(self::TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `dimension` VARCHAR(50) NOT NULL,
            `metrics` VARCHAR(500) NOT NULL,
            `filters` TEXT NULL,
            `created_by` VARCHAR(100) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }
}
