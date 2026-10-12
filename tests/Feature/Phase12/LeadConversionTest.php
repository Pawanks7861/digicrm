<?php

namespace Tests\Feature\Phase12;

use App\Models\Client;
use App\Models\ClientCommunication;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Quotation;
use App\Services\CommunicationService;
use App\Services\FollowUpService;
use App\Services\LeadConversionService;
use App\Services\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadConversionTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    public function test_agency_lead_converts_to_exactly_one_client_on_double_call(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id, 'name' => 'Karan Mehta', 'company' => 'Mehta Foods', 'email' => 'karan@mehta.test'], $am);

        $this->actingAs($am)->post(route('leads.convert', $lead))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($am)->post(route('leads.convert', $lead))->assertRedirect()->assertSessionHasNoErrors();

        $second = app(LeadConversionService::class)->convert($lead->fresh(), $am);

        $this->assertSame(1, Client::count());
        $client = Client::firstOrFail();
        $lead->refresh();
        $this->assertSame($client->id, $second->id);
        $this->assertSame($client->id, (int) $lead->converted_client_id);
        $this->assertNotNull($lead->converted_at);
        $this->assertSame('won', $lead->status);
        $this->assertSame('Mehta Foods', $client->company_name);
        $this->assertSame('Karan Mehta', $client->contact_person);
        $this->assertSame('onboarding', $client->status);
        $this->assertSame($am->id, (int) $client->account_manager_id);
        $this->assertSame(1, LeadStatusHistory::where('lead_id', $lead->id)->where('to_status', 'won')->count());
    }

    public function test_conversion_keeps_follow_ups_quotation_and_history(): void
    {
        $am = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am->id, 'company' => 'Keep Co'], $am);

        $followUp = app(FollowUpService::class)->create([
            'followupable_type' => Lead::class, 'followupable_id' => $lead->id,
            'assigned_to' => $am->id, 'title' => 'Call back', 'follow_up_type' => 'call', 'due_at' => now()->addDay(),
        ], $am);
        app(CommunicationService::class)->logForLead($lead, [
            'communication_type' => 'phone', 'direction' => 'outbound', 'notes' => 'Intro call',
        ], $am);
        $opp = app(OpportunityService::class)->create($lead->fresh(), ['estimated_value' => 20000], $am);
        $quotation = app(OpportunityService::class)->createQuotation($opp, [
            'items' => [['name' => 'Retainer', 'quantity' => 1, 'unit_rate' => 20000]],
        ], $am);
        $prospectId = (int) $lead->fresh()->prospect_client_id;
        $historyBefore = LeadStatusHistory::where('lead_id', $lead->id)->count();

        $client = app(LeadConversionService::class)->convert($lead->fresh(), $am);

        $lead->refresh();
        $this->assertSame($prospectId, $client->id);
        $this->assertSame(1, Client::count());
        $this->assertSame('onboarding', $client->fresh()->status);

        $followUp->refresh();
        $this->assertSame(Lead::class, $followUp->followupable_type);
        $this->assertSame($lead->id, (int) $followUp->followupable_id);
        $this->assertSame($client->id, (int) $followUp->client_id);

        $comm = ClientCommunication::where('communicable_type', Lead::class)->where('communicable_id', $lead->id)->firstOrFail();
        $this->assertSame($client->id, (int) $comm->client_id);

        $quotation->refresh();
        $this->assertSame($lead->id, (int) $quotation->lead_id);
        $this->assertSame($opp->id, (int) $quotation->opportunity_id);
        $this->assertSame($client->id, (int) $quotation->client_id);
        $this->assertSame(1, Quotation::count());

        $this->assertSame($client->id, (int) $opp->fresh()->client_id);
        $this->assertSame($historyBefore + 1, LeadStatusHistory::where('lead_id', $lead->id)->count());
        $this->assertSame(['new', 'assigned'], LeadStatusHistory::where('lead_id', $lead->id)->orderBy('id')->limit(2)->pluck('to_status')->all());
    }

    public function test_campaign_lead_can_never_be_converted_to_an_agency_client(): void
    {
        $super = $this->makeUser('super_admin');
        $am = $this->makeUser('account_manager');
        $client = Client::factory()->forManager($am)->create();
        $lead = $this->campaignLead($client, ['company' => 'End Customer'], $am);
        $clientsBefore = Client::count();

        $this->actingAs($super)->post(route('leads.convert', $lead))->assertSessionHasErrors('lead');
        $this->actingAs($am)->post(route('leads.convert', $lead))->assertSessionHasErrors('lead');

        foreach (['convert', 'ensureProspectClient'] as $method) {
            try {
                app(LeadConversionService::class)->{$method}($lead, $super);
                $this->fail("{$method} must reject campaign leads");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lead', $e->errors());
            }
        }

        $lead->refresh();
        $this->assertNull($lead->converted_client_id);
        $this->assertNull($lead->prospect_client_id);
        $this->assertSame($clientsBefore, Client::count());
    }

    public function test_conversion_permissions_and_lost_lead_guard(): void
    {
        $team = $this->makeUser('team_member');
        $admin = $this->makeUser('admin');
        $lead = $this->agencyLead(['assigned_to' => $team->id], $admin);

        $this->actingAs($team)->post(route('leads.convert', $lead))->assertForbidden();

        app(\App\Services\LeadService::class)->transition($lead, 'lost', $admin, ['lost_reason_id' => $this->lostReasonId()]);
        $this->actingAs($admin)->post(route('leads.convert', $lead))->assertSessionHasErrors('status');

        $this->assertSame(0, Client::count());
    }
}
