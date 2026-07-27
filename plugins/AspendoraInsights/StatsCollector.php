<?php

namespace Piwik\Plugins\AspendoraInsights;

use Piwik\API\Request;

/**
 * Gathers a compact week-over-week stats bundle for one site by calling
 * Matomo's internal reporting APIs. Kept deliberately small — the output is
 * the LLM prompt payload, so only decision-relevant numbers are included.
 */
class StatsCollector
{
    public function collect(int $idSite): array
    {
        $thisWeek = 'last7';
        $prevWeek = date('Y-m-d', strtotime('-14 days')) . ',' . date('Y-m-d', strtotime('-8 days'));

        return [
            'traffic' => [
                'this_week' => $this->fetch('VisitsSummary.get', $idSite, 'range', $thisWeek),
                'prev_week' => $this->fetch('VisitsSummary.get', $idSite, 'range', $prevWeek),
            ],
            'top_pages'      => $this->rows($this->fetch('Actions.getPageUrls', $idSite, 'range', $thisWeek, ['flat' => 1, 'filter_limit' => 10]), ['label', 'nb_hits', 'nb_visits']),
            'referrer_types' => $this->rows($this->fetch('Referrers.getReferrerType', $idSite, 'range', $thisWeek), ['label', 'nb_visits']),
            'gsc_keywords'   => $this->rows($this->fetch('AspendoraSearchKeywords.getKeywords', $idSite, 'range', $thisWeek, ['filter_limit' => 10]), ['label', 'nb_clicks', 'nb_impressions', 'avg_position']),
            'known_visitors' => $this->rows($this->fetch('AspendoraIdentity.getKnownVisitors', $idSite, 'range', $thisWeek, ['filter_limit' => 10]), ['label', 'company', 'nb_visits', 'lead_score']),
            'companies'      => $this->rows($this->fetch('AspendoraCompanies.getCompanies', $idSite, 'range', $thisWeek, ['filter_limit' => 10]), ['label', 'nb_visits', 'nb_actions']),
            'funnels'        => $this->rows($this->fetch('AspendoraFunnels.getFunnels', $idSite, 'range', $thisWeek), ['label', 'nb_visits', 'step_rate']),
            'js_errors'      => $this->rows($this->fetch('AspendoraCrash.getJsErrors', $idSite, 'range', $thisWeek, ['filter_limit' => 5]), ['label', 'nb_occurrences']),
        ];
    }

    private function fetch(string $method, int $idSite, string $period, string $date, array $extra = []): array
    {
        try {
            $result = Request::processRequest($method, array_merge([
                'idSite'     => $idSite,
                'period'     => $period,
                'date'       => $date,
                'format'     => 'original',
                'serialize'  => 0,
            ], $extra));
            if ($result instanceof \Piwik\DataTable || $result instanceof \Piwik\DataTable\Map) {
                $out = [];
                $tables = $result instanceof \Piwik\DataTable\Map ? $result->getDataTables() : [$result];
                foreach ($tables as $t) {
                    foreach ($t->getRows() as $row) {
                        $out[] = $row->getColumns();
                    }
                }
                return $out;
            }
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /** Keep only the named columns from each row — trims the LLM payload. */
    private function rows(array $rows, array $cols): array
    {
        $out = [];
        foreach (array_slice($rows, 0, 10) as $row) {
            $slim = [];
            foreach ($cols as $c) {
                if (isset($row[$c])) {
                    $slim[$c] = $row[$c];
                }
            }
            if ($slim) {
                $out[] = $slim;
            }
        }
        return $out;
    }
}
