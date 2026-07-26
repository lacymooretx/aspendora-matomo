<?php

namespace Piwik\Plugins\AspendoraAttribution;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;

/**
 * Channel attribution across six models. Journey = the converting identity's visits in the
 * 30 days before conversion (identity = user_id when set, else visitor ID — cookieless
 * caveat: anonymous cross-day journeys fragment, so most anonymous credit lands on the
 * conversion visit's own channel).
 */
class API extends \Piwik\Plugin\API
{
    private const MODELS = ['last', 'last_non_direct', 'first', 'linear', 'position', 'time_decay'];
    private const LOOKBACK_DAYS = 30;
    private const DECAY_HALFLIFE_DAYS = 7.0;

    public function getChannelAttribution($idSite, $period, $date)
    {
        Piwik::checkUserHasViewAccess($idSite);
        $idSite = (int) $idSite;
        $periodObj = PeriodFactory::build($period, $date);
        $start = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $end = $periodObj->getDateEnd()->toString('Y-m-d') . ' 23:59:59';

        $conversions = $this->loadConversions($idSite, $start, $end);
        if (!$conversions) {
            return new DataTable();
        }
        $identities = array_unique(array_column($conversions, 'identity'));
        $journeys = $this->loadJourneys($idSite, $identities, $start);

        $credit = []; // channel => model => credit
        foreach ($conversions as $conv) {
            $visits = array_values(array_filter($journeys[$conv['identity']] ?? [], function ($v) use ($conv) {
                $t = strtotime($conv['time']);
                $vt = strtotime($v['time']);
                return $vt <= $t && $vt >= $t - self::LOOKBACK_DAYS * 86400;
            }));
            if (!$visits) {
                continue;
            }
            $touch = array_map(fn($v) => $v['channel'], $visits);
            $n = count($touch);
            $add = function (string $ch, string $model, float $c) use (&$credit) {
                $credit[$ch][$model] = ($credit[$ch][$model] ?? 0) + $c;
            };

            // last
            $add($touch[$n - 1], 'last', 1.0);
            // last non-direct
            $lnd = $touch[$n - 1];
            for ($i = $n - 1; $i >= 0; $i--) {
                if ($touch[$i] !== 'Direct') { $lnd = $touch[$i]; break; }
            }
            $add($lnd, 'last_non_direct', 1.0);
            // first
            $add($touch[0], 'first', 1.0);
            // linear
            foreach ($touch as $ch) {
                $add($ch, 'linear', 1.0 / $n);
            }
            // position based 40/20/40
            if ($n === 1) {
                $add($touch[0], 'position', 1.0);
            } elseif ($n === 2) {
                $add($touch[0], 'position', 0.5);
                $add($touch[1], 'position', 0.5);
            } else {
                $add($touch[0], 'position', 0.4);
                $add($touch[$n - 1], 'position', 0.4);
                for ($i = 1; $i < $n - 1; $i++) {
                    $add($touch[$i], 'position', 0.2 / ($n - 2));
                }
            }
            // time decay
            $convT = strtotime($conv['time']);
            $weights = [];
            foreach ($visits as $i => $v) {
                $days = ($convT - strtotime($v['time'])) / 86400;
                $weights[$i] = pow(0.5, $days / self::DECAY_HALFLIFE_DAYS);
            }
            $sum = array_sum($weights) ?: 1;
            foreach ($visits as $i => $v) {
                $add($v['channel'], 'time_decay', $weights[$i] / $sum);
            }
        }

        $dt = new DataTable();
        uasort($credit, fn($a, $b) => ($b['last'] ?? 0) <=> ($a['last'] ?? 0));
        foreach ($credit as $channel => $models) {
            $row = ['label' => $channel];
            foreach (self::MODELS as $m) {
                $row[$m] = round($models[$m] ?? 0, 2);
            }
            $dt->addRowFromSimpleArray($row);
        }
        return $dt;
    }

    /** @return array<int, array{identity:string, time:string}> */
    private function loadConversions(int $idSite, string $start, string $end): array
    {
        $out = [];
        $lc = Common::prefixTable('log_conversion');
        $lv = Common::prefixTable('log_visit');
        foreach (Db::fetchAll(
            "SELECT COALESCE(NULLIF(v.user_id, ''), HEX(v.idvisitor)) AS identity, c.server_time AS t
             FROM `$lc` c JOIN `$lv` v ON v.idvisit = c.idvisit
             WHERE c.idsite = ? AND c.server_time BETWEEN ? AND ? AND c.idgoal >= 0
             LIMIT 20000",
            [$idSite, $start, $end]
        ) as $r) {
            $out[] = ['identity' => $r['identity'], 'time' => $r['t']];
        }
        $cats = AspendoraAttribution::conversionEventCategories();
        $in = implode(',', array_fill(0, count($cats), '?'));
        $llva = Common::prefixTable('log_link_visit_action');
        $la = Common::prefixTable('log_action');
        foreach (Db::fetchAll(
            "SELECT COALESCE(NULLIF(v.user_id, ''), HEX(v.idvisitor)) AS identity, llva.server_time AS t
             FROM `$llva` llva
             JOIN `$la` ac ON ac.idaction = llva.idaction_event_category
             JOIN `$lv` v ON v.idvisit = llva.idvisit
             WHERE llva.idsite = ? AND llva.server_time BETWEEN ? AND ? AND ac.name IN ($in)
             LIMIT 20000",
            array_merge([$idSite, $start, $end], $cats)
        ) as $r) {
            $out[] = ['identity' => $r['identity'], 'time' => $r['t']];
        }
        return $out;
    }

    /** @return array<string, array<int, array{time:string, channel:string}>> */
    private function loadJourneys(int $idSite, array $identities, string $periodStart): array
    {
        if (!$identities) {
            return [];
        }
        $lookbackStart = date('Y-m-d H:i:s', strtotime($periodStart) - self::LOOKBACK_DAYS * 86400);
        $lv = Common::prefixTable('log_visit');
        $in = implode(',', array_fill(0, count($identities), '?'));
        $rows = Db::fetchAll(
            "SELECT COALESCE(NULLIF(user_id, ''), HEX(idvisitor)) AS identity,
                    visit_first_action_time AS t, referer_type, referer_name
             FROM `$lv`
             WHERE idsite = ? AND visit_first_action_time >= ?
               AND COALESCE(NULLIF(user_id, ''), HEX(idvisitor)) IN ($in)
             ORDER BY visit_first_action_time
             LIMIT 50000",
            array_merge([$idSite, $lookbackStart], $identities)
        );
        $journeys = [];
        foreach ($rows as $r) {
            $journeys[$r['identity']][] = [
                'time'    => $r['t'],
                'channel' => self::channel((int) $r['referer_type'], (string) $r['referer_name']),
            ];
        }
        return $journeys;
    }

    public static function channel(int $refererType, string $name): string
    {
        switch ($refererType) {
            case Common::REFERRER_TYPE_SEARCH_ENGINE:
                return 'Search: ' . ($name ?: 'unknown');
            case Common::REFERRER_TYPE_WEBSITE:
                return 'Website: ' . ($name ?: 'unknown');
            case Common::REFERRER_TYPE_CAMPAIGN:
                return 'Campaign: ' . ($name ?: 'unknown');
            case Common::REFERRER_TYPE_SOCIAL_NETWORK:
                return 'Social: ' . ($name ?: 'unknown');
            case Common::REFERRER_TYPE_AI_ASSISTANT:
                return 'AI Assistant: ' . ($name ?: 'unknown');
            case Common::REFERRER_TYPE_DIRECT_ENTRY:
            default:
                return 'Direct';
        }
    }
}
