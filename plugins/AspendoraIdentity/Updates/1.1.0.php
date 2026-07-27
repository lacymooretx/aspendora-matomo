<?php

namespace Piwik\Plugins\AspendoraIdentity;

use Piwik\Common;
use Piwik\Db;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

/** Adds the Espo bridge columns (espo_target, espo_synced_at). */
class Updates_1_1_0 extends PiwikUpdates
{
    public function doUpdate(Updater $updater)
    {
        $table = Common::prefixTable(AspendoraIdentity::TABLE);
        foreach (['espo_target' => 'VARCHAR(90) DEFAULT NULL', 'espo_synced_at' => 'DATETIME DEFAULT NULL'] as $col => $def) {
            $exists = Db::fetchOne(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $col]
            );
            if (!$exists) {
                Db::exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        }
    }
}
