<?php
/**
 * Aspendora Funnels — ordered multi-step URL funnels over visit sequences.
 *
 * Definitions come from the ASPENDORA_FUNNELS env var (JSON), e.g.:
 *   [{"name":"Lead Gen","idsite":1,"steps":[
 *      {"label":"Landing","pattern":"^/$"},
 *      {"label":"Services","pattern":"^/(services|compliance)"},
 *      {"label":"Contact","pattern":"^/contact"}]}]
 * "pattern" is a PCRE fragment matched against the page path (no delimiters);
 * "idsite" is optional — omit to apply the funnel to every site.
 * With no env config, a default Contact funnel is used.
 */

namespace Piwik\Plugins\AspendoraFunnels;

class AspendoraFunnels extends \Piwik\Plugin
{
    /** @return array<int, array{name: string, idsite?: int, steps: array}> */
    public static function definitions(int $idSite): array
    {
        $raw = getenv('ASPENDORA_FUNNELS') ?: '';
        $defs = json_decode($raw, true);
        if (!is_array($defs) || !$defs) {
            $defs = [[
                'name'  => 'Contact funnel',
                'steps' => [
                    ['label' => 'Any page', 'pattern' => '.'],
                    ['label' => 'Contact / quote / demo', 'pattern' => 'contact|quote|demo'],
                ],
            ]];
        }
        $out = [];
        foreach ($defs as $def) {
            if (empty($def['name']) || empty($def['steps']) || !is_array($def['steps'])) {
                continue;
            }
            if (isset($def['idsite']) && (int) $def['idsite'] !== $idSite) {
                continue;
            }
            $out[] = $def;
        }
        return $out;
    }
}
