<?php

namespace Piwik\Plugins\AspendoraCompanies;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Mail;
use Piwik\Plugins\AspendoraIdentity\AspendoraIdentity;

/**
 * Daily hot-activity digest: hot known visitors active in the last 24h
 * (from AspendoraIdentity) + companies (non-ISP orgs) seen in the last 24h.
 * Sent to ASPENDORA_ALERT_EMAIL; skipped when unset or nothing happened.
 */
class Alerts
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function send(): void
    {
        $to = AspendoraCompanies::alertEmail();
        if (!$to) {
            return;
        }
        $lines = array_merge($this->hotLeadLines(), $this->companyLines());
        if (!$lines) {
            return;
        }
        $body = '<pre style="font-family:monospace;">' . htmlspecialchars(implode("\n", $lines))
            . "</pre><p>Details: https://analytics.aspendora.com/ → Visitors → Known Visitors / Companies</p>";
        if (class_exists(\Piwik\Plugins\AspendoraInsights\Mailer::class)) {
            \Piwik\Plugins\AspendoraInsights\Mailer::send($to, '[Aspendora Analytics] Hot activity — last 24h', $body);
        } else {
            $mail = new Mail();
            $mail->setDefaultFromPiwik();
            $mail->addTo($to);
            $mail->setSubject('[Aspendora Analytics] Hot activity — last 24h');
            $mail->setWrappedHtmlBody($body);
            $mail->send();
        }
        $this->logger->info('AspendoraCompanies: hot-activity digest sent to {t} ({n} lines)', [
            't' => $to, 'n' => count($lines),
        ]);
    }

    private function hotLeadLines(): array
    {
        if (!class_exists(AspendoraIdentity::class)) {
            return [];
        }
        $identity = Common::prefixTable(AspendoraIdentity::TABLE);
        $rows = Db::fetchAll(
            "SELECT email, user_id, first_name, last_name, company, lead_score, nb_visits
             FROM `$identity`
             WHERE lead_score >= ? AND last_seen > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY lead_score DESC LIMIT 20",
            [AspendoraIdentity::hotScoreThreshold()]
        );
        $lines = [];
        foreach ($rows as $r) {
            $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            $who = $name !== '' ? $name : ($r['email'] ?: $r['user_id']);
            $company = $r['company'] ? ' (' . $r['company'] . ')' : '';
            $lines[] = sprintf('HOT LEAD  %s%s — score %d, %d visits', $who, $company, $r['lead_score'], $r['nb_visits']);
        }
        return $lines;
    }

    private function companyLines(): array
    {
        $orgs = Common::prefixTable(AspendoraCompanies::TABLE);
        $logVisit = Common::prefixTable('log_visit');
        $rows = Db::fetchAll(
            "SELECT o.org, COUNT(DISTINCT v.idvisit) nb_visits, SUM(v.visit_total_actions) nb_actions
             FROM `$logVisit` v
             JOIN `$orgs` o ON o.prefix = v.aspendora_org_prefix
             WHERE o.is_isp = 0 AND o.org IS NOT NULL
               AND v.visit_last_action_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY o.org ORDER BY nb_actions DESC LIMIT 20"
        );
        $lines = [];
        foreach ($rows as $r) {
            $lines[] = sprintf('COMPANY   %s — %d visits, %d page views', $r['org'], $r['nb_visits'], (int) $r['nb_actions']);
        }
        return $lines;
    }
}
