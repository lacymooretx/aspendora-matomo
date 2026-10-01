<?php
/**
 * Aspendora Site Audit — URL helpers.
 *
 * Framework-independent (no Piwik\ imports) so the crawler/analyzer can run standalone.
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

final class Url
{
    /** Resolve $href against $base and normalise. Returns null for non-http(s) links (mailto:, tel:, javascript: …). */
    public static function resolve(string $base, string $href): ?string
    {
        $href = trim(str_replace(["\t", "\n", "\r"], '', $href));
        if ($href === '') {
            return self::normalize($base);
        }
        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $href, $m)) {
            $scheme = strtolower($m[1]);
            return in_array($scheme, ['http', 'https'], true) ? self::normalize($href) : null;
        }
        $b = parse_url($base);
        if (!$b || empty($b['scheme']) || empty($b['host'])) {
            return null;
        }
        $authority = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return self::normalize($b['scheme'] . ':' . $href);
        }
        $basePath = $b['path'] ?? '/';
        if ($href[0] === '#') {
            return self::normalize($authority . $basePath . (isset($b['query']) ? '?' . $b['query'] : ''));
        }
        if ($href[0] === '?') {
            return self::normalize($authority . $basePath . $href);
        }
        if ($href[0] === '/') {
            return self::normalize($authority . $href);
        }
        $dir = substr($basePath, 0, strrpos($basePath, '/') + 1);
        return self::normalize($authority . $dir . $href);
    }

    /**
     * Canonical form used as the crawl key: lower-case scheme/host, no default port, no #fragment,
     * dot segments removed, empty path → "/", spaces encoded. The query string is kept as-is.
     */
    public static function normalize(string $url): ?string
    {
        $url = preg_replace('/#.*$/s', '', trim($url));
        $p = parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            return null;
        }
        $scheme = strtolower($p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower($p['host']);
        $port = isset($p['port']) && !(($scheme === 'http' && $p['port'] == 80) || ($scheme === 'https' && $p['port'] == 443))
            ? ':' . $p['port'] : '';
        $path = self::removeDotSegments($p['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        $path = str_replace(' ', '%20', $path);
        $query = isset($p['query']) && $p['query'] !== '' ? '?' . str_replace(' ', '%20', $p['query']) : '';
        return $scheme . '://' . $host . $port . $path . $query;
    }

    public static function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    public static function path(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    }

    /** Path plus query — what robots.txt rules match against. */
    public static function pathAndQuery(string $url): string
    {
        $q = parse_url($url, PHP_URL_QUERY);
        return self::path($url) . ($q !== null && $q !== '' ? '?' . $q : '');
    }

    /** True when the last path segment has a file extension that isn't a page (.pdf, .jpg, .xml …) — same test as check-seo.mjs. */
    public static function isAsset(string $url): bool
    {
        $path = self::path($url);
        return (bool) preg_match('/\.[a-z0-9]{2,5}$/i', $path)
            && !preg_match('/\.(html?|php|aspx?|jsp|cfm)$/i', $path);
    }

    private static function removeDotSegments(string $path): string
    {
        if (!str_contains($path, '.')) {
            return $path;
        }
        $out = [];
        $segments = explode('/', $path);
        $last = end($segments);
        foreach ($segments as $seg) {
            if ($seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if (count($out) > 1) {
                    array_pop($out);
                }
                continue;
            }
            $out[] = $seg;
        }
        $result = implode('/', $out);
        if ($last === '.' || $last === '..') {
            $result .= '/';
        }
        if ($result === '' || $result[0] !== '/') {
            $result = '/' . ltrim($result, '/');
        }
        return $result;
    }
}
