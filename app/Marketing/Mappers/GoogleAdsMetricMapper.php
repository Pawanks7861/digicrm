<?php

namespace App\Marketing\Mappers;

class GoogleAdsMetricMapper
{
    /** Micros → currency units divisor. */
    public const MICROS = 1_000_000;

    /** @var array<string, array{code: string, micros?: bool}> */
    protected static array $map = [
        'metrics.impressions' => ['code' => 'impressions'],
        'impressions' => ['code' => 'impressions'],
        'metrics.clicks' => ['code' => 'clicks'],
        'clicks' => ['code' => 'clicks'],
        'metrics.cost_micros' => ['code' => 'spend', 'micros' => true],
        'cost_micros' => ['code' => 'spend', 'micros' => true],
        'metrics.conversions' => ['code' => 'conversions'],
        'conversions' => ['code' => 'conversions'],
        'metrics.conversions_value' => ['code' => 'attributed_revenue'],
        'conversions_value' => ['code' => 'attributed_revenue'],
        'metrics.all_conversions' => ['code' => 'conversions'],
        'lead_form_submission' => ['code' => 'leads'],
    ];

    public static function toCanonical(string $providerField): ?string
    {
        return self::$map[$providerField]['code'] ?? null;
    }

    /**
     * Convert Google Ads micros to currency units when required.
     *
     * @param  array{provider_field: string, value: mixed, date: string, currency?: ?string}  $row
     * @return array{metric_key: string, metric_value: float, metric_date: string, currency: ?string}|null
     */
    public static function mapRow(array $row): ?array
    {
        $field = $row['provider_field'];
        if (! isset(self::$map[$field])) {
            return null;
        }

        $def = self::$map[$field];
        $value = (float) $row['value'];
        if (! empty($def['micros'])) {
            $value = $value / self::MICROS;
        }

        return [
            'metric_key' => $def['code'],
            'metric_value' => $value,
            'metric_date' => $row['date'],
            'currency' => $row['currency'] ?? null,
        ];
    }
}
