<?php

namespace Tests\Feature\Phase12;

use App\Models\BillingSetting;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\MarketingMetricSnapshot;
use App\Services\InvoiceService;
use App\Services\LeadAnalyticsService;
use App\Services\LeadConversionService;
use App\Services\LeadService;
use App\Services\OpportunityService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class CampaignLeadReportTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    protected function spend(Client $client, Campaign $campaign, float $amount, ?string $date = null): void
    {
        MarketingMetricSnapshot::create([
            'client_id' => $client->id, 'campaign_id' => $campaign->id,
            'source' => 'meta', 'provider' => 'meta', 'metric_key' => 'spend',
            'metric_value' => $amount, 'metric_date' => $date ?? now()->toDateString(),
            'currency' => 'INR', 'is_manual' => false,
        ]);
    }

    public function test_crm_cpl_and_cost_per_qualified_are_correct(): void
    {
        $admin = $this->makeUser('admin');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $otherCampaign = Campaign::factory()->create(['client_id' => $client->id]);
        $this->spend($client, $campaign, 6000);
        $this->spend($client, $otherCampaign, 99999);

        $leads = collect(range(1, 4))->map(fn () => $this->campaignLead($client, ['campaign_id' => $campaign->id], $admin));
        app(LeadService::class)->transition($leads[0], 'qualified', $admin);
        $this->campaignLead($client, ['campaign_id' => $otherCampaign->id], $admin);
        $this->agencyLead(['campaign_id' => $campaign->id], $admin);

        $report = app(LeadAnalyticsService::class)->campaignLeadReport($campaign, now()->subDay()->toDateString(), now()->toDateString());

        $this->assertEquals(6000.0, $report['spend']['value']);
        $this->assertSame(4, $report['crm_leads']);
        $this->assertSame(1, $report['qualified']);
        $this->assertEquals(1500.0, $report['cpl']['value']);
        $this->assertSame('INR', $report['cpl']['currency']);
        $this->assertEquals(6000.0, $report['cost_per_qualified']['value']);
        $this->assertNull($report['converted']);
        $this->assertSame(LeadAnalyticsService::NA, $report['cost_per_conversion']['display']);
        $this->assertSame(0, MarketingMetricSnapshot::where('metric_key', 'leads')->count());
    }

    public function test_zero_leads_or_missing_spend_is_not_available(): void
    {
        $admin = $this->makeUser('admin');
        $client = Client::factory()->create();
        $withSpend = Campaign::factory()->create(['client_id' => $client->id]);
        $noSpend = Campaign::factory()->create(['client_id' => $client->id]);
        $this->spend($client, $withSpend, 5000);
        $this->campaignLead($client, ['campaign_id' => $noSpend->id], $admin);

        $service = app(LeadAnalyticsService::class);
        $from = now()->subDay()->toDateString();
        $to = now()->toDateString();

        $zeroLeads = $service->campaignLeadReport($withSpend, $from, $to);
        $this->assertSame(0, $zeroLeads['crm_leads']);
        $this->assertNull($zeroLeads['cpl']['value']);
        $this->assertSame(LeadAnalyticsService::NA, $zeroLeads['cpl']['display']);
        $this->assertSame(LeadAnalyticsService::NA, $zeroLeads['cost_per_qualified']['display']);

        $missingSpend = $service->campaignLeadReport($noSpend, $from, $to);
        $this->assertSame(1, $missingSpend['crm_leads']);
        $this->assertSame(LeadAnalyticsService::NA, $missingSpend['cpl']['display']);
    }

    public function test_client_summary_only_includes_that_clients_campaign_leads(): void
    {
        $admin = $this->makeUser('admin');
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $campaignA = Campaign::factory()->create(['client_id' => $clientA->id]);
        $campaignB = Campaign::factory()->create(['client_id' => $clientB->id]);
        $this->spend($clientA, $campaignA, 3000);

        $this->campaignLead($clientA, ['campaign_id' => $campaignA->id], $admin);
        $this->campaignLead($clientA, ['campaign_id' => $campaignA->id], $admin);
        $this->campaignLead($clientA, [], $admin);
        $this->campaignLead($clientB, ['campaign_id' => $campaignB->id], $admin);
        $this->agencyLead(['campaign_id' => $campaignA->id], $admin);

        $summary = app(LeadAnalyticsService::class)->clientCampaignLeadSummary($clientA, now()->subDay()->toDateString(), now()->toDateString(), true);

        $this->assertSame(3, $summary['summary']['total']);
        $this->assertSame(1, $summary['unattributed_to_campaign']);
        $this->assertCount(1, $summary['campaigns']);
        $this->assertSame($campaignA->id, $summary['campaigns'][0]['campaign_id']);
        $this->assertSame(2, $summary['campaigns'][0]['crm_leads']);
        $this->assertSame('1,500.00', $summary['campaigns'][0]['cpl']);
        $this->assertArrayNotHasKey('lead_to_conversion_rate', $summary['summary']);

        $hidden = app(LeadAnalyticsService::class)->clientCampaignLeadSummary($clientA, now()->subDay()->toDateString(), now()->toDateString(), false);
        $this->assertNull($hidden['campaigns'][0]['spend']);
        $this->assertNull($hidden['campaigns'][0]['cpl']);
    }

    public function test_campaign_report_route_is_limited_to_managed_clients(): void
    {
        $am = $this->makeUser('account_manager');
        $mine = Campaign::factory()->create(['client_id' => Client::factory()->forManager($am)->create()->id]);
        $notMine = Campaign::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->actingAs($am)->get(route('leads.reports.campaigns', ['campaign_id' => $mine->id]))->assertOk();
        $this->actingAs($am)->get(route('leads.reports.campaigns', ['campaign_id' => $notMine->id]))->assertForbidden();
        $this->actingAs($this->makeUser('team_member'))->get(route('leads.reports.campaigns'))->assertForbidden();
    }

    public function test_sales_revenue_measures_are_separate_and_not_double_counted(): void
    {
        BillingSetting::current()->update(['state' => 'Gujarat', 'legal_name' => 'Digi Agency Pvt Ltd']);
        $super = $this->makeUser('super_admin');
        $lead = $this->agencyLead(['assigned_to' => $super->id, 'company' => 'Revenue Co'], $super);
        $opp = app(OpportunityService::class)->create($lead, ['estimated_value' => 50000], $super);
        $quotation = app(OpportunityService::class)->createQuotation($opp, [
            'tax_treatment' => 'exempt',
            'items' => [['name' => 'Retainer', 'quantity' => 1, 'unit_rate' => 10000]],
        ], $super);
        app(QuotationService::class)->send($quotation, $super);
        app(QuotationService::class)->accept($quotation->fresh(), $super);
        app(OpportunityService::class)->markWon($opp, $super);
        app(LeadConversionService::class)->convert($lead->fresh(), $super);

        $invoice = app(InvoiceService::class)->convertFromQuotation($quotation->fresh(), $super);
        app(InvoiceService::class)->send($invoice, $super);
        app(PaymentService::class)->record($invoice->fresh(), [
            'amount' => 4000, 'payment_date' => now()->toDateString(), 'payment_method' => 'upi',
        ], $super);

        $revenue = app(LeadAnalyticsService::class)->salesRevenue();

        $this->assertSame([['currency' => 'INR', 'total' => '50000.00', 'count' => 1]], $revenue['won_opportunities']['rows']);
        $this->assertSame([], $revenue['estimated_pipeline']['rows']);
        $this->assertSame([['currency' => 'INR', 'total' => '10000.00', 'count' => 1]], $revenue['accepted_quotations']['rows']);
        $this->assertSame([['currency' => 'INR', 'total' => '10000.00', 'count' => 1]], $revenue['invoiced']['rows']);
        $this->assertSame([['currency' => 'INR', 'total' => '4000.00', 'count' => 1]], $revenue['collected']['rows']);
        $this->assertSame([['currency' => 'INR', 'total' => '6000.00', 'count' => 1]], $revenue['outstanding']['rows']);

        $this->actingAs($super)->get(route('leads.reports.sales-revenue'))->assertOk();
        $this->actingAs($this->makeUser('account_manager'))->get(route('leads.reports.sales-revenue'))->assertForbidden();
    }
}
