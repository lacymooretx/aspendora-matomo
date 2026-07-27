<?php

namespace Piwik\Plugins\AspendoraInsights;

use Piwik\Access;
use Piwik\Log\LoggerInterface;
use Piwik\Mail;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;

/**
 * Builds and emails the weekly digest: one branded, plain-language HTML
 * section per site, written by Claude from the week-over-week stats bundle.
 * White-label / client-presentable on purpose — no Matomo branding, no
 * jargon, safe to forward.
 */
class Digest
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You write weekly website analytics summaries for a busy managed-IT-services business owner.
You receive a JSON stats bundle for one website: this week vs last week traffic, top pages,
referrer types, Google search keywords, identified visitors with lead scores, companies whose
networks visited, funnel step counts, and JavaScript errors.

Write an HTML fragment (no <html>/<head>/<body> — just content elements: <h3>, <p>, <ul>, <strong>)
with exactly these sections:
<h3>What happened</h3> — 2-4 sentences: the week's traffic story with the numbers that matter and
the likely why (compare to previous week; call out notable pages, referrers, or keywords).
<h3>Leads &amp; opportunities</h3> — 1-3 sentences on identified visitors, hot leads, and company
visits worth a follow-up. If there are none, say so in one sentence.
<h3>Do this next</h3> — a <ul> of 2-3 specific, small actions grounded in the data (e.g. a page to
improve, a keyword to target, a lead to contact, an error to fix). Skip generic advice.

Rules: plain language a non-analyst reads in 60 seconds; no headers beyond the three above; no
invented numbers — only what is in the data; if a data section is empty, do not fabricate content
for it; total under 250 words.
PROMPT;

    private ClaudeClient $claude;
    private LoggerInterface $logger;

    public function __construct(ClaudeClient $claude, LoggerInterface $logger)
    {
        $this->claude = $claude;
        $this->logger = $logger;
    }

    public function send(): void
    {
        $to = AspendoraInsights::recipient();
        if (!$to) {
            $this->logger->warning('AspendoraInsights: no recipient configured (ASPENDORA_INSIGHTS_EMAIL / ASPENDORA_ALERT_EMAIL); skipping digest');
            return;
        }
        if (!$this->claude->isConfigured()) {
            $this->logger->warning('AspendoraInsights: ASPENDORA_ANTHROPIC_KEY not set; skipping digest');
            return;
        }
        $sections = [];
        $sites = Access::doAsSuperUser(function () {
            $ids = SitesManagerAPI::getInstance()->getAllSitesId();
            $out = [];
            foreach ($ids as $id) {
                $site = SitesManagerAPI::getInstance()->getSiteFromId((int) $id);
                $out[(int) $id] = $site['name'] ?? ('Site ' . $id);
            }
            return $out;
        });
        foreach ($sites as $idSite => $name) {
            try {
                $stats = Access::doAsSuperUser(fn() => (new StatsCollector())->collect($idSite));
                $narrative = $this->claude->complete(
                    self::SYSTEM_PROMPT,
                    "Website: {$name}\nStats bundle:\n" . json_encode($stats)
                );
                $sections[] = '<h2 style="color:#1a3c34;border-bottom:2px solid #1a3c34;padding-bottom:4px;">'
                    . htmlspecialchars($name) . '</h2>' . $narrative;
                $this->logger->info('AspendoraInsights: narrative generated for site {s}', ['s' => $idSite]);
            } catch (\Exception $e) {
                $this->logger->error('AspendoraInsights: digest failed for site {s}: {m}', [
                    's' => $idSite, 'm' => $e->getMessage(),
                ]);
            }
        }
        if (!$sections) {
            return;
        }
        $html = '<div style="font-family:Georgia,serif;max-width:640px;margin:0 auto;color:#222;line-height:1.5;">'
            . '<p style="font-size:18px;font-weight:bold;color:#1a3c34;">Aspendora Analytics — Weekly Insights</p>'
            . '<p style="color:#666;font-size:13px;">Week ending ' . date('F j, Y') . '</p>'
            . implode('<div style="height:24px;"></div>', $sections)
            . '<p style="color:#999;font-size:12px;margin-top:32px;">Prepared automatically by Aspendora Analytics.</p>'
            . '</div>';
        $mail = new Mail();
        $mail->setDefaultFromPiwik();
        $mail->addTo($to);
        $mail->setSubject('Aspendora Analytics — Weekly Insights (' . date('M j') . ')');
        $mail->setWrappedHtmlBody($html);
        $mail->send();
        $this->logger->info('AspendoraInsights: weekly digest sent to {t} ({n} sites)', [
            't' => $to, 'n' => count($sections),
        ]);
    }
}
