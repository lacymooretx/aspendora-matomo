<?php

namespace Piwik\Plugins\AspendoraCustomReports;

/**
 * Curated dimension/metric catalog. Two query scopes:
 *  - "visit": one row per visit (log_visit)
 *  - "action": one row per pageview/event (log_link_visit_action + log_action)
 * Every dimension is a whitelisted SQL expression — user input never reaches SQL as text.
 */
class Catalog
{
    public static function dimensions(): array
    {
        return [
            // ---- visit scope
            'channel'      => ['scope' => 'visit', 'label' => 'Channel (referrer type)',
                'sql' => "CASE v.referer_type WHEN 1 THEN 'Direct' WHEN 2 THEN CONCAT('Search: ', COALESCE(NULLIF(v.referer_name,''),'unknown')) WHEN 3 THEN CONCAT('Website: ', COALESCE(NULLIF(v.referer_name,''),'unknown')) WHEN 6 THEN CONCAT('Campaign: ', COALESCE(NULLIF(v.referer_name,''),'unknown')) WHEN 7 THEN CONCAT('Social: ', COALESCE(NULLIF(v.referer_name,''),'unknown')) WHEN 8 THEN CONCAT('AI: ', COALESCE(NULLIF(v.referer_name,''),'unknown')) ELSE 'Other' END"],
            'referrer_name' => ['scope' => 'visit', 'label' => 'Referrer name', 'sql' => "COALESCE(NULLIF(v.referer_name,''),'(none)')"],
            'campaign'     => ['scope' => 'visit', 'label' => 'Campaign', 'sql' => "CASE WHEN v.referer_type = 6 THEN v.referer_name ELSE '(no campaign)' END"],
            'country'      => ['scope' => 'visit', 'label' => 'Country', 'sql' => "COALESCE(NULLIF(v.location_country,''),'(unknown)')"],
            'region'       => ['scope' => 'visit', 'label' => 'Region', 'sql' => "COALESCE(NULLIF(v.location_region,''),'(unknown)')"],
            'city'         => ['scope' => 'visit', 'label' => 'City', 'sql' => "COALESCE(NULLIF(v.location_city,''),'(unknown)')"],
            'device_type'  => ['scope' => 'visit', 'label' => 'Device type',
                'sql' => "CASE v.config_device_type WHEN 0 THEN 'Desktop' WHEN 1 THEN 'Smartphone' WHEN 2 THEN 'Tablet' WHEN 3 THEN 'Feature phone' WHEN 4 THEN 'Console' WHEN 5 THEN 'TV' WHEN 6 THEN 'Car browser' WHEN 7 THEN 'Smart display' WHEN 8 THEN 'Camera' WHEN 9 THEN 'Media player' WHEN 10 THEN 'Phablet' WHEN 11 THEN 'Smart speaker' WHEN 12 THEN 'Wearable' WHEN 13 THEN 'Peripheral' ELSE 'Unknown' END"],
            'browser'      => ['scope' => 'visit', 'label' => 'Browser', 'sql' => "COALESCE(NULLIF(v.config_browser_name,''),'(unknown)')"],
            'os'           => ['scope' => 'visit', 'label' => 'Operating system', 'sql' => "COALESCE(NULLIF(v.config_os,''),'(unknown)')"],
            'hour'         => ['scope' => 'visit', 'label' => 'Hour of day (UTC)', 'sql' => "HOUR(v.visit_first_action_time)"],
            'day_of_week'  => ['scope' => 'visit', 'label' => 'Day of week', 'sql' => "DAYNAME(v.visit_first_action_time)"],
            'date'         => ['scope' => 'visit', 'label' => 'Date', 'sql' => "DATE(v.visit_first_action_time)"],
            'identified'   => ['scope' => 'visit', 'label' => 'Identified (userId known)', 'sql' => "IF(COALESCE(v.user_id,'') <> '', 'Identified', 'Anonymous')"],
            'user_id'      => ['scope' => 'visit', 'label' => 'User ID', 'sql' => "COALESCE(NULLIF(v.user_id,''),'(anonymous)')"],
            // ---- action scope
            'page_path'    => ['scope' => 'action', 'label' => 'Page path',
                'sql' => "SUBSTRING_INDEX(CONCAT('/', SUBSTRING(u.name, LOCATE('/', u.name) + 1)), '?', 1)"],
            'page_title'   => ['scope' => 'action', 'label' => 'Page title', 'sql' => "t.name"],
            'event_category' => ['scope' => 'action', 'label' => 'Event category', 'sql' => "ec.name"],
            'event_action' => ['scope' => 'action', 'label' => 'Event action', 'sql' => "ea.name"],
            'event_name'   => ['scope' => 'action', 'label' => 'Event name', 'sql' => "en.name"],
        ];
    }

    public static function metrics(): array
    {
        return [
            // ---- visit scope
            'visits'        => ['scope' => 'visit', 'label' => 'Visits', 'sql' => 'COUNT(*)'],
            'unique_identities' => ['scope' => 'visit', 'label' => 'Unique identities', 'sql' => "COUNT(DISTINCT COALESCE(NULLIF(v.user_id,''), HEX(v.idvisitor)))"],
            'actions'       => ['scope' => 'visit', 'label' => 'Actions', 'sql' => 'SUM(v.visit_total_actions)'],
            'avg_duration'  => ['scope' => 'visit', 'label' => 'Avg visit duration (s)', 'sql' => 'ROUND(AVG(v.visit_total_time))'],
            'bounced'       => ['scope' => 'visit', 'label' => 'Bounced visits (1 action)', 'sql' => 'SUM(v.visit_total_actions <= 1)'],
            'identified_visits' => ['scope' => 'visit', 'label' => 'Identified visits', 'sql' => "SUM(COALESCE(v.user_id,'') <> '')"],
            // ---- action scope
            'hits'          => ['scope' => 'action', 'label' => 'Hits', 'sql' => 'COUNT(*)'],
            'unique_visits' => ['scope' => 'action', 'label' => 'Unique visits', 'sql' => 'COUNT(DISTINCT a.idvisit)'],
            'sum_value'     => ['scope' => 'action', 'label' => 'Sum of event value', 'sql' => 'ROUND(SUM(a.custom_float), 1)'],
            'avg_value'     => ['scope' => 'action', 'label' => 'Avg event value', 'sql' => 'ROUND(AVG(a.custom_float), 2)'],
        ];
    }
}
