<?php
/**
 * Aspendora Site Audit — analyzer.
 *
 * Framework-independent (no Piwik\ imports). Turns a Crawler::crawl() result into a flat list of
 * issues: ['code', 'severity' => error|warning|notice, 'url', 'detail'].
 *
 * Thresholds and wording deliberately match aspendora-website/frontend/scripts/check-seo.mjs
 * (title ≤60, description 110–160, exactly one <h1>, orphan rules) so the pre-deploy check and the
 * weekly audit agree. Where check-seo can't see something (HTTP status, redirect chains, response
 * time, Open Graph) the audit adds its own checks.
 *
 * "indexable" = 200 HTML, no noindex in meta robots or X-Robots-Tag, canonical self or absent.
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

final class Analyzer
{
    public const TITLE_MAX = 60;
    public const DESC_MAX = 160;
    public const DESC_MIN = 110;
    public const SLOW_MS = 2000;
    private const MAX_HOPS = 20;
    private const DETAIL_URL_LIST = 10;

    /** code => [severity, English name]. Names are mirrored in lang/en.json (AspendoraSiteAudit_Issue_<code>). */
    public const ISSUES = [
        // errors
        'http_4xx'                 => ['error', 'Broken page (4xx)'],
        'http_5xx'                 => ['error', 'Server error (5xx)'],
        'connection_failed'        => ['error', 'Connection failed or timed out'],
        'broken_internal_link'     => ['error', 'Broken internal link'],
        'redirect_chain'           => ['error', 'Redirect chain (more than one hop)'],
        'redirect_loop'            => ['error', 'Redirect loop'],
        'noindex_in_sitemap'       => ['error', 'Noindex page in sitemap'],
        'title_missing'            => ['error', 'Title missing or empty'],
        'title_too_long'           => ['error', 'Title too long (>60 characters)'],
        'desc_missing'             => ['error', 'Meta description missing or empty'],
        'desc_too_long'            => ['error', 'Meta description too long (>160 characters)'],
        'duplicate_title'          => ['error', 'Duplicate title'],
        'duplicate_description'    => ['error', 'Duplicate meta description'],
        // warnings
        'desc_too_short'           => ['warning', 'Meta description too short (<110 characters)'],
        'h1_missing'               => ['warning', 'H1 missing'],
        'h1_multiple'              => ['warning', 'More than one H1'],
        'links_to_redirect'        => ['warning', 'Internal link to a redirect'],
        'canonical_mismatch'       => ['warning', 'Canonical points to another URL'],
        'indexable_not_in_sitemap' => ['warning', 'Indexable page missing from the sitemap'],
        'sitemap_url_not_200'      => ['warning', 'Sitemap URL is not 200 (redirect or error)'],
        'orphan'                   => ['warning', 'Orphan page (no internal links point here)'],
        'slow_page'                => ['warning', 'Slow page (response >2 s)'],
        // notices
        'og_tags_missing'          => ['notice', 'Open Graph tags missing'],
        'noindex_page'             => ['notice', 'Noindex page'],
        'redirect'                 => ['notice', 'Redirect (3xx)'],
    ];

    public const SEVERITY_ORDER = ['error' => 1, 'warning' => 2, 'notice' => 3];

    /** @var array<string,array> */
    private array $pages;
    private string $startUrl;
    /** @var string[] PCRE patterns (with delimiters) matched against the URL path */
    private array $orphanOk;
    private bool $hasSitemap;
    /** Orphan detection needs the full link graph; skipped when the crawl stopped at max pages. */
    private bool $truncated;
    private array $issues = [];
    /** @var array<string,array{final:string,hops:string[],loop:bool}> */
    private array $finalCache = [];

    /**
     * @param array    $crawl     Crawler::crawl() result (needs 'start_url', 'pages', 'sitemap_urls')
     * @param string[] $orphanOk  regexes for paths that are intentionally unlinked, without delimiters
     *                            (e.g. "^/(sitting-duck|aspirin)/$"), as in ASPENDORA_AUDIT_ORPHAN_OK
     */
    public function __construct(array $crawl, array $orphanOk = [])
    {
        $this->pages = $crawl['pages'];
        $this->startUrl = $crawl['start_url'];
        $this->hasSitemap = !empty($crawl['sitemap_urls']);
        $this->truncated = !empty($crawl['truncated']);
        $this->orphanOk = [];
        foreach ($orphanOk as $re) {
            $p = '~' . str_replace('~', '\~', (string) $re) . '~';
            if (@preg_match($p, '') !== false) {
                $this->orphanOk[] = $p;
            }
        }
    }

    /** @return array<int,array{code:string,severity:string,url:string,detail:string}> */
    public function analyze(): array
    {
        $this->issues = [];
        $inlinks = [];
        $referrers = [];

        foreach ($this->pages as $url => $p) {
            foreach ($p['links'] ?? [] as $target) {
                if ($target === $url) {
                    continue;
                }
                $referrers[$target][] = $url;
                $final = $this->resolve($target)['final'];
                if ($final !== $url) {
                    $inlinks[$final][$url] = true;
                }
            }
        }

        foreach ($this->pages as $url => $p) {
            $this->checkStatus($url, $p, $referrers[$url] ?? []);
            if ($p['status'] === 200 && !empty($p['is_html'])) {
                $this->checkPage($url, $p, $inlinks[$url] ?? []);
                $this->checkLinks($url, $p);
            }
        }
        $this->checkDuplicates('title', 'duplicate_title', 'title');
        $this->checkDuplicates('description', 'duplicate_description', 'description');

        usort($this->issues, function ($a, $b) {
            return [self::SEVERITY_ORDER[$a['severity']], $a['code'], $a['url'], $a['detail']]
                <=> [self::SEVERITY_ORDER[$b['severity']], $b['code'], $b['url'], $b['detail']];
        });
        return $this->issues;
    }

    public static function isNoindex(array $p): bool
    {
        $r = strtolower(($p['meta_robots'] ?? '') . ' ' . ($p['x_robots_tag'] ?? ''));
        return str_contains($r, 'noindex') || (bool) preg_match('/(^|[\s,:])none($|[\s,])/', $r);
    }

    public static function isIndexable(array $p): bool
    {
        return $p['status'] === 200 && !empty($p['is_html']) && !self::isNoindex($p)
            && (empty($p['canonical']) || $p['canonical'] === $p['url']);
    }

    // ---------------------------------------------------------------- checks

    private function checkStatus(string $url, array $p, array $refs): void
    {
        $s = $p['status'];
        $from = $refs ? ' · linked from ' . $this->urlList($refs) : '';
        if (!empty($p['in_sitemap'])) {
            $from .= ' · listed in sitemap';
        }
        if ($s === 0) {
            $this->add('connection_failed', $url, trim(($p['error'] ?? 'connection failed') . $from));
        } elseif ($s >= 400 && $s < 500) {
            $this->add('http_4xx', $url, "HTTP $s" . $from);
        } elseif ($s >= 500) {
            $this->add('http_5xx', $url, "HTTP $s" . $from);
        } elseif ($s >= 300 && $s < 400) {
            $r = $this->resolve($url);
            $this->add('redirect', $url, "$s → " . ($p['location'] ?? '(no Location header)'));
            if ($r['loop']) {
                $this->add('redirect_loop', $url, implode(' → ', $r['hops']));
            } elseif (count($r['hops']) > 2) {
                $this->add('redirect_chain', $url, (count($r['hops']) - 1) . ' hops: ' . implode(' → ', $r['hops']));
            }
        }
        if (!empty($p['in_sitemap']) && $s !== 200) {
            $detail = $s === 0 ? 'connection failed' : "HTTP $s";
            if ($p['location']) {
                $detail .= ' → ' . $p['location'];
            }
            $this->add('sitemap_url_not_200', $url, $detail);
        }
        if (!empty($p['is_html']) && $p['response_ms'] > self::SLOW_MS) {
            $this->add('slow_page', $url, "response {$p['response_ms']} ms (max " . self::SLOW_MS . ')');
        }
    }

    private function checkPage(string $url, array $p, array $inlinks): void
    {
        $noindex = self::isNoindex($p);
        $indexable = self::isIndexable($p);

        $title = $p['title'];
        if ($title === null || $title === '') {
            $this->add('title_missing', $url, 'title missing');
        } elseif (($len = mb_strlen($title)) > self::TITLE_MAX) {
            $this->add('title_too_long', $url, "title $len chars (max " . self::TITLE_MAX . "): $title");
        }

        $desc = $p['description'];
        if ($desc === null || $desc === '') {
            $this->add('desc_missing', $url, 'meta description missing or empty');
        } elseif (($len = mb_strlen($desc)) > self::DESC_MAX) {
            $this->add('desc_too_long', $url, "description $len chars (max " . self::DESC_MAX . ')');
        } elseif ($indexable && $len < self::DESC_MIN) {
            $this->add('desc_too_short', $url, "description $len chars (aim for " . self::DESC_MIN . '–155)');
        }

        if (!$noindex && !empty($p['canonical']) && $p['canonical'] !== $url) {
            $this->add('canonical_mismatch', $url, 'canonical points elsewhere: ' . $p['canonical']);
        }
        if ($noindex) {
            $src = [];
            if (stripos((string) $p['meta_robots'], 'noindex') !== false || stripos((string) $p['meta_robots'], 'none') !== false) {
                $src[] = 'meta robots "' . $p['meta_robots'] . '"';
            }
            if ($p['x_robots_tag'] !== null && $p['x_robots_tag'] !== '') {
                $src[] = 'X-Robots-Tag "' . $p['x_robots_tag'] . '"';
            }
            $this->add('noindex_page', $url, implode('; ', $src) ?: 'noindex');
            if (!empty($p['in_sitemap'])) {
                $this->add('noindex_in_sitemap', $url, 'noindex page is in the sitemap');
            }
        }
        if ($indexable && $this->hasSitemap && empty($p['in_sitemap'])) {
            $this->add('indexable_not_in_sitemap', $url, 'indexable page missing from the sitemap');
        }

        $h1 = (int) $p['h1_count'];
        if ($h1 === 0) {
            $this->add('h1_missing', $url, '0 <h1> tags (expected 1)');
        } elseif ($h1 > 1) {
            $this->add('h1_multiple', $url, "$h1 <h1> tags (expected 1)");
        }

        $isStart = $url === $this->startUrl || $url === $this->resolve($this->startUrl)['final'];
        if ($indexable && !$this->truncated && !$isStart && !$inlinks && !$this->isOrphanOk($url)) {
            $this->add('orphan', $url, 'orphan: no internal links point here'
                . (!empty($p['in_sitemap']) ? ' (found via sitemap)' : ''));
        }

        if ($indexable && (empty($p['og_title']) || empty($p['og_image']))) {
            $missing = array_keys(array_filter(['og:title' => empty($p['og_title']), 'og:image' => empty($p['og_image'])]));
            $this->add('og_tags_missing', $url, 'missing ' . implode(', ', $missing));
        }
    }

    private function checkLinks(string $url, array $p): void
    {
        foreach ($p['links'] ?? [] as $target) {
            if ($target === $url || !isset($this->pages[$target])) {
                continue; // not crawled (robots.txt, max pages) — unknown, not reported
            }
            $t = $this->pages[$target];
            $r = $this->resolve($target);
            $final = $this->pages[$r['final']] ?? null;
            if ($final !== null && ($final['status'] === 0 || $final['status'] >= 400)) {
                $code = $final['status'] ?: 'connection failed';
                $via = $r['final'] !== $target ? ' via redirect to ' . $r['final'] : '';
                $this->add('broken_internal_link', $url, "broken internal link $target (HTTP $code$via)");
            } elseif ($t['status'] >= 300 && $t['status'] < 400) {
                $this->add('links_to_redirect', $url, "links to redirect $target → {$r['final']}");
            }
        }
    }

    private function checkDuplicates(string $field, string $code, string $label): void
    {
        $groups = [];
        foreach ($this->pages as $url => $p) {
            if (self::isIndexable($p) && isset($p[$field]) && $p[$field] !== '') {
                $groups[$p[$field]][] = $url;
            }
        }
        foreach ($groups as $value => $urls) {
            if (count($urls) < 2) {
                continue;
            }
            foreach ($urls as $u) {
                $others = array_values(array_diff($urls, [$u]));
                $this->add($code, $u, "duplicate $label also on " . $this->urlList($others) . ': "' . mb_substr((string) $value, 0, 70) . '"');
            }
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Follow recorded redirects from $url. hops = [url, hop1, …, final]; final may be off-host or
     * uncrawled (then it has no record).
     */
    private function resolve(string $url): array
    {
        if (isset($this->finalCache[$url])) {
            return $this->finalCache[$url];
        }
        $hops = [$url];
        $seen = [$url => true];
        $loop = false;
        $cur = $url;
        while (isset($this->pages[$cur]) && $this->pages[$cur]['status'] >= 300 && $this->pages[$cur]['status'] < 400
            && !empty($this->pages[$cur]['location']) && count($hops) <= self::MAX_HOPS) {
            $next = $this->pages[$cur]['location'];
            $hops[] = $next;
            if (isset($seen[$next])) {
                $loop = true;
                break;
            }
            $seen[$next] = true;
            $cur = $next;
        }
        if (count($hops) > self::MAX_HOPS) {
            $loop = true;
        }
        return $this->finalCache[$url] = ['final' => $cur, 'hops' => $hops, 'loop' => $loop];
    }

    private function isOrphanOk(string $url): bool
    {
        $path = Url::path($url);
        foreach ($this->orphanOk as $re) {
            if (preg_match($re, $path)) {
                return true;
            }
        }
        return false;
    }

    private function urlList(array $urls): string
    {
        $urls = array_values(array_unique($urls));
        $shown = array_slice($urls, 0, self::DETAIL_URL_LIST);
        $more = count($urls) - count($shown);
        return implode(', ', $shown) . ($more > 0 ? " (+$more more)" : '');
    }

    private function add(string $code, string $url, string $detail): void
    {
        $this->issues[] = ['code' => $code, 'severity' => self::ISSUES[$code][0], 'url' => $url, 'detail' => $detail];
    }

    /** @return array<string,int> issue count per severity */
    public static function countBySeverity(array $issues): array
    {
        $out = ['error' => 0, 'warning' => 0, 'notice' => 0];
        foreach ($issues as $i) {
            $out[$i['severity']]++;
        }
        return $out;
    }

    /** @return array<string,int> distinct URLs per issue code, most frequent first */
    public static function countByCode(array $issues): array
    {
        $seen = [];
        foreach ($issues as $i) {
            $seen[$i['code']][$i['url']] = true;
        }
        $out = array_map('count', $seen);
        arsort($out);
        return $out;
    }
}
