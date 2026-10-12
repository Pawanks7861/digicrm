<?php

namespace App\Marketing;

class MarketingMetricRegistry
{
    public const AGG_SUM = 'sum';
    public const AGG_LATEST = 'latest_value';
    public const AGG_DERIVED = 'derived';

    /**
     * @return array<string, array{
     *   code: string, label: string, category: string, unit: string,
     *   aggregation: string, is_percent: bool, is_currency: bool,
     *   providers: list<string>, direction: string, formula?: string, formula_label?: string
     * }>
     */
    public static function all(): array
    {
        $defs = [
            // Awareness
            ['impressions', 'Impressions', 'awareness', 'count', self::AGG_SUM, false, false, ['meta', 'google_ads', 'youtube'], 'higher'],
            ['reach', 'Reach', 'awareness', 'count', self::AGG_SUM, false, false, ['meta'], 'higher'],
            ['views', 'Views', 'awareness', 'count', self::AGG_SUM, false, false, ['youtube', 'meta'], 'higher'],
            // Engagement
            ['clicks', 'Clicks', 'engagement', 'count', self::AGG_SUM, false, false, ['meta', 'google_ads', 'ga4'], 'higher'],
            ['likes', 'Likes', 'engagement', 'count', self::AGG_SUM, false, false, ['meta', 'youtube'], 'higher'],
            ['comments', 'Comments', 'engagement', 'count', self::AGG_SUM, false, false, ['meta', 'youtube'], 'higher'],
            ['shares', 'Shares', 'engagement', 'count', self::AGG_SUM, false, false, ['meta'], 'higher'],
            ['saves', 'Saves', 'engagement', 'count', self::AGG_SUM, false, false, ['meta'], 'higher'],
            ['engagements', 'Engagements', 'engagement', 'count', self::AGG_SUM, false, false, ['meta', 'youtube'], 'higher'],
            // Traffic
            ['sessions', 'Sessions', 'traffic', 'count', self::AGG_SUM, false, false, ['ga4'], 'higher'],
            ['users', 'Users', 'traffic', 'count', self::AGG_SUM, false, false, ['ga4'], 'higher'],
            ['pageviews', 'Pageviews', 'traffic', 'count', self::AGG_SUM, false, false, ['ga4'], 'higher'],
            // Leads / conversions
            ['leads', 'Leads', 'leads', 'count', self::AGG_SUM, false, false, ['meta', 'google_ads', 'manual'], 'higher'],
            ['conversions', 'Conversions', 'conversions', 'count', self::AGG_SUM, false, false, ['meta', 'google_ads', 'ga4', 'manual'], 'higher'],
            ['attributed_revenue', 'Attributed Revenue', 'conversions', 'currency', self::AGG_SUM, false, true, ['google_ads', 'meta', 'manual'], 'higher'],
            // Paid
            ['spend', 'Ad Spend', 'paid', 'currency', self::AGG_SUM, false, true, ['meta', 'google_ads'], 'lower'],
            // Social growth
            ['followers', 'Followers', 'social_growth', 'count', self::AGG_LATEST, false, false, ['meta', 'youtube'], 'higher'],
            ['subscribers', 'Subscribers', 'social_growth', 'count', self::AGG_LATEST, false, false, ['youtube'], 'higher'],
            // Derived
            ['ctr', 'CTR', 'engagement', 'percent', self::AGG_DERIVED, true, false, ['meta', 'google_ads'], 'higher', 'clicks/impressions*100', 'CTR = clicks ÷ impressions × 100'],
            ['cpc', 'CPC', 'paid', 'currency', self::AGG_DERIVED, false, true, ['meta', 'google_ads'], 'lower', 'spend/clicks', 'CPC = spend ÷ clicks'],
            ['cpl', 'CPL', 'leads', 'currency', self::AGG_DERIVED, false, true, ['meta', 'google_ads'], 'lower', 'spend/leads', 'CPL = spend ÷ leads'],
            ['cpa', 'CPA', 'conversions', 'currency', self::AGG_DERIVED, false, true, ['meta', 'google_ads'], 'lower', 'spend/conversions', 'CPA = spend ÷ conversions'],
            ['conversion_rate', 'Conversion Rate', 'conversions', 'percent', self::AGG_DERIVED, true, false, ['meta', 'google_ads', 'ga4'], 'higher', 'conversions/clicks*100', 'ConvRate = conversions ÷ clicks × 100'],
            ['roas', 'ROAS', 'paid', 'ratio', self::AGG_DERIVED, false, false, ['meta', 'google_ads'], 'higher', 'attributed_revenue/spend', 'ROAS = attributed revenue ÷ spend'],
            ['engagement_rate_by_reach', 'Engagement Rate (by reach)', 'engagement', 'percent', self::AGG_DERIVED, true, false, ['meta'], 'higher', 'engagements/reach*100', 'Engagement rate by reach'],
            ['engagement_rate_by_impressions', 'Engagement Rate (by impressions)', 'engagement', 'percent', self::AGG_DERIVED, true, false, ['meta'], 'higher', 'engagements/impressions*100', 'Engagement rate by impressions'],
            ['engagement_rate_by_followers', 'Engagement Rate (by followers)', 'engagement', 'percent', self::AGG_DERIVED, true, false, ['meta'], 'higher', 'engagements/followers*100', 'Engagement rate by followers'],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[$d[0]] = [
                'code' => $d[0],
                'label' => $d[1],
                'category' => $d[2],
                'unit' => $d[3],
                'aggregation' => $d[4],
                'is_percent' => $d[5],
                'is_currency' => $d[6],
                'providers' => $d[7],
                'direction' => $d[8],
                'formula' => $d[9] ?? null,
                'formula_label' => $d[10] ?? null,
            ];
        }

        return $out;
    }

    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    public static function aggregation(string $code): string
    {
        return self::get($code)['aggregation'] ?? self::AGG_SUM;
    }

    public static function label(string $code): string
    {
        return self::get($code)['label'] ?? $code;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }
}
