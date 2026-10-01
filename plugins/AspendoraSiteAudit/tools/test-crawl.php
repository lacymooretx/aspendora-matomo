<?php
/**
 * Standalone crawl + analyze (no Matomo needed). CLI only.
 *
 *   php tools/test-crawl.php https://www.aspendora.com/ [maxPages=300] ['["^/(sitting-duck|aspirin)/$"]']
 *
 * Prints the page count, issue counts by code and up to 3 sample issues per code.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../Url.php';
require __DIR__ . '/../Crawler.php';
require __DIR__ . '/../Analyzer.php';

use Piwik\Plugins\AspendoraSiteAudit\Analyzer;
use Piwik\Plugins\AspendoraSiteAudit\Crawler;

$start = $argv[1] ?? 'https://www.aspendora.com/';
$max = (int) ($argv[2] ?? 300);
$orphanOk = isset($argv[3]) ? (json_decode($argv[3], true) ?: []) : [];

$crawler = new Crawler($start, $max, 4, fn ($m) => fwrite(STDERR, "  $m\n"));
$crawl = $crawler->crawl();
$issues = (new Analyzer($crawl, $orphanOk))->analyze();

$pages = $crawl['pages'];
$html = count(array_filter($pages, fn ($p) => $p['is_html']));
$statuses = array_count_values(array_map(fn ($p) => (string) $p['status'], $pages));
ksort($statuses);
printf("start %s — %d URLs fetched (%d HTML 200), %d sitemap URLs, %.1fs, truncated=%s, robots-skipped=%d\n",
    $crawl['start_url'], count($pages), $html, count($crawl['sitemap_urls']), $crawl['duration_s'],
    $crawl['truncated'] ? 'yes' : 'no', $crawl['skipped_robots']);
echo 'status codes: ' . json_encode($statuses) . "\n";
$sev = Analyzer::countBySeverity($issues);
printf("issues: %d errors, %d warnings, %d notices\n", $sev['error'], $sev['warning'], $sev['notice']);

$byCode = [];
foreach ($issues as $i) {
    $byCode[$i['code']][] = $i;
}
uksort($byCode, fn ($a, $b) => [Analyzer::SEVERITY_ORDER[Analyzer::ISSUES[$a][0]], $a] <=> [Analyzer::SEVERITY_ORDER[Analyzer::ISSUES[$b][0]], $b]);
foreach ($byCode as $code => $list) {
    printf("\n[%s] %s — %d issue(s), %d URL(s)\n", Analyzer::ISSUES[$code][0], $code, count($list), count(array_unique(array_column($list, 'url'))));
    foreach (array_slice($list, 0, 3) as $i) {
        printf("    %s\n      %s\n", $i['url'], mb_strimwidth($i['detail'], 0, 220, '…'));
    }
}
