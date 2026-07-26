<?php

namespace Piwik\Plugins\AspendoraCompanies\Columns;

use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

/**
 * Captures the visitor's network prefix (/24 for IPv4, /48 for IPv6) at
 * tracking time — the only moment the full IP exists (stored IPs are
 * anonymized). Pure string math, no I/O: safe for the tracker hot path.
 * Private/loopback/link-local ranges are skipped.
 */
class OrgPrefix extends VisitDimension
{
    protected $columnName = 'aspendora_org_prefix';
    protected $columnType = 'VARCHAR(26) NULL';
    protected $nameSingular = 'AspendoraCompanies_NetworkPrefix';
    protected $type = self::TYPE_TEXT;

    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return self::prefixForIp($request->getIpString());
    }

    public static function prefixForIp(?string $ip): ?string
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        if (strpos($ip, ':') === false) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        }
        // IPv6: first three hextets of the expanded address (/48)
        $bin = inet_pton($ip);
        if ($bin === false) {
            return null;
        }
        $hex = bin2hex($bin);
        return implode(':', str_split(substr($hex, 0, 12), 4));
    }
}
