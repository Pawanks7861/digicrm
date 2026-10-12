<?php

namespace Tests\Feature\Phase12;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Services\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    public function test_opportunity_created_from_agency_lead_over_http(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id, 'company' => 'Acme', 'interested_service' => 'SEO', 'estimated_budget' => 45000], $am);
        app(\App\Services\LeadService::class)->transition($lead, 'contacted', $am);

        $this->actingAs($am)->post(route('leads.opportunities.store', $lead), ['currency' => 'INR'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $opp = Opportunity::firstOrFail();
        $this->assertSame($lead->id, (int) $opp->lead_id);
        $this->assertSame($am->id, (int) $opp->owner_id);
        $this->assertSame('open', $opp->status);
        $this->assertSame('45000.00', (string) $opp->estimated_value);
        $this->assertSame('SEO — Acme', $opp->title);
        $this->assertSame('opportunity', $lead->fresh()->status);
        $this->assertNull($opp->client_id);
        $this->assertSame(0, Client::count());
    }

    public function test_opportunity_cannot_be_created_for_campaign_or_lost_lead(): void
    {
        $admin = $this->makeUser('admin');
        $campaignLead = $this->campaignLead(Client::factory()->create(), [], $admin);

        $this->actingAs($admin)->post(route('leads.opportunities.store', $campaignLead), [])->assertForbidden();

        try {
            app(OpportunityService::class)->create($campaignLead, [], $admin);
            $this->fail('Campaign lead opportunity must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lead', $e->errors());
        }

        $lost = $this->agencyLead([], $admin);
        app(\App\Services\LeadService::class)->transition($lost, 'lost', $admin, ['lost_reason_id' => $this->lostReasonId()]);
        $this->actingAs($admin)->post(route('leads.opportunities.store', $lost), [])->assertSessionHasErrors('lead');

        $this->assertSame(0, Opportunity::count());
    }

    public function test_quotation_links_to_opportunity_and_reuses_one_prospect_client(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id, 'company' => 'Prospect Co', 'email' => 'buyer@prospect.test'], $am);
        $opp = app(OpportunityService::class)->create($lead, ['estimated_value' => 30000], $am);

        $items = [['name' => 'Social media retainer', 'quantity' => 1, 'unit_rate' => 25000]];
        $this->actingAs($am)->post(route('opportunities.quotation', $opp), ['items' => $items])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($am)->post(route('opportunities.quotation', $opp), ['items' => $items])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Quotation::where('opportunity_id', $opp->id)->count());
        $this->assertSame(2, Quotation::where('lead_id', $lead->id)->count());
        $this->assertSame(1, Client::count());

        $client = Client::firstOrFail();
        $lead->refresh();
        $this->assertSame('prospect', $client->status);
        $this->assertSame('Prospect Co', $client->company_name);
        $this->assertSame($am->id, (int) $client->account_manager_id);
        $this->assertSame($client->id, (int) $lead->prospect_client_id);
        $this->assertNull($lead->converted_client_id);
        $this->assertSame($client->id, (int) $opp->fresh()->client_id);
        $this->assertSame(2, Quotation::where('client_id', $client->id)->count());
    }

    public function test_lost_opportunity_cannot_be_quoted_and_quote_needs_permission(): void
    {
        $admin = $this->makeUser('admin');
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $admin);
        $opp = app(OpportunityService::class)->create($lead, [], $admin);
        $items = [['name' => 'Ads', 'quantity' => 1, 'unit_rate' => 1000]];

        $this->actingAs($admin)->post(route('opportunities.quotation', $opp), ['items' => $items])->assertForbidden();

        app(OpportunityService::class)->markLost($opp, $this->lostReasonId(), null, $admin);
        $this->actingAs($am)->post(route('opportunities.quotation', $opp), ['items' => $items])->assertSessionHasErrors('opportunity');
        $this->assertSame(0, Quotation::count());
    }

    public function test_follow_ups_link_to_the_lead_without_a_client(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $am);
        $due = now()->addDay()->startOfHour();

        $this->actingAs($am)->post(route('follow-ups.store'), [
            'lead_id' => $lead->id,
            'assigned_to' => $am->id,
            'title' => 'Send proposal deck',
            'follow_up_type' => 'call',
            'due_at' => $due->toDateTimeString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $followUp = FollowUp::firstOrFail();
        $this->assertSame(Lead::class, $followUp->followupable_type);
        $this->assertSame($lead->id, (int) $followUp->followupable_id);
        $this->assertNull($followUp->client_id);
        $this->assertSame($due->toDateTimeString(), $lead->fresh()->next_follow_up_at->toDateTimeString());
        $this->assertSame(1, $lead->followUps()->count());

        $other = $this->makeUser('account_manager');
        $this->actingAs($other)->post(route('follow-ups.store'), [
            'lead_id' => $lead->id, 'assigned_to' => $other->id, 'title' => 'Snoop',
            'follow_up_type' => 'call', 'due_at' => $due->toDateTimeString(),
        ])->assertForbidden();
        $this->assertSame(1, FollowUp::count());
    }

    public function test_logging_a_communication_marks_first_human_contact(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id], $am);

        $this->actingAs($am)->post(route('leads.communications.store', $lead), [
            'communication_type' => 'phone',
            'direction' => 'outbound',
            'notes' => 'Discussed requirements',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertNotNull($lead->first_contacted_at);
        $this->assertSame(1, $lead->communications()->count());
        $this->assertNull($lead->communications()->first()->client_id);
    }
}
