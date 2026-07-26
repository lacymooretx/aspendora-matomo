<?php
/**
 * Aspendora Form Analytics.
 *
 * No tables of its own — reads the standard Matomo event log. The website tracker pushes
 * events with category "FormAnalytics":
 *   action "view" | "start" | "submit"  → name "gform_<id>"
 *   action "field"                       → name "gform_<id>::<field label>", value = seconds
 *   action "abandon"                     → name "gform_<id>::<last touched field>"
 *
 * Optional env ASPENDORA_GF_FORM_NAMES: JSON map of form id → display name,
 *   e.g. {"gform_32":"Newsletter (footer)"}
 */

namespace Piwik\Plugins\AspendoraFormAnalytics;

class AspendoraFormAnalytics extends \Piwik\Plugin
{
    public static function formDisplayName(string $formId): string
    {
        static $map = null;
        if ($map === null) {
            $map = json_decode(getenv('ASPENDORA_GF_FORM_NAMES') ?: '{}', true) ?: [];
        }
        return $map[$formId] ?? $formId;
    }
}
