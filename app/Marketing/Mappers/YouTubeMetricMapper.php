<?php

namespace App\Marketing\Mappers;

class YouTubeMetricMapper
{
    /** @var array<string, string> */
    protected static array $map = [
        'views' => 'views',
        'likes' => 'likes',
        'comments' => 'comments',
        'shares' => 'shares',
        'subscribersGained' => 'subscribers',
        'subscribers' => 'subscribers',
        'estimatedMinutesWatched' => 'views', // not ideal; prefer views only when present
        'averageViewDuration' => null,
    ];

    public static function toCanonical(string $providerField): ?string
    {
        if (! array_key_exists($providerField, self::$map)) {
            return null;
        }

        return self::$map[$providerField];
    }

    /**
     * @param  array{provider_field: string, value: mixed, date: string, currency?: ?string}  $row
     * @return array{metric_key: string, metric_value: float, metric_date: string, currency: ?string}|null
     */
    public static function mapRow(array $row): ?array
    {
        // Prefer not mapping estimatedMinutesWatched → views when 'views' exists separately
        if ($row['provider_field'] === 'estimatedMinutesWatched') {
            return null;
        }

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
