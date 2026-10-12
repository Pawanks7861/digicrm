<?php

namespace App\Marketing\Mappers;

class Ga4MetricMapper
{
    /** @var array<string, string> */
    protected static array $map = [
        'sessions' => 'sessions',
        'totalUsers' => 'users',
        'activeUsers' => 'users',
        'screenPageViews' => 'pageviews',
        'conversions' => 'conversions',
        'eventCount' => 'conversions',
        'sessionsPerUser' => null, // ignore derived from GA
        'bounceRate' => null,
        'engagedSessions' => 'sessions',
        'keyEvents' => 'conversions',
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
