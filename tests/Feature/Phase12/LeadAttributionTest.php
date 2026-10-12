<?php

namespace Tests\Feature\Phase12;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadTouchpoint;
use App\Services\LeadAnalyticsService;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadAttributionTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    public function test_first_touch_is_unchanged_after_later_touches_and_edits(): void
    {
        $admin = $this->makeUser('admin');
        $client = Client::factory()->create();
        $campaignA = Campaign::factory()->create(['client_id' => $client->id]);
        $campaignB = Campaign::factory()->create(['client_id' => $client->id]);

        $lead = $this->campaignLead($client, [
            'lead_source_id' => LeadSource::idFor('google_ads'),
            'campaign_id' => $campaignA->id,
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'diwali',
            'gclid' => 'G-123',
        ], $admin);
        $firstTouch = LeadTouchpoint::where('lead_id', $lead->id)->where('touch_type', 'first_touch')->firstOrFail()->toArray();

        $service = app(LeadService::class);
        $service->recordTouch($lead, [
            'lead_source_id' => LeadSource::idFor('facebook'),
            'utm_source' => 'facebook', 'utm_campaign' => 'retarget',
        ], 'return_visit', 'utm_match', $admin);
        $service->update($lead, [
            'lead_source_id' => LeadSource::idFor('referral'),
            'campaign_id' => $campaignB->id,
            'company' => 'Updated Co',
        ], $admin);

        $lead->refresh();
        $this->assertSame(LeadSource::idFor('google_ads'), (int) $lead->lead_source_id);
        $this->assertSame($campaignA->id, (int) $lead->campaign_id);
        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('diwali', $lead->utm_campaign);
        $this->assertSame('G-123', $lead->gclid);
        $this->assertSame('Updated Co', $lead->company);

        $touches = LeadTouchpoint::where('lead_id', $lead->id)->orderBy('id')->get();
        $this->assertSame(['first_touch', 'return_visit', 'manual'], $touches->pluck('touch_type')->all());
        $this->assertSame($firstTouch, $touches->first()->fresh()->toArray());
        $this->assertSame($campaignB->id, (int) $touches[2]->campaign_id);
        $this->assertSame('manual', $touches[2]->provenance);
    }

    public function test_missing_attribution_can_be_filled_once_but_not_overwritten(): void
    {
        $admin = $this->makeUser('admin');
        $lead = $this->agencyLead(['lead_source_id' => null], $admin);

        app(LeadService::class)->update($lead, ['lead_source_id' => LeadSource::idFor('referral'), 'source_detail' => 'Partner'], $admin);
        $this->assertSame(LeadSource::idFor('referral'), (int) $lead->fresh()->lead_source_id);

        app(LeadService::class)->update($lead->fresh(), ['lead_source_id' => LeadSource::idFor('event')], $admin);
        $this->assertSame(LeadSource::idFor('referral'), (int) $lead->fresh()->lead_source_id);
        $this->assertSame(1, LeadTouchpoint::where('lead_id', $lead->id)->where('touch_type', 'manual')->count());
    }

    public function test_merge_keeps_target_first_touch_and_records_source_touch(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->agencyLead(['email' => 'm@example.com', 'lead_source_id' => LeadSource::idFor('website_form'), 'utm_source' => 'newsletter'], $admin);
        $source = $this->agencyLead(['email' => 'm@example.com', 'lead_source_id' => LeadSource::idFor('instagram'), 'utm_source' => 'ig'], $admin);

        app(LeadService::class)->merge($source, $target, $admin);

        $target->refresh();
        $this->assertSame(LeadSource::idFor('website_form'), (int) $target->lead_source_id);
        $this->assertSame('newsletter', $target->utm_source);
        $this->assertSame(2, LeadTouchpoint::where('lead_id', $target->id)->where('touch_type', 'first_touch')->count());
        $mergeTouch = LeadTouchpoint::where('lead_id', $target->id)->where('touch_type', 'merge')->firstOrFail();
        $this->assertSame(LeadSource::idFor('instagram'), (int) $mergeTouch->lead_source_id);
        $this->assertSame('ig', $mergeTouch->utm_source);
    }

    public function test_source_analysis_counts_each_lead_once_by_first_touch(): void
    {
        $admin = $this->makeUser('admin');
        $a = $this->agencyLead(['lead_source_id' => LeadSource::idFor('website_form')], $admin);
        $this->agencyLead(['lead_source_id' => LeadSource::idFor('website_form')], $admin);
        $this->agencyLead(['lead_source_id' => LeadSource::idFor('referral')], $admin);
        $this->agencyLead(['lead_source_id' => null], $admin);

        foreach (['facebook', 'instagram', 'google_ads'] as $code) {
            app(LeadService::class)->recordTouch($a, ['lead_source_id' => LeadSource::idFor($code)], 'return_visit', 'utm_match', $admin);
        }

        $analysis = app(LeadAnalyticsService::class)->sourceAnalysis($admin);
        $rows = collect($analysis['rows'])->keyBy('source');

        $this->assertSame(4, $analysis['total']);
        $this->assertSame(4, collect($analysis['rows'])->sum('total'));
        $this->assertSame(2, $rows['Website Form']['total']);
        $this->assertSame(1, $rows['Referral']['total']);
        $this->assertSame(1, $rows['Unattributed']['total']);
        $this->assertFalse($rows->has('Facebook'));
    }

    public function test_lead_rates_use_na_for_zero_denominators(): void
    {
        $admin = $this->makeUser('admin');
        $service = app(LeadAnalyticsService::class);

        $empty = $service->summary($admin);
        $this->assertSame(0, $empty['total']);
        $this->assertSame(LeadAnalyticsService::NA, $empty['lead_to_qualified_rate']['display']);
        $this->assertSame(LeadAnalyticsService::NA, $empty['lead_to_conversion_rate']['display']);
        $this->assertSame(LeadAnalyticsService::NA, $empty['avg_first_response_display']);

        $client = Client::factory()->create();
        $lead = $this->campaignLead($client, [], $admin);
        app(LeadService::class)->transition($lead, 'qualified', $admin);
        $this->campaignLead($client, [], $admin);

        $summary = $service->summary($admin);
        $this->assertSame(2, $summary['total']);
        $this->assertEquals(50.0, $summary['lead_to_qualified_rate']['value']);
        $this->assertSame(LeadAnalyticsService::NA, $summary['lead_to_conversion_rate']['display']);
    }
}
