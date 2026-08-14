<?php

/**
 * Regenerates plugins/AspendoraWhiteLabel/lang/en.json.
 *
 * Matomo merges plugin translations over core ones with array_replace_recursive, in plugin
 * load order (see Piwik\Translation\Loader\JsonFileLoader), so a plugin that loads after core
 * can override any core key just by declaring it. This walks the whole English catalogue,
 * finds every string that names the product, and writes the rebranded values into this
 * plugin's lang file — which is why the rebrand survives an upstream merge: re-run this and
 * the overrides are rebuilt against the new catalogue.
 *
 * Run from the repo root (no PHP on the host — same pattern as the php -l checks):
 *
 *   docker run --rm -v "$PWD":/src -w /src php:8.2-cli \
 *       php plugins/AspendoraWhiteLabel/tools/rebrand-translations.php
 *
 * Add --dry-run to print what would change without writing.
 */

const BRAND = 'Aspendora';

/**
 * Namespaces deliberately left saying "Matomo".
 *
 * Two reasons, both about honesty over cosmetics: admin/diagnostic surfaces are where you go
 * when something is broken and need to know what the software actually is (the user's choice
 * was "remove from the UI, keep it in the source and the About/System Check pages"), and the
 * promo plugins are deactivated in this deployment anyway.
 */
const SKIP_NAMESPACES = [
    'Installation',       // one-time installer, already run
    'CoreUpdater',        // upgrade flow — version numbers must match the real product
    'Diagnostics',        // System Check / System Report
    'CorePluginsAdmin',   // plugin manager: compatibility is stated against Matomo versions
    'Marketplace',        // deactivated
    'Feedback',           // deactivated
    'Tour',               // deactivated
    'ProfessionalServices', // deactivated
];

/**
 * Strings left alone wherever they appear: a legal entity, a sibling company, or a named
 * product we don't sell. Renaming "Matomo Analytics GmbH" to "Aspendora Analytics GmbH" would
 * be a false statement, not a rebrand.
 */
const SKIP_IF_CONTAINS = [
    'GmbH',
    'InnoCraft',
    'Matomo Cloud',
    'Matomo for WordPress',
    'Matomo Mobile',
    'formerly known as', // "Matomo, formerly known as Piwik" rebrands to a sentence about itself
];

/**
 * Namespaces whose source we own, so the rebrand belongs in the source string rather than in
 * an override. It has to: third-party plugins load AFTER this one (PluginList::sortPlugins
 * puts bundled plugins first, then everything else natcase-sorted), so an override of, say,
 * LoginOIDC_* would be overwritten right back by LoginOIDC's own lang file.
 */
const SKIP_OWN_NAMESPACE_PREFIXES = ['Aspendora', 'LoginOIDC'];

$root = dirname(__DIR__, 3);
$dryRun = in_array('--dry-run', $argv, true);
$target = $root . '/plugins/AspendoraWhiteLabel/lang/en.json';

$sources = array_merge([$root . '/lang/en.json'], glob($root . '/plugins/*/lang/en.json'));

$overrides = [];
$scanned = 0;

foreach ($sources as $file) {
    $catalogue = json_decode((string)file_get_contents($file), true);
    if (!is_array($catalogue)) {
        continue;
    }

    foreach ($catalogue as $namespace => $strings) {
        if (!is_array($strings) || in_array($namespace, SKIP_NAMESPACES, true)) {
            continue;
        }
        foreach (SKIP_OWN_NAMESPACE_PREFIXES as $prefix) {
            if (str_starts_with($namespace, $prefix)) {
                continue 2; // skip to the next namespace, not just the next prefix
            }
        }

        foreach ($strings as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $scanned++;

            $rebranded = rebrand($value);
            if ($rebranded !== $value) {
                $overrides[$namespace][$key] = $rebranded;
            }
        }
    }
}

ksort($overrides);
foreach ($overrides as &$strings) {
    ksort($strings);
}
unset($strings);

// This plugin's own strings are not generated — keep them at the top of the file.
$out = ['AspendoraWhiteLabel' => ['WhiteLabel' => 'White Label']] + $overrides;

$json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

$count = array_sum(array_map('count', $overrides));
printf("scanned %d strings, rebranded %d across %d namespaces\n", $scanned, $count, count($overrides));

if ($dryRun) {
    echo $json;
    exit(0);
}

file_put_contents($target, $json);
printf("wrote %s\n", $target);

/**
 * Replaces the product name while leaving code and URLs intact.
 *
 * Word boundaries alone are not enough: printf placeholders butt straight up against the name
 * ("%1$sMatomo%2$s"), so \b sees a letter before the M and skips it. Placeholders are parked
 * behind sentinels for the duration of the match instead. Lowercase "matomo" is left as-is —
 * that's matomo.org links and piwik.php filenames, which must keep working — and a trailing
 * dot-something is skipped too, so prose that names "Matomo.org" doesn't become a domain we
 * don't own.
 */
function rebrand(string $value): string
{
    foreach (SKIP_IF_CONTAINS as $needle) {
        if (str_contains($value, $needle)) {
            return $value;
        }
    }

    $placeholders = [];
    $parked = preg_replace_callback('/%(?:\d+\$)?[sd]/', function ($m) use (&$placeholders) {
        $token = "\x00" . count($placeholders) . "\x00";
        $placeholders[$token] = $m[0];
        return $token;
    }, $value);

    $parked = preg_replace('/(?<![A-Za-z])(Matomo|Piwik)(?![A-Za-z])(?!\.[a-z])/', BRAND, $parked);

    return strtr($parked, $placeholders);
}
