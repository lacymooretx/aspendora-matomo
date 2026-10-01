<?php
/**
 * Aspendora Site Audit — crawler.
 *
 * Framework-independent (plain PHP 8.2 + ext-curl + ext-dom, no Piwik\ imports) so it can be run
 * and tested standalone (see tools/test-crawl.php).
 *
 * Seeds: the start URL plus every URL in the site's sitemaps (robots.txt `Sitemap:` lines, else
 * /sitemap-index.xml, else /sitemap.xml; sitemap indexes are followed). Then a breadth-first crawl
 * of same-host <a href> links, honouring robots.txt `User-agent: *` Disallow rules. Redirects are
 * NOT followed by curl: each hop is its own record (status + Location) and the target is queued.
 * URLs that look like files (.pdf, .jpg …) get a HEAD (falling back to GET) and are not parsed.
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AspendoraSiteAudit;

final class Crawler
{
    public const USER_AGENT = 'AspendoraSiteAudit/1.0 (+https://www.aspendora.com)';
    private const MAX_BODY_BYTES = 5 * 1024 * 1024;
    private const MAX_URL_LENGTH = 2048;
    private const MAX_QUERY_VARIANTS_PER_PATH = 10;
    private const MAX_SITEMAP_FILES = 50;
    private const MAX_SITEMAP_URLS = 50000;

    private string $startUrl;
    private string $host;
    private int $maxPages;
    private int $concurrency;
    /** @var callable|null */
    private $log;

    /** @var string[] robots.txt Disallow rules for User-agent: * */
    private array $disallow = [];
    /** @var array<string,true> */
    private array $sitemapUrls = [];
    /** @var string[] sitemap files actually read */
    private array $sitemapFiles = [];

    /** @var array<string,true> URLs queued or fetched */
    private array $seen = [];
    /** @var array<string,int> shortest link distance from the start URL */
    private array $depth = [];
    /** @var array<string,int> distinct query-string variants seen per path */
    private array $queryVariants = [];
    private \SplQueue $queue;
    private int $skippedRobots = 0;
    private int $skippedQuery = 0;
    private bool $truncated = false;

    public function __construct(string $startUrl, int $maxPages = 2000, int $concurrency = 4, ?callable $log = null)
    {
        $norm = Url::normalize($startUrl);
        if ($norm === null) {
            throw new \InvalidArgumentException("Invalid start URL: $startUrl");
        }
        $this->startUrl = $norm;
        $this->host = Url::host($norm);
        $this->maxPages = max(1, $maxPages);
        $this->concurrency = max(1, $concurrency);
        $this->log = $log;
        $this->queue = new \SplQueue();
    }

    /**
     * @return array{start_url:string, pages:array<string,array>, sitemap_urls:string[], sitemap_files:string[],
     *               robots_disallow:string[], skipped_robots:int, skipped_query:int, truncated:bool, duration_s:float}
     */
    public function crawl(): array
    {
        $t0 = microtime(true);
        $sitemapsFromRobots = $this->loadRobots();
        $this->loadSitemaps($sitemapsFromRobots);
        $this->say(sprintf('robots: %d disallow rule(s); sitemaps: %d file(s), %d URL(s)',
            count($this->disallow), count($this->sitemapFiles), count($this->sitemapUrls)));

        $this->enqueue($this->startUrl, 0);
        foreach (array_keys($this->sitemapUrls) as $u) {
            $this->enqueue($u, null);
        }

        $pages = $this->run();

        foreach ($pages as $url => &$rec) {
            $rec['depth'] = $this->depth[$url] ?? null;
            $rec['in_sitemap'] = isset($this->sitemapUrls[$url]);
        }
        unset($rec);

        return [
            'start_url'       => $this->startUrl,
            'pages'           => $pages,
            'sitemap_urls'    => array_keys($this->sitemapUrls),
            'sitemap_files'   => $this->sitemapFiles,
            'robots_disallow' => $this->disallow,
            'skipped_robots'  => $this->skippedRobots,
            'skipped_query'   => $this->skippedQuery,
            'truncated'       => $this->truncated,
            'duration_s'      => round(microtime(true) - $t0, 1),
        ];
    }

    // ---------------------------------------------------------------- queue

    private function enqueue(string $url, ?int $depth): void
    {
        if (Url::host($url) !== $this->host) {
            return;
        }
        if ($depth !== null && (!isset($this->depth[$url]) || $depth < $this->depth[$url])) {
            $this->depth[$url] = $depth;
        }
        if (isset($this->seen[$url])) {
            return;
        }
        if (strlen($url) > self::MAX_URL_LENGTH) {
            $this->skippedQuery++;
            return;
        }
        if (parse_url($url, PHP_URL_QUERY)) {
            $key = Url::path($url);
            $this->queryVariants[$key] = ($this->queryVariants[$key] ?? 0) + 1;
            if ($this->queryVariants[$key] > self::MAX_QUERY_VARIANTS_PER_PATH) {
                $this->skippedQuery++;
                return;
            }
        }
        if ($this->isDisallowed($url)) {
            $this->skippedRobots++;
            return;
        }
        $this->seen[$url] = true;
        $this->queue->enqueue($url);
    }

    /** @return array<string,array> records keyed by URL, in fetch-completion order */
    private function run(): array
    {
        $pages = [];
        $started = 0;
        $mh = curl_multi_init();
        $active = []; // (int) handle id => job

        while (true) {
            while (count($active) < $this->concurrency && !$this->queue->isEmpty()) {
                if ($started >= $this->maxPages) {
                    $this->truncated = true;
                    break;
                }
                $url = $this->queue->dequeue();
                $job = $this->makeJob($url, Url::isAsset($url) ? 'HEAD' : 'GET');
                curl_multi_add_handle($mh, $job->ch);
                $active[(int) $job->ch] = $job;
                $started++;
            }
            if (!$active) {
                break;
            }
            do {
                $status = curl_multi_exec($mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $job = $active[(int) $ch];
                unset($active[(int) $ch]);
                curl_multi_remove_handle($mh, $ch);
                $rec = $this->finishJob($job, $info['result']);
                curl_close($ch);

                // HEAD not allowed / not understood → retry the asset with GET (not counted twice).
                if ($job->method === 'HEAD' && in_array($rec['status'], [0, 400, 403, 404, 405, 501], true) && !$job->retried) {
                    $retry = $this->makeJob($job->url, 'GET');
                    $retry->retried = true;
                    curl_multi_add_handle($mh, $retry->ch);
                    $active[(int) $retry->ch] = $retry;
                    continue;
                }
                $pages[$job->url] = $rec;
                $this->afterFetch($rec);
            }
            if (!$this->queue->isEmpty() && $started >= $this->maxPages) {
                $this->truncated = true;
            }
        }
        curl_multi_close($mh);
        if ($this->truncated) {
            $this->say("stopped at max pages ({$this->maxPages}); " . $this->queue->count() . ' URL(s) left in queue');
        }
        return $pages;
    }

    private function afterFetch(array $rec): void
    {
        $url = $rec['url'];
        $d = $this->depth[$url] ?? null;
        if ($rec['location'] !== null) {
            // A redirect is "the same page" for depth purposes.
            $this->enqueue($rec['location'], $d);
        }
        foreach ($rec['links'] as $link) {
            $this->enqueue($link, $d === null ? null : $d + 1);
        }
    }

    // ---------------------------------------------------------------- fetching

    private function makeJob(string $url, string $method): object
    {
        $job = (object) ['url' => $url, 'method' => $method, 'headers' => [], 'body' => '', 'retried' => false,
                         'keepBody' => $method === 'GET' && !Url::isAsset($url)];
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->baseCurlOptions() + [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY         => $method === 'HEAD',
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use ($job) {
                $len = strlen($line);
                if (preg_match('#^HTTP/\S+\s+\d+#', $line)) {
                    $job->headers = []; // new response block
                } elseif (($p = strpos($line, ':')) !== false) {
                    $job->headers[strtolower(trim(substr($line, 0, $p)))][] = trim(substr($line, $p + 1));
                }
                return $len;
            },
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use ($job) {
                if ($job->keepBody && strlen($job->body) < self::MAX_BODY_BYTES) {
                    $job->body .= $chunk;
                }
                return strlen($chunk);
            },
        ]);
        $job->ch = $ch;
        return $job;
    }

    private function baseCurlOptions(): array
    {
        return [
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8'],
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
    }

    private function finishJob(object $job, int $curlResult): array
    {
        $ch = $job->ch;
        $status = $curlResult === CURLE_OK ? (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) : 0;
        $error = $curlResult === CURLE_OK ? null : (curl_error($ch) ?: curl_strerror($curlResult));
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        $location = null;
        if ($status >= 300 && $status < 400) {
            $raw = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: ($job->headers['location'][0] ?? null);
            $location = $raw ? Url::resolve($job->url, $raw) : null;
        }
        $rec = [
            'url'          => $job->url,
            'method'       => $job->method,
            'status'       => $status,
            'error'        => $error,
            'location'     => $location,
            'content_type' => $contentType,
            'response_ms'  => (int) round(((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME)) * 1000),
            'depth'        => null,
            'in_sitemap'   => false,
            'is_html'      => false,
            'title'        => null,
            'description'  => null,
            'canonical'    => null,
            'meta_robots'  => null,
            'x_robots_tag' => isset($job->headers['x-robots-tag']) ? implode(', ', $job->headers['x-robots-tag']) : null,
            'h1_count'     => null,
            'word_count'   => null,
            'og_title'     => null,
            'og_image'     => null,
            'links'        => [],
            'nofollow'     => false,
        ];
        $isHtml = $contentType !== null && (bool) preg_match('#^(text/html|application/xhtml\+xml)#i', $contentType);
        if ($status === 200 && $isHtml && $job->keepBody) {
            $rec['is_html'] = true;
            $rec = array_merge($rec, self::parseHtml($job->url, $job->body));
        }
        if ($rec['x_robots_tag'] !== null && stripos($rec['x_robots_tag'], 'nofollow') !== false) {
            $rec['nofollow'] = true;
        }
        return $rec;
    }

    /**
     * Extract the SEO fields from an HTML document. Public + static so the analyzer tests can reuse it.
     * description: null when the tag is absent, "" when present but empty (mirrors check-seo.mjs).
     */
    public static function parseHtml(string $url, string $html): array
    {
        $out = ['title' => null, 'description' => null, 'canonical' => null, 'meta_robots' => null,
                'h1_count' => 0, 'word_count' => 0, 'og_title' => false, 'og_image' => false,
                'links' => [], 'nofollow' => false];
        if (trim($html) === '') {
            return $out;
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        // The XML prolog makes libxml treat the bytes as UTF-8 regardless of the <meta charset>.
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($doc);

        $lc = 'translate(%s,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")';
        $metaName = fn (string $name) => $xp->query('//meta[' . sprintf($lc, '@name') . '="' . $name . '"]')->item(0);
        $metaProp = fn (string $prop) => $xp->query('//meta[' . sprintf($lc, '@property') . '="' . $prop . '"]')->item(0);

        $title = $xp->query('//title')->item(0);
        if ($title) {
            $out['title'] = trim(preg_replace('/\s+/u', ' ', $title->textContent));
        }
        $desc = $metaName('description');
        if ($desc) {
            $out['description'] = $desc->hasAttribute('content') ? $desc->getAttribute('content') : '';
        }
        $robots = $metaName('robots');
        if ($robots) {
            $out['meta_robots'] = trim($robots->getAttribute('content'));
            $out['nofollow'] = stripos($out['meta_robots'], 'nofollow') !== false || stripos($out['meta_robots'], 'none') !== false;
        }
        $base = $url;
        $baseEl = $xp->query('//base[@href]')->item(0);
        if ($baseEl) {
            $base = Url::resolve($url, $baseEl->getAttribute('href')) ?? $url;
        }
        $canon = $xp->query('//link[' . sprintf($lc, '@rel') . '="canonical"][@href]')->item(0);
        if ($canon) {
            $out['canonical'] = Url::resolve($base, $canon->getAttribute('href'));
        }
        $og = $metaProp('og:title');
        $out['og_title'] = $og !== null && trim($og->getAttribute('content')) !== '';
        $og = $metaProp('og:image');
        $out['og_image'] = $og !== null && trim($og->getAttribute('content')) !== '';
        $out['h1_count'] = $doc->getElementsByTagName('h1')->length;

        $links = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            if (!$a->hasAttribute('href')) {
                continue;
            }
            $abs = Url::resolve($base, $a->getAttribute('href'));
            // Same-host links only (self-links are kept; the analyzer ignores them for inlink counts).
            if ($abs !== null && Url::host($abs) === Url::host($url)) {
                $links[$abs] = true;
            }
        }
        $out['links'] = array_keys($links);

        // Join text nodes with spaces: minified HTML has no whitespace between block elements.
        $text = [];
        foreach ($xp->query('//body//text()[not(ancestor::script or ancestor::style or ancestor::noscript or ancestor::template)]') as $t) {
            $text[] = $t->nodeValue;
        }
        $out['word_count'] = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', implode(' ', $text));
        return $out;
    }

    /** Simple synchronous GET (follows redirects) for robots.txt and sitemaps. Returns [status, body]. */
    private function fetchSimple(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $this->baseCurlOptions() + [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $body = curl_exec($ch);
        $status = $body === false ? 0 : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, $body === false ? '' : $body];
    }

    // ---------------------------------------------------------------- robots.txt

    /** @return string[] Sitemap: URLs declared in robots.txt */
    private function loadRobots(): array
    {
        $root = parse_url($this->startUrl, PHP_URL_SCHEME) . '://' . $this->host;
        [$status, $body] = $this->fetchSimple($root . '/robots.txt');
        if ($status !== 200) {
            return [];
        }
        [$this->disallow, $sitemaps] = self::parseRobots($body);
        return $sitemaps;
    }

    /** @return array{0:string[],1:string[]} [Disallow rules for User-agent: *, Sitemap URLs] */
    public static function parseRobots(string $txt): array
    {
        $disallow = [];
        $sitemaps = [];
        $agents = [];
        $inRules = false;
        foreach (preg_split('/\r\n|\r|\n/', $txt) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $k = strtolower($k);
            if ($k === 'sitemap') {
                $sitemaps[] = $v;
            } elseif ($k === 'user-agent') {
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }
                $agents[] = strtolower($v);
            } elseif ($k === 'disallow' || $k === 'allow') {
                $inRules = true;
                if ($k === 'disallow' && $v !== '' && in_array('*', $agents, true)) {
                    $disallow[] = $v;
                }
            }
        }
        return [array_values(array_unique($disallow)), array_values(array_unique($sitemaps))];
    }

    private function isDisallowed(string $url): bool
    {
        $pq = Url::pathAndQuery($url);
        foreach ($this->disallow as $rule) {
            if (strpbrk($rule, '*$') === false) {
                if (str_starts_with($pq, $rule)) {
                    return true;
                }
                continue;
            }
            $re = '~^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule, '~')) . '~';
            if (preg_match($re, $pq)) {
                return true;
            }
        }
        return false;
    }

    // ---------------------------------------------------------------- sitemaps

    private function loadSitemaps(array $fromRobots): void
    {
        $root = parse_url($this->startUrl, PHP_URL_SCHEME) . '://' . $this->host;
        $candidates = $fromRobots ?: [$root . '/sitemap-index.xml', $root . '/sitemap.xml'];
        $queue = [];
        foreach ($candidates as $c) {
            $queue[] = $c;
            if (!$fromRobots) {
                // Fallback mode: stop at the first sitemap that exists.
                if ($this->readSitemapTree([$c])) {
                    return;
                }
                $queue = [];
            }
        }
        if ($queue) {
            $this->readSitemapTree($queue);
        }
    }

    /** Breadth-first over sitemap indexes. Returns true if at least one sitemap file was read. */
    private function readSitemapTree(array $queue): bool
    {
        $done = [];
        $any = false;
        while ($queue && count($this->sitemapFiles) < self::MAX_SITEMAP_FILES) {
            $url = array_shift($queue);
            if (isset($done[$url])) {
                continue;
            }
            $done[$url] = true;
            [$status, $body] = $this->fetchSimple($url);
            if ($status !== 200 || $body === '') {
                continue;
            }
            if (str_starts_with($body, "\x1f\x8b")) {
                $body = @gzdecode($body) ?: '';
            }
            if (!str_contains($body, '<loc')) {
                continue;
            }
            $any = true;
            $this->sitemapFiles[] = $url;
            $isIndex = (bool) preg_match('/<sitemapindex[\s>]/i', $body);
            foreach (self::parseSitemapLocs($body) as $loc) {
                if ($isIndex) {
                    $queue[] = $loc;
                } elseif (count($this->sitemapUrls) < self::MAX_SITEMAP_URLS) {
                    $n = Url::normalize($loc);
                    if ($n !== null) {
                        $this->sitemapUrls[$n] = true;
                    }
                }
            }
        }
        return $any;
    }

    /** @return string[] */
    public static function parseSitemapLocs(string $xml): array
    {
        preg_match_all('#<loc>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*</loc>#is', $xml, $m);
        return array_map(fn ($s) => html_entity_decode(trim($s), ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1]);
    }

    private function say(string $msg): void
    {
        if ($this->log) {
            ($this->log)($msg);
        }
    }
}
