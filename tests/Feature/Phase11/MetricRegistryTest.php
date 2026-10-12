<?php

namespace Tests\Feature\Phase11;

use App\Marketing\MarketingMetricRegistry;
use App\Marketing\Mappers\GoogleAdsMetricMapper;
use App\Marketing\Mappers\MetaMetricMapper;
use Tests\TestCase;

class MetricRegistryTest extends TestCase
{
    public function test_registry_has_canonical_families_and_aggregation(): void
    {
        $impressions = MarketingMetricRegistry::get('impressions');
        $followers = MarketingMetricRegistry::get('followers');
        $ctr = MarketingMetricRegistry::get('ctr');

        $this->assertSame('sum', $impressions['aggregation']);
        $this->assertSame('latest_value', $followers['aggregation']);
        $this->assertSame('derived', $ctr['aggregation']);
        $this->assertStringContainsString('clicks', $ctr['formula_label']);
    }

    public function test_meta_and_ads_normalize_to_impressions(): void
    {
        $meta = MetaMetricMapper::mapRow([
            'provider_field' => 'impressions',
            'value' => 1000,
            'date' => '2026-09-01',
        ]);
        $ads = GoogleAdsMetricMapper::mapRow([
            'provider_field' => 'metrics.impressions',
            'value' => 2000,
            'date' => '2026-09-01',
        ]);

        $this->assertSame('impressions', $meta['metric_key']);
        $this->assertSame('impressions', $ads['metric_key']);
    }

    public function test_google_ads_micros_converted_to_currency_units(): void
    {
        $mapped = GoogleAdsMetricMapper::mapRow([
            'provider_field' => 'metrics.cost_micros',
            'value' => 5_000_000,
            'date' => '2026-09-01',
            'currency' => 'INR',
        ]);

        $this->assertSame('spend', $mapped['metric_key']);
        $this->assertEquals(5.0, $mapped['metric_value']);
    }
}
