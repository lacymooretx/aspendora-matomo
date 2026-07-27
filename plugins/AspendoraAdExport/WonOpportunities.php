<?php

namespace Piwik\Plugins\AspendoraAdExport;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Log\LoggerInterface;

/**
 * Imports won opportunities from BOTH CRMs and attributes each to the most
 * recent ad click id (AdClick event) of the same identity within 90 days.
 *
 *  - EspoCRM (CRM of record): GET Opportunity where stage=Closed Won.
 *    Env: ASPENDORA_ESPO_URL / ASPENDORA_ESPO_API_KEY. Rows keyed "espo:<id>".
 *  - GoHighLevel (legacy, migration window): GET /opportunities/search
 *    (snake_case location_id!; PIT needs opportunities.readonly).
 *    Env-gated by ASPENDORA_GHL_* — unset to kill after cutover.
 *
 * Identity resolution: the opportunity's contact email (or "ghl:<contactId>")
 * matched to Matomo visits' user_id.
 */
class WonOpportunities
{
    private const LOOKBACK_DAYS = 90;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function import(): void
    {
        $this->importFromEspo();
        $this->importFromGhl();
    }

    /** EspoCRM Closed Won opportunities — the CRM of record going forward. */
    private function importFromEspo(): void
    {
        if (!getenv('ASPENDORA_ESPO_URL') || !getenv('ASPENDORA_ESPO_API_KEY')) {
            $this->logger->warning('AspendoraAdExport: Espo env credentials not set; skipping Espo won-opportunity import');
            return;
        }
        $table = Common::prefixTable(AspendoraAdExport::TABLE);
        $imported = 0;
        $offset = 0;
        do {
            $resp = $this->espoGet('/Opportunity?' . http_build_query([
                'maxSize'             => 100,
                'offset'              => $offset,
                'select'              => 'id,name,amount,stage,closeDate,modifiedAt,contactId',
                'where[0][type]'      => 'equals',
                'where[0][attribute]' => 'stage',
                'where[0][value]'     => 'Closed Won',
            ]));
            $opps = $resp['list'] ?? [];
            foreach ($opps as $opp) {
                $oppId = 'espo:' . ($opp['id'] ?? '');
                if ($oppId === 'espo:') {
                    continue;
                }
                $exists = Db::fetchOne("SELECT COUNT(*) FROM `$table` WHERE opp_id = ?", [$oppId]);
                if ($exists) {
                    continue;
                }
                $email = $this->espoContactEmail($opp['contactId'] ?? null);
                $wonAt = $opp['closeDate'] ?? $opp['modifiedAt'] ?? null;
                $wonAt = $wonAt ? date('Y-m-d H:i:s', strtotime($wonAt)) : date('Y-m-d H:i:s');
                $click = $this->findClickForIdentity($email, null, $wonAt);
                Db::query(
                    "INSERT INTO `$table`
                     (opp_id, contact_id, email, opp_name, monetary_value, won_at, network, click_id, imported_at)
                     VALUES (?,?,?,?,?,?,?,?,NOW())",
                    [
                        substr($oppId, 0, 64), $opp['contactId'] ?? null,
                        $email !== null && $email !== '' ? substr($email, 0, 190) : null,
                        substr((string) ($opp['name'] ?? ''), 0, 190),
                        round((float) ($opp['amount'] ?? 0), 2), $wonAt,
                        $click['network'] ?? null, $click['click_id'] ?? null,
                    ]
                );
                $imported++;
            }
            $offset += 100;
        } while (count($opps) === 100 && $offset <= 2000);
        if ($imported) {
            $this->logger->info('AspendoraAdExport: imported {n} won Espo opportunities', ['n' => $imported]);
        }
    }

    private function espoContactEmail(?string $contactId): ?string
    {
        if (!$contactId) {
            return null;
        }
        try {
            $c = $this->espoGet('/Contact/' . rawurlencode($contactId));
            $email = strtolower((string) ($c['emailAddress'] ?? ''));
            return $email !== '' ? $email : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function espoGet(string $path): array
    {
        $ch = curl_init(rtrim(getenv('ASPENDORA_ESPO_URL'), '/') . '/api/v1' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['X-Api-Key: ' . getenv('ASPENDORA_ESPO_API_KEY')],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception('Espo GET ' . $path . ' failed: ' . $err);
        }
        if ($code >= 400) {
            throw new Exception('Espo GET ' . $path . ' returned HTTP ' . $code . ': ' . substr($raw, 0, 300));
        }
        return json_decode($raw, true) ?: [];
    }

    /** Legacy GHL import — env-gated; remove after migration cutover. */
    private function importFromGhl(): void
    {
        $token = getenv('ASPENDORA_GHL_TOKEN');
        $location = getenv('ASPENDORA_GHL_LOCATION_ID');
        if (!$token || !$location) {
            return;
        }
        $table = Common::prefixTable(AspendoraAdExport::TABLE);
        $page = 1;
        $imported = 0;
        do {
            $resp = $this->ghlGet('/opportunities/search?location_id=' . rawurlencode($location)
                . '&status=won&limit=100&page=' . $page);
            $opps = $resp['opportunities'] ?? [];
            foreach ($opps as $opp) {
                $oppId = $opp['id'] ?? null;
                if (!$oppId) {
                    continue;
                }
                $exists = Db::fetchOne("SELECT COUNT(*) FROM `$table` WHERE opp_id = ?", [$oppId]);
                if ($exists) {
                    continue;
                }
                $contactId = $opp['contactId'] ?? ($opp['contact']['id'] ?? null);
                $email = strtolower((string) ($opp['contact']['email'] ?? ''));
                $wonAt = $opp['lastStatusChangeAt'] ?? $opp['updatedAt'] ?? $opp['createdAt'] ?? null;
                $wonAt = $wonAt ? date('Y-m-d H:i:s', strtotime($wonAt)) : date('Y-m-d H:i:s');
                $click = $this->findClickForIdentity($email, $contactId, $wonAt);
                Db::query(
                    "INSERT INTO `$table`
                     (opp_id, contact_id, email, opp_name, monetary_value, won_at, network, click_id, imported_at)
                     VALUES (?,?,?,?,?,?,?,?,NOW())",
                    [
                        $oppId, $contactId, $email !== '' ? substr($email, 0, 190) : null,
                        substr((string) ($opp['name'] ?? ''), 0, 190),
                        round((float) ($opp['monetaryValue'] ?? 0), 2), $wonAt,
                        $click['network'] ?? null, $click['click_id'] ?? null,
                    ]
                );
                $imported++;
            }
            $page++;
        } while (count($opps) === 100 && $page <= 20);
        if ($imported) {
            $this->logger->info('AspendoraAdExport: imported {n} won opportunities', ['n' => $imported]);
        }
    }

    /** Most recent AdClick event for this identity before the won time, within lookback. */
    private function findClickForIdentity(?string $email, ?string $contactId, string $wonAt): ?array
    {
        $identities = [];
        if ($email) {
            $identities[] = $email;
        }
        if ($contactId) {
            $identities[] = 'ghl:' . $contactId;
        }
        if (!$identities) {
            return null;
        }
        $llva = Common::prefixTable('log_link_visit_action');
        $la = Common::prefixTable('log_action');
        $lv = Common::prefixTable('log_visit');
        $in = implode(',', array_fill(0, count($identities), '?'));
        $row = Db::fetchRow(
            "SELECT aa.name AS network, an.name AS click_id
             FROM `$llva` llva
             JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
             JOIN `$la` aa ON aa.idaction = llva.idaction_event_action
             JOIN `$la` an ON an.idaction = llva.idaction_name
             JOIN `$lv` v ON v.idvisit = llva.idvisit
             WHERE ac.name = 'AdClick' AND aa.name IN ('gclid','msclkid')
               AND v.user_id IN ($in)
               AND llva.server_time <= ? AND llva.server_time >= DATE_SUB(?, INTERVAL " . self::LOOKBACK_DAYS . " DAY)
             ORDER BY llva.server_time DESC LIMIT 1",
            array_merge($identities, [$wonAt, $wonAt])
        );
        return $row ?: null;
    }

    private function ghlGet(string $path): array
    {
        $ch = curl_init('https://services.leadconnectorhq.com' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . getenv('ASPENDORA_GHL_TOKEN'),
                'Content-Type: application/json',
                'Version: 2021-07-28',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception('GHL GET ' . $path . ' failed: ' . $err);
        }
        if ($code >= 400) {
            throw new Exception('GHL GET ' . $path . ' returned HTTP ' . $code . ': ' . substr($raw, 0, 300));
        }
        return json_decode($raw, true) ?: [];
    }
}
