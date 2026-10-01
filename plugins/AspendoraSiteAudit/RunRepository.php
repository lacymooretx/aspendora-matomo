<?php
/**
 * Aspendora Site Audit — read helpers shared by the API and the report views.
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

use Piwik\Common;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Site;

class RunRepository
{
    /** Latest 'ok' run of the site that finished on or before $endDate (Y-m-d; finished_at is UTC). */
    public static function latestOkRun(int $idSite, string $endDate): ?array
    {
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_RUN);
        $row = Db::fetchRow(
            "SELECT * FROM `$table` WHERE idsite = ? AND status = 'ok' AND finished_at < DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY finished_at DESC, idrun DESC LIMIT 1",
            [$idSite, $endDate]
        );
        return $row ?: null;
    }

    /** The 'ok' run of the same site immediately before $run. */
    public static function previousOkRun(array $run): ?array
    {
        $table = Common::prefixTable(AspendoraSiteAudit::TABLE_RUN);
        $row = Db::fetchRow(
            "SELECT * FROM `$table` WHERE idsite = ? AND status = 'ok' AND idrun < ? ORDER BY idrun DESC LIMIT 1",
            [(int) $run['idsite'], (int) $run['idrun']]
        );
        return $row ?: null;
    }

    public static function periodEnd(string $period, string $date): string
    {
        return PeriodFactory::build($period, $date)->getDateEnd()->toString('Y-m-d');
    }

    /** Format a UTC DATETIME in the site's timezone, e.g. "2026-09-28 09:03 CDT". */
    public static function formatTime(?string $utc, int $idSite): string
    {
        if (!$utc) {
            return '';
        }
        $tz = new \DateTimeZone('UTC');
        try {
            $tz = new \DateTimeZone(Site::getTimezoneFor($idSite));
        } catch (\Throwable $e) {
            // Matomo allows "UTC+5"-style zones PHP can't parse; fall back to UTC.
        }
        $d = new \DateTime($utc, new \DateTimeZone('UTC'));
        return $d->setTimezone($tz)->format('Y-m-d H:i T');
    }

    public static function issueName(string $code): string
    {
        $key = 'AspendoraSiteAudit_Issue_' . $code;
        $t = Piwik::translate($key);
        return $t === $key ? (Analyzer::ISSUES[$code][1] ?? $code) : $t;
    }

    public static function severityName(string $severity): string
    {
        $key = 'AspendoraSiteAudit_Severity_' . $severity;
        $t = Piwik::translate($key);
        return $t === $key ? ucfirst($severity) : $t;
    }
}
