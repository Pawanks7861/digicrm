<?php

namespace Tests\Feature\Phase11;

use App\Integrations\Google\FakeGoogleAdsMetricsProvider;
use App\Integrations\Meta\FakeMetaMetricsProvider;
use App\Models\Campaign;
use App\Models\CampaignKpi;
use App\Models\Client;
use App\Models\ClientPortalSetting;
use App\Models\IntegrationConnection;
use App\Models\MarketingMetricSnapshot;
use App\Models\User;
use App\Services\KpiService;
use App\Services\ManualMetricService;
use App\Services\MarketingPerformanceService;
use App\Services\MetricAggregationService;
use App\Services\MetricSyncService;
use App\Services\PerformanceReportService;
use Carbon\CarbonPeriod;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase11PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_sum_impressions_and_latest_followers(): void
    {
        $client = Client::factory()->create();
        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'source' => 'meta', 'provider' => 'meta',
            'metric_key' => 'impressions', 'metric_value' => 100, 'metric_date' => '2026-09-01', 'is_manual' => false,
        ]);
        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'source' => 'meta', 'provider' => 'meta',
            'metric_key' => 'impressions', 'metric_value' => 250, 'metric_date' => '2026-09-02', 'is_manual' => false,
        ]);
        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'source' => 'meta', 'provider' => 'meta',
            'metric_key' => 'followers', 'metric_value' => 900, 'metric_date' => '2026-09-01', 'is_manual' => false,
        ]);
        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'source' => 'meta', 'provider' => 'meta',
            'metric_key' => 'followers', 'metric_value' => 1000, 'metric_date' => '2026-09-03', 'is_manual' => false,
        ]);

        $agg = app(MetricAggregationService::class)->aggregate($client->id, '2026-09-01', '2026-09-03');

        $this->assertEquals(350, $agg['impressions']['value']);
        $this->assertEquals(1000, $agg['followers']['value']);
    }

    public function test_derived_cpl_cpa_roas_and_zero_leads_is_na(): void
    {
        $client = Client::factory()->create();
        foreach ([
            ['spend', 1000],
            ['clicks', 50],
            ['impressions', 1000],
            ['conversions', 10],
            ['attributed_revenue', 5000],
        ] as [$key, $val]) {
            MarketingMetricSnapshot::create([
                'client_id' => $client->id, 'source' => 'google_ads', 'provider' => 'google_ads',
                'metric_key' => $key, 'metric_value' => $val, 'metric_date' => '2026-09-01',
                'currency' => 'INR', 'is_manual' => false,
            ]);
        }

        $agg = app(MetricAggregationService::class)->aggregate($client->id, '2026-09-01', '2026-09-01');

        $this->assertSame(MetricAggregationService::NA, $agg['cpl']['display']);
        $this->assertEquals(100.0, $agg['cpa']['value']);
        $this->assertEquals(5.0, $agg['roas']['value']);
        $this->assertEquals(5.0, $agg['ctr']['value']);
    }

    public function test_kpi_progress_eighty_percent(): void
    {
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'campaign_id' => $campaign->id,
            'source' => 'manual', 'provider' => 'manual', 'metric_key' => 'leads',
            'metric_value' => 80, 'metric_date' => now()->toDateString(), 'is_manual' => true,
        ]);
        $kpi = CampaignKpi::create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'metric_code' => 'leads',
            'target_value' => 100,
            'target_type' => 'min',
            'period' => 'month',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ]);

        $progress = app(KpiService::class)->progress($kpi);

        $this->assertEquals(80.0, $progress['progress_pct']);
        $this->assertSame('below_target', $progress['status']);
    }

    public function test_double_sync_idempotent_with_fake_meta_and_google_ads(): void
    {
        $client = Client::factory()->create();
        $conn = IntegrationConnection::create([
            'provider' => 'meta',
            'client_id' => $client->id,
            'status' => 'connected',
            'is_enabled' => true,
            'name' => 'Meta',
            'connected_at' => now(),
        ]);
        $conn->access_token = 'fake-token';
        $conn->save();

        \App\Models\ExternalMarketingEntity::create([
            'client_id' => $client->id,
            'provider' => 'meta',
            'entity_type' => 'ad_campaign',
            'external_id' => 'camp_a',
            'external_name' => 'Campaign A',
            'status' => 'active',
        ]);

        $fake = new FakeMetaMetricsProvider([
            [
                'provider_field' => 'impressions',
                'value' => 500,
                'date' => now()->toDateString(),
                'external_entity_type' => 'ad_campaign',
                'external_entity_id' => 'camp_a',
            ],
            [
                'provider_field' => 'spend',
                'value' => 120.5,
                'date' => now()->toDateString(),
                'external_entity_type' => 'ad_campaign',
                'external_entity_id' => 'camp_a',
                'currency' => 'INR',
            ],
        ]);

        $sync = app(MetricSyncService::class);
        $period = CarbonPeriod::create(now()->subDay(), now());
        $sync->syncConnection($conn, $period, 'manual', null, $fake);
        $sync->syncConnection($conn, $period, 'manual', null, $fake);

        $this->assertEquals(2, MarketingMetricSnapshot::where('client_id', $client->id)->where('is_manual', false)->count());
        $this->assertEquals(500, (float) MarketingMetricSnapshot::where('metric_key', 'impressions')->value('metric_value'));

        $adsConn = IntegrationConnection::create([
            'provider' => 'google_ads',
            'client_id' => $client->id,
            'status' => 'connected',
            'is_enabled' => true,
            'name' => 'Ads',
            'connected_at' => now(),
        ]);
        $adsConn->access_token = 'ads-token';
        $adsConn->save();

        $adsFake = new FakeGoogleAdsMetricsProvider([
            [
                'provider_field' => 'metrics.cost_micros',
                'value' => 2_500_000,
                'date' => now()->toDateString(),
                'external_entity_id' => 'gads_1',
                'currency' => 'INR',
            ],
            [
                'provider_field' => 'metrics.impressions',
                'value' => 111,
                'date' => now()->toDateString(),
                'external_entity_id' => 'gads_1',
            ],
        ]);

        $sync->syncConnection($adsConn, $period, 'manual', null, $adsFake);
        $spend = MarketingMetricSnapshot::where('provider', 'google_ads')->where('metric_key', 'spend')->first();
        $this->assertNotNull($spend);
        $this->assertEquals(2.5, (float) $spend->metric_value);
    }

    public function test_manual_not_overwritten_by_api(): void
    {
        $client = Client::factory()->create();
        $conn = IntegrationConnection::create([
            'provider' => 'meta', 'client_id' => $client->id, 'status' => 'connected',
            'is_enabled' => true, 'name' => 'Meta', 'connected_at' => now(),
        ]);
        $conn->access_token = 't';
        $conn->save();

        MarketingMetricSnapshot::create([
            'client_id' => $client->id,
            'source' => 'meta',
            'provider' => 'meta',
            'metric_key' => 'impressions',
            'metric_value' => 999,
            'metric_date' => now()->toDateString(),
            'external_entity_id' => null,
            'is_manual' => true,
        ]);

        $admin = $this->admin();
        app(ManualMetricService::class)->upsert($client, [
            'metric_key' => 'leads',
            'metric_value' => 7,
            'metric_date' => now()->toDateString(),
        ], $admin);

        $fake = new FakeMetaMetricsProvider([
            [
                'provider_field' => 'impressions',
                'value' => 1,
                'date' => now()->toDateString(),
            ],
            [
                'provider_field' => 'actions_lead',
                'value' => 50,
                'date' => now()->toDateString(),
            ],
        ]);

        app(MetricSyncService::class)->syncConnection(
            $conn,
            CarbonPeriod::create(now()->subDay(), now()),
            'manual',
            null,
            $fake
        );

        $manualImpressions = MarketingMetricSnapshot::where('metric_key', 'impressions')->where('is_manual', true)->first();
        $this->assertEquals(999, (float) $manualImpressions->metric_value);

        $manualLeads = MarketingMetricSnapshot::where('metric_key', 'leads')->where('is_manual', true)->first();
        $this->assertEquals(7, (float) $manualLeads->metric_value);

        $apiLeads = MarketingMetricSnapshot::where('metric_key', 'leads')->where('is_manual', false)->first();
        $this->assertNotNull($apiLeads);
        $this->assertEquals(50, (float) $apiLeads->metric_value);
    }

    public function test_mapping_isolation_campaign_a_vs_b(): void
    {
        $client = Client::factory()->create();
        $a = Campaign::factory()->create(['client_id' => $client->id, 'name' => 'A']);
        $b = Campaign::factory()->create(['client_id' => $client->id, 'name' => 'B']);

        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'campaign_id' => $a->id, 'source' => 'meta', 'provider' => 'meta',
            'metric_key' => 'impressions', 'metric_value' => 100, 'metric_date' => '2026-09-01',
            'external_entity_id' => 'ext_a', 'is_manual' => false,
        ]);
        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'campaign_id' => $b->id, 'source' => 'meta', 'provider' => 'meta',
            'metric_key' => 'impressions', 'metric_value' => 9999, 'metric_date' => '2026-09-01',
            'external_entity_id' => 'ext_b', 'is_manual' => false,
        ]);

        $aggA = app(MetricAggregationService::class)->aggregate($client->id, '2026-09-01', '2026-09-01', $a->id);
        $this->assertEquals(100, $aggA['impressions']['value']);
    }

    public function test_report_snapshot_immutable_after_publish_and_partial_coverage(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        IntegrationConnection::create([
            'provider' => 'meta', 'client_id' => $client->id, 'status' => 'connected',
            'is_enabled' => true, 'name' => 'Meta', 'connected_at' => now(),
        ]);

        $coverage = app(MarketingPerformanceService::class)->coverage(
            $client->id,
            now()->subDays(7)->toDateString(),
            now()->toDateString()
        );
        $this->assertContains($coverage['status'], ['partial', 'no_data']);
        $this->assertNotEmpty($coverage['warning'] ?? $coverage['label']);

        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'source' => 'meta', 'provider' => 'meta',
            'metric_key' => 'impressions', 'metric_value' => 42, 'metric_date' => now()->toDateString(),
            'is_manual' => false,
        ]);

        $service = app(PerformanceReportService::class);
        $report = $service->generateDraft($client, now()->subDays(7)->toDateString(), now()->toDateString(), $admin);
        $frozen = (float) $report->metrics()->where('metric_code', 'impressions')->value('metric_value');
        $this->assertEquals(42, $frozen);

        $service->approve($report, $admin);
        $service->publish($report->fresh(), $admin);

        MarketingMetricSnapshot::where('metric_key', 'impressions')->update(['metric_value' => 999]);
        $report->refresh()->load('metrics');
        $this->assertEquals(42, (float) $report->metrics->firstWhere('metric_code', 'impressions')->metric_value);
        $this->assertTrue($report->isImmutable());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assertMutable($report);
    }

    public function test_portal_idor_blocked_for_other_client_reports_and_pdf(): void
    {
        $admin = $this->admin();
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        ClientPortalSetting::forClient($clientB)->update([
            'portal_enabled' => true,
            'show_reports' => true,
            'show_performance' => true,
        ]);

        $report = app(PerformanceReportService::class)->generateDraft(
            $clientA,
            now()->subMonth()->startOfMonth()->toDateString(),
            now()->subMonth()->endOfMonth()->toDateString(),
            $admin
        );
        app(PerformanceReportService::class)->approve($report, $admin);
        app(PerformanceReportService::class)->publish($report->fresh(), $admin);

        $portalUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $clientB->id,
        ]);
        $portalUser->assignRole('client');

        $this->actingAs($portalUser)
            ->get(route('portal.reports.show', $report))
            ->assertForbidden();

        $this->actingAs($portalUser)
            ->get(route('portal.reports.pdf', $report))
            ->assertForbidden();
    }

    public function test_monthly_automation_template_seeded_disabled(): void
    {
        $rule = \App\Models\AutomationRule::where('name', 'Monthly performance report draft')->first();
        $this->assertNotNull($rule);
        $this->assertSame('disabled', $rule->status);
        $this->assertTrue((bool) $rule->is_system);
        $this->assertEquals(
            'generate_performance_report',
            $rule->actions()->value('action_type')
        );
    }
}
