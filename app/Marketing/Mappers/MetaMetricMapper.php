<?php

namespace App\Marketing\Mappers;

class MetaMetricMapper
{
    /** @var array<string, string> */
    protected static array $map = [
        'impressions' => 'impressions',
        'reach' => 'reach',
        'clicks' => 'clicks',
        'inline_link_clicks' => 'clicks',
        'spend' => 'spend',
        'actions_lead' => 'leads',
        'actions_purchase' => 'conversions',
        'action_values_purchase' => 'attributed_revenue',
        'post_engagements' => 'engagements',
        'likes' => 'likes',
        'comments' => 'comments',
        'shares' => 'shares',
        'saved' => 'saves',
        'video_views' => 'views',
        'page_fans' => 'followers',
        'followers_count' => 'followers',
    ];

    public static function toCanonical(string $providerField): ?string
    {
        return self::$map[$providerField] ?? null;
    }

    /**
     * @param  array{provider_field: string, value: mixed, date: string, currency?: ?string}  $row
     * @return array{metric_key: string, metric_value: float, metric_date: string, currency: ?string}|null
     */
    public static function mapRow(array $row): ?array
    {
        $code = self::toCanonical($row['provider_field']);
        if (! $code) {
            return null;
        }

        return [
            'metric_key' => $code,
            'metric_value' => (float) $row['value'],
            'metric_date' => $row['date'],
            'currency' => $row['currency'] ?? null,
        ];
    }
}
