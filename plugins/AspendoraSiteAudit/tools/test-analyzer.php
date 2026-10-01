<?php
/**
 * Seed-defect test for the Analyzer: synthetic crawl records with known problems; asserts each is detected
 * (and that the clean page raises nothing). CLI only. Exit code 0 = pass.
 *
 *   php tools/test-analyzer.php
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../Url.php';
require __DIR__ . '/../Crawler.php';
require __DIR__ . '/../Analyzer.php';

use Piwik\Plugins\AspendoraSiteAudit\Analyzer;
use Piwik\Plugins\AspendoraSiteAudit\Crawler;
use Piwik\Plugins\AspendoraSiteAudit\Url;

const S = 'https://example.test';
$goodDesc = str_repeat('Managed IT and cybersecurity for small business. ', 3); // 147 chars
$goodDesc = trim(substr($goodDesc, 0, 140));

function page(string $path, array $o = []): array
{
    global $goodDesc;
    return array_merge([
        'url' => S . $path, 'method' => 'GET', 'status' => 200, 'error' => null, 'location' => null,
        'content_type' => 'text/html; charset=utf-8', 'response_ms' => 120, 'depth' => 1, 'in_sitemap' => true,
        'is_html' => true, 'title' => 'Page ' . $path, 'description' => $goodDesc . ' ' . $path,
        'canonical' => S . $path, 'meta_robots' => null, 'x_robots_tag' => null, 'h1_count' => 1, 'word_count' => 500,
        'og_title' => true, 'og_image' => true, 'links' => [], 'nofollow' => false,
    ], $o);
}
function redirect(string $path, string $to, int $code = 301): array
{
    return page($path, ['status' => $code, 'location' => S . $to, 'is_html' => false, 'title' => null,
        'description' => null, 'canonical' => null, 'h1_count' => null, 'in_sitemap' => false, 'content_type' => null]);
}

$all = ['/clean/', '/long-title/', '/empty-desc/', '/broken-linker/', '/chain-linker/', '/orphan/', '/campaign/',
        '/noindex/', '/dup-a/', '/dup-b/', '/two-h1/', '/canon-other/', '/not-in-sitemap/', '/slow/', '/no-og/', '/short-desc/'];
$home = page('/', ['links' => array_map(fn ($p) => S . $p, array_diff($all, ['/orphan/', '/campaign/']))]);
$pages = [
    page('/clean/', ['links' => [S . '/']]),
    page('/long-title/', ['title' => str_repeat('x', 61)]),
    page('/empty-desc/', ['description' => '']),
    page('/broken-linker/', ['links' => [S . '/gone/', S . '/to-gone']]),
    page('/gone/', ['status' => 404, 'is_html' => false, 'in_sitemap' => true, 'title' => null]),
    redirect('/to-gone', '/gone/'),
    page('/chain-linker/', ['links' => [S . '/r1', S . '/loop-a']]),
    redirect('/r1', '/r2'),
    redirect('/r2', '/clean/', 302),
    redirect('/loop-a', '/loop-b'),
    redirect('/loop-b', '/loop-a'),
    page('/orphan/'),
    page('/campaign/'),
    page('/noindex/', ['meta_robots' => 'noindex, follow']),
    page('/dup-a/', ['title' => 'Same Title', 'description' => $goodDesc]),
    page('/dup-b/', ['title' => 'Same Title', 'description' => $goodDesc]),
    page('/two-h1/', ['h1_count' => 2]),
    page('/canon-other/', ['canonical' => S . '/clean/']),
    page('/not-in-sitemap/', ['in_sitemap' => false]),
    page('/slow/', ['response_ms' => 2500]),
    page('/no-og/', ['og_image' => false]),
    page('/short-desc/', ['description' => 'Too short.']),
    page('/dead/', ['status' => 0, 'error' => 'Operation timed out', 'is_html' => false, 'in_sitemap' => false]),
    page('/xrobots/', ['x_robots_tag' => 'noindex', 'in_sitemap' => false, 'links' => [S . '/']]),
];
$crawl = ['start_url' => S . '/', 'pages' => [], 'sitemap_urls' => []];
foreach (array_merge([$home], $pages) as $p) {
    $crawl['pages'][$p['url']] = $p;
    if ($p['in_sitemap']) {
        $crawl['sitemap_urls'][] = $p['url'];
    }
}

$issues = (new Analyzer($crawl, ['^/campaign/$']))->analyze();
$has = function (string $code, string $path, ?string $detailContains = null) use ($issues): bool {
    foreach ($issues as $i) {
        if ($i['code'] === $code && $i['url'] === S . $path && ($detailContains === null || str_contains($i['detail'], $detailContains))) {
            return true;
        }
    }
    return false;
};

$expect = [
    ['title_too_long', '/long-title/', 'title 61 chars (max 60)'],
    ['desc_missing', '/empty-desc/', null],
    ['broken_internal_link', '/broken-linker/', S . '/gone/'],
    ['broken_internal_link', '/broken-linker/', 'via redirect'],
    ['http_4xx', '/gone/', 'HTTP 404'],
    ['redirect_chain', '/r1', '2 hops'],
    ['redirect_loop', '/loop-a', null],
    ['links_to_redirect', '/chain-linker/', S . '/r1 → ' . S . '/clean/'],
    ['redirect', '/r1', '301 →'],
    ['orphan', '/orphan/', null],
    ['noindex_in_sitemap', '/noindex/', null],
    ['noindex_page', '/noindex/', 'meta robots'],
    ['noindex_page', '/xrobots/', 'X-Robots-Tag'],
    ['duplicate_title', '/dup-a/', S . '/dup-b/'],
    ['duplicate_description', '/dup-b/', S . '/dup-a/'],
    ['h1_multiple', '/two-h1/', null],
    ['canonical_mismatch', '/canon-other/', S . '/clean/'],
    ['indexable_not_in_sitemap', '/not-in-sitemap/', null],
    ['sitemap_url_not_200', '/gone/', 'HTTP 404'],
    ['slow_page', '/slow/', '2500 ms'],
    ['og_tags_missing', '/no-og/', 'og:image'],
    ['desc_too_short', '/short-desc/', 'description 10 chars'],
    ['connection_failed', '/dead/', 'timed out'],
];
$notExpected = [
    ['orphan', '/campaign/'],          // ORPHAN_OK
    ['orphan', '/'],                   // start URL
    ['orphan', '/noindex/'],           // not indexable
    ['redirect_chain', '/r2'],         // single hop
    ['indexable_not_in_sitemap', '/xrobots/'],
];
$fail = 0;
foreach ($expect as [$code, $path, $d]) {
    $ok = $has($code, $path, $d);
    $fail += $ok ? 0 : 1;
    printf("%s  %-26s %s%s\n", $ok ? 'PASS' : 'FAIL', $code, $path, $d ? "  (detail ~ \"$d\")" : '');
}
foreach ($notExpected as [$code, $path]) {
    $ok = !$has($code, $path);
    $fail += $ok ? 0 : 1;
    printf("%s  no %-23s %s\n", $ok ? 'PASS' : 'FAIL', $code, $path);
}
$clean = array_filter($issues, fn ($i) => $i['url'] === S . '/clean/');
$fail += $clean ? 1 : 0;
$truncatedIssues = (new Analyzer(['truncated' => true] + $crawl, ['^/campaign/$']))->analyze();
$noOrphans = !array_filter($truncatedIssues, fn ($i) => $i['code'] === 'orphan');
$fail += $noOrphans ? 0 : 1;
printf("%s  no orphan issues when the crawl was truncated\n", $noOrphans ? 'PASS' : 'FAIL');
printf("%s  clean page has no issues%s\n", $clean ? 'FAIL' : 'PASS', $clean ? ': ' . json_encode(array_column($clean, 'code')) : '');

// Parser + URL helpers.
$html = '<!doctype html><html><head><title> Hello &amp; welcome </title><meta name="Description" content="">'
    . '<link rel="canonical" href="/a/"><meta name="robots" content="noindex,nofollow"><meta property="og:title" content="x">'
    . '</head><body><h1>One</h1><script>var a="<h1>no</h1>";</script><p>Three short words</p>'
    . '<a href="/b/#frag">b</a><a href="../c/?q=1">c</a><a href="mailto:x@y.z">m</a><a href="https://other.test/">o</a></body></html>';
$p = Crawler::parseHtml(S . '/a/', $html);
$checks = [
    'title decoded+trimmed' => $p['title'] === 'Hello & welcome',
    'empty description is ""' => $p['description'] === '',
    'canonical resolved' => $p['canonical'] === S . '/a/',
    'meta robots + nofollow' => $p['meta_robots'] === 'noindex,nofollow' && $p['nofollow'] === true,
    'og:title yes, og:image no' => $p['og_title'] === true && $p['og_image'] === false,
    'h1 count ignores script' => $p['h1_count'] === 1,
    'word count (h1 + p + link text)' => $p['word_count'] === 8,
    'links same-host, no fragment' => $p['links'] === [S . '/b/', S . '/c/?q=1'],
    'normalize' => Url::normalize('HTTPS://Example.TEST:443/x/../y/./z#f') === S . '/y/z',
    'isAsset' => Url::isAsset(S . '/f.pdf') && !Url::isAsset(S . '/page/') && !Url::isAsset(S . '/x.html'),
    'robots parse' => Crawler::parseRobots("User-agent: Googlebot\nDisallow: /g/\n\nUser-agent: *\nDisallow: /admin/\nDisallow:\nSitemap: https://e/s.xml")
        === [['/admin/'], ['https://e/s.xml']],
    'sitemap locs' => Crawler::parseSitemapLocs('<urlset><url><loc> https://e/a?x=1&amp;y=2 </loc></url><url><loc><![CDATA[https://e/b]]></loc></url></urlset>')
        === ['https://e/a?x=1&y=2', 'https://e/b'],
];
foreach ($checks as $name => $ok) {
    $fail += $ok ? 0 : 1;
    printf("%s  parser: %s\n", $ok ? 'PASS' : 'FAIL', $name);
}
if (!$checks['links same-host, no fragment']) {
    var_dump($p['links']);
}
echo $fail ? "\n$fail FAILURE(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
