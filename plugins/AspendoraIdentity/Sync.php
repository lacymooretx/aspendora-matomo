<?php

namespace Piwik\Plugins\AspendoraIdentity;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log\LoggerInterface;

/**
 * Aggregates identified visits (log_visit.user_id) into the identity table,
 * scores each identity, enriches it against GoHighLevel, and pushes tags back.
 *
 * Full recompute over the last 365 days each run — idempotent and cheap at
 * marketing-site volume. GHL calls are capped per run and re-checked weekly.
 *
 * Lead score (0-100):
 *   min(10, visits) * 4  +  min(40, actions)  +  15 if seen in last 7 days
 *   +  25 if a high-intent page (hotPagesPattern) was viewed in last 30 days
 */
class Sync
{
    private const GHL_CALL_CAP = 100;
    private const GHL_RECHECK_DAYS = 7;

    private const ESPO_CALL_CAP = 50;

    private GhlClient $ghl;
    private EspoClient $espo;
    private LoggerInterface $logger;

    public function __construct(GhlClient $ghl, LoggerInterface $logger, ?EspoClient $espo = null)
    {
        $this->ghl = $ghl;
        $this->espo = $espo ?? new EspoClient();
        $this->logger = $logger;
    }

    public function run(): void
    {
        $this->aggregateVisits();
        $this->flagHotIntent();
        $this->computeScores();
        // EspoCRM is the CRM of record; the GHL block below is the env-gated
        // legacy path for the migration window (unset ASPENDORA_GHL_* to kill).
        if ($this->espo->isConfigured()) {
            $this->syncToEspo();
        } else {
            $this->logger->warning('AspendoraIdentity: Espo env credentials not set; skipping CRM-of-record sync');
        }
        if ($this->ghl->isConfigured()) {
            $this->enrichFromGhl();
            $this->pushHotLeads();
        }
    }

    /**
     * Push identified visitors into EspoCRM: upsert the Lead (Contacts win if
     * one already exists), append AspPageView timeline rows for new page views,
     * and touch the engagement fields — Espo's own lifecycle-stage, engagement
     * and scoring hooks react to those saves, so no scoring is duplicated here.
     */
    private function syncToEspo(): void
    {
        $identity = Common::prefixTable(AspendoraIdentity::TABLE);
        $rows = Db::fetchAll(
            "SELECT idsite, user_id, email, first_name, last_name, espo_target, espo_synced_at, last_seen
             FROM `$identity`
             WHERE email IS NOT NULL
               AND (espo_synced_at IS NULL OR last_seen > espo_synced_at)
             ORDER BY last_seen DESC
             LIMIT " . self::ESPO_CALL_CAP
        );
        foreach ($rows as $r) {
            try {
                $target = null;
                if (!empty($r['espo_target']) && strpos($r['espo_target'], ':') !== false) {
                    [$type, $id] = explode(':', $r['espo_target'], 2);
                    $target = ['type' => $type, 'id' => $id, 'pageViewCount' => null];
                }
                if ($target === null) {
                    $target = $this->espo->findTargetByEmail($r['email'])
                        ?? $this->espo->createLead($r['email'], $r['first_name'], $r['last_name']);
                }
                if ($target['pageViewCount'] === null) {
                    $fresh = $this->espo->findTargetByEmail($r['email']);
                    $target['pageViewCount'] = $fresh['pageViewCount'] ?? 0;
                }
                $views = $this->pageViewsSince((int) $r['idsite'], $r['user_id'], $r['espo_synced_at']);
                foreach ($views as $v) {
                    $this->espo->createPageView($target['type'], $target['id'], $v['url'], $v['title'], $v['t']);
                }
                $this->espo->touchEngagement(
                    $target['type'], $target['id'],
                    $r['last_seen'], $target['pageViewCount'] + count($views)
                );
                Db::query(
                    "UPDATE `$identity` SET espo_target = ?, espo_synced_at = ? WHERE idsite = ? AND user_id = ?",
                    [$target['type'] . ':' . $target['id'], $r['last_seen'], (int) $r['idsite'], $r['user_id']]
                );
                $this->logger->info('AspendoraIdentity: synced {u} to Espo {t} ({n} new page views)', [
                    'u' => $r['user_id'], 't' => $target['type'] . ':' . $target['id'], 'n' => count($views),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('AspendoraIdentity: Espo sync failed for {u}: {m}', [
                    'u' => $r['user_id'], 'm' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @return array<int, array{url:string, title:string, t:string}> */
    private function pageViewsSince(int $idSite, string $userId, ?string $since): array
    {
        $logVisit = Common::prefixTable('log_visit');
        $logLink = Common::prefixTable('log_link_visit_action');
        $logAction = Common::prefixTable('log_action');
        $sinceSql = $since ? ' AND a.server_time > ?' : ' AND a.server_time > DATE_SUB(NOW(), INTERVAL 90 DAY)';
        $binds = [$idSite, $userId];
        if ($since) {
            $binds[] = $since;
        }
        $rows = Db::fetchAll(
            "SELECT CONCAT('https://', u.name) AS url,
                    COALESCE(t.name, '') AS title,
                    a.server_time AS t
             FROM `$logLink` a
             JOIN `$logVisit` v ON v.idvisit = a.idvisit
             JOIN `$logAction` u ON u.idaction = a.idaction_url
             LEFT JOIN `$logAction` t ON t.idaction = a.idaction_name AND a.idaction_event_category IS NULL
             WHERE a.idsite = ? AND v.user_id = ?
               AND a.idaction_url IS NOT NULL AND a.idaction_event_category IS NULL
               $sinceSql
             ORDER BY a.server_time
             LIMIT 50",
            $binds
        );
        return $rows;
    }

    private function aggregateVisits(): void
    {
        $identity = Common::prefixTable(AspendoraIdentity::TABLE);
        $logVisit = Common::prefixTable('log_visit');
        Db::query(
            "INSERT INTO `$identity` (idsite, user_id, email, first_seen, last_seen, nb_visits, nb_actions)
             SELECT idsite, user_id,
                    CASE WHEN user_id LIKE '%@%' THEN LOWER(LEFT(user_id, 190)) ELSE NULL END,
                    MIN(visit_first_action_time), MAX(visit_last_action_time),
                    COUNT(*), COALESCE(SUM(visit_total_actions), 0)
             FROM `$logVisit`
             WHERE user_id IS NOT NULL AND user_id != ''
               AND visit_last_action_time > DATE_SUB(NOW(), INTERVAL 365 DAY)
             GROUP BY idsite, user_id
             ON DUPLICATE KEY UPDATE
                email = COALESCE(VALUES(email), email),
                first_seen = LEAST(COALESCE(first_seen, VALUES(first_seen)), VALUES(first_seen)),
                last_seen = VALUES(last_seen),
                nb_visits = VALUES(nb_visits),
                nb_actions = VALUES(nb_actions)"
        );
    }

    private function flagHotIntent(): void
    {
        $identity = Common::prefixTable(AspendoraIdentity::TABLE);
        $logVisit = Common::prefixTable('log_visit');
        $logLink = Common::prefixTable('log_link_visit_action');
        $logAction = Common::prefixTable('log_action');
        Db::query("UPDATE `$identity` SET is_hot_intent = 0");
        // type 1 = TYPE_PAGE_URL
        Db::query(
            "UPDATE `$identity` i
             JOIN (SELECT DISTINCT v.idsite, v.user_id
                   FROM `$logLink` a
                   JOIN `$logVisit` v ON v.idvisit = a.idvisit
                   JOIN `$logAction` act ON act.idaction = a.idaction_url
                   WHERE v.user_id IS NOT NULL AND v.user_id != ''
                     AND a.server_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
                     AND act.type = 1
                     AND act.name REGEXP ?) hot
               ON hot.idsite = i.idsite AND hot.user_id = i.user_id
             SET i.is_hot_intent = 1",
            [AspendoraIdentity::hotPagesPattern()]
        );
    }

    private function computeScores(): void
    {
        $identity = Common::prefixTable(AspendoraIdentity::TABLE);
        Db::query(
            "UPDATE `$identity` SET lead_score = LEAST(100,
                LEAST(10, nb_visits) * 4
                + LEAST(40, nb_actions)
                + IF(last_seen > DATE_SUB(NOW(), INTERVAL 7 DAY), 15, 0)
                + IF(is_hot_intent = 1, 25, 0))"
        );
    }

    private function enrichFromGhl(): void
    {
        $identity = Common::prefixTable(AspendoraIdentity::TABLE);
        $rows = Db::fetchAll(
            "SELECT idsite, user_id, email, ghl_contact_id FROM `$identity`
             WHERE ghl_checked_at IS NULL
                OR ghl_checked_at < DATE_SUB(NOW(), INTERVAL " . self::GHL_RECHECK_DAYS . " DAY)
             ORDER BY lead_score DESC
             LIMIT " . self::GHL_CALL_CAP
        );
        foreach ($rows as $r) {
            try {
                $contact = null;
                if (strpos($r['user_id'], 'ghl:') === 0) {
                    $contact = $this->ghl->getContact(substr($r['user_id'], 4));
                } elseif (!empty($r['email'])) {
                    $contact = $this->ghl->upsertContactByEmail($r['email']);
                    if ($contact && !in_array(AspendoraIdentity::TAG_VISITOR, $contact['tags'] ?? [], true)) {
                        $this->ghl->addTags($contact['id'], [AspendoraIdentity::TAG_VISITOR]);
                    }
                }
                Db::query(
                    "UPDATE `" . $identity . "` SET ghl_checked_at = NOW(),
                        ghl_contact_id = ?, email = COALESCE(?, email),
                        first_name = ?, last_name = ?, company = ?
                     WHERE idsite = ? AND user_id = ?",
                    [
                        $contact['id'] ?? $r['ghl_contact_id'],
                        isset($contact['email']) ? strtolower($contact['email']) : null,
                        $contact['firstName'] ?? null,
                        $contact['lastName'] ?? null,
                        $contact['companyName'] ?? null,
                        (int) $r['idsite'], $r['user_id'],
                    ]
                );
            } catch (\Exception $e) {
                $this->logger->error('AspendoraIdentity: GHL enrich failed for {u}: {m}', [
                    'u' => $r['user_id'], 'm' => $e->getMessage(),
                ]);
            }
        }
        if ($rows) {
            $this->logger->info('AspendoraIdentity: enriched {n} identities from GHL', ['n' => count($rows)]);
        }
    }

    private function pushHotLeads(): void
    {
        $identity = Common::prefixTable(AspendoraIdentity::TABLE);
        $rows = Db::fetchAll(
            "SELECT idsite, user_id, ghl_contact_id FROM `$identity`
             WHERE ghl_hot_pushed = 0 AND ghl_contact_id IS NOT NULL AND lead_score >= ?
             LIMIT " . self::GHL_CALL_CAP,
            [AspendoraIdentity::hotScoreThreshold()]
        );
        foreach ($rows as $r) {
            try {
                $this->ghl->addTags($r['ghl_contact_id'], [AspendoraIdentity::TAG_HOT]);
                Db::query(
                    "UPDATE `$identity` SET ghl_hot_pushed = 1 WHERE idsite = ? AND user_id = ?",
                    [(int) $r['idsite'], $r['user_id']]
                );
                $this->logger->info('AspendoraIdentity: tagged hot lead {c}', ['c' => $r['ghl_contact_id']]);
            } catch (\Exception $e) {
                $this->logger->error('AspendoraIdentity: hot-lead tag failed for {c}: {m}', [
                    'c' => $r['ghl_contact_id'], 'm' => $e->getMessage(),
                ]);
            }
        }
    }
}
