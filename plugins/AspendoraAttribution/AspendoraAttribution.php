<?php
/**
 * Aspendora Attribution.
 *
 * Conversions = Matomo goal conversions (log_conversion) plus event conversions defined in env
 * ASPENDORA_ATTRIB_EVENTS (JSON list of event categories; default ["Newsletter"]).
 */

namespace Piwik\Plugins\AspendoraAttribution;

class AspendoraAttribution extends \Piwik\Plugin
{
    public static function conversionEventCategories(): array
    {
        $cats = json_decode(getenv('ASPENDORA_ATTRIB_EVENTS') ?: '["Newsletter"]', true);
        return is_array($cats) && $cats ? $cats : ['Newsletter'];
    }
}
