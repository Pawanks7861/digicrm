<?php

namespace Tests\Feature\Phase12;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class ClientLeadIsolationTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    public function test_account_manager_sees_only_authorized_agency_and_campaign_leads(): void
    {
        $admin = $this->makeUser('admin');
        $am1 = $this->makeUser('account_manager');
        $am2 = $this->makeUser('account_manager');
        $client1 = Client::factory()->forManager($am1)->create();
        $client2 = Client::factory()->forManager($am2)->create();

        $myAgency = $this->agencyLead(['assigned_to' => $am1->id], $admin);
        $theirAgency = $this->agencyLead(['assigned_to' => $am2->id], $admin);
        $unassignedAgency = $this->agencyLead([], $admin);
        $myClientLead = $this->campaignLead($client1, [], $admin);
        $theirClientLead = $this->campaignLead($client2, [], $admin);

        $visible = Lead::visibleTo($am1)->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$myAgency->id, $myClientLead->id])->sort()->values()->all(), $visible);

        $response = $this->actingAs($am1)->get(route('leads.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Leads/Index')->has('leads.data', 2));

        $this->actingAs($am1)->get(route('leads.show', $myAgency))->assertOk();
        $this->actingAs($am1)->get(route('leads.show', $myClientLead))->assertOk();
        foreach ([$theirAgency, $unassignedAgency, $theirClientLead] as $lead) {
            $this->actingAs($am1)->get(route('leads.show', $lead))->assertForbidden();
            $this->actingAs($am1)->put(route('leads.update', $lead), ['name' => 'Hijack'])->assertForbidden();
            $this->actingAs($am1)->post(route('leads.transition', $lead), ['status' => 'contacted'])->assertForbidden();
            $this->actingAs($am1)->post(route('leads.notes.store', $lead), ['body' => 'peek'])->assertForbidden();
        }
        $this->assertSame(0, Lead::where('name', 'Hijack')->count());
        $this->assertSame(0, LeadNote::count());

        $this->actingAs($admin)->get(route('leads.index'))
            ->assertInertia(fn (Assert $page) => $page->has('leads.data', 5));
    }

    public function test_account_manager_cannot_create_campaign_leads_for_other_clients(): void
    {
        $am = $this->makeUser('account_manager');
        $other = Client::factory()->forManager($this->makeUser('account_manager'))->create();
        $foreignCampaign = Campaign::factory()->create(['client_id' => $other->id]);
        $own = Client::factory()->forManager($am)->create();

        $this->actingAs($am)->post(route('leads.store'), [
            'lead_category' => Lead::CATEGORY_CAMPAIGN, 'client_id' => $other->id, 'name' => 'X', 'email' => 'x@example.com',
        ])->assertSessionHasErrors('client_id');

        $this->actingAs($am)->post(route('leads.store'), [
            'lead_category' => Lead::CATEGORY_CAMPAIGN, 'client_id' => $own->id, 'campaign_id' => $foreignCampaign->id, 'name' => 'Y', 'email' => 'y@example.com',
        ])->assertSessionHasErrors('campaign_id');

        $this->assertSame(0, Lead::count());
    }

    public function test_team_member_cannot_bypass_lead_permissions(): void
    {
        $admin = $this->makeUser('admin');
        $team = $this->makeUser('team_member');
        $mine = $this->agencyLead(['assigned_to' => $team->id], $admin);
        $notMine = $this->agencyLead([], $admin);

        $this->actingAs($team)->get(route('leads.create'))->assertForbidden();
        $this->actingAs($team)->post(route('leads.store'), ['lead_category' => Lead::CATEGORY_AGENCY, 'name' => 'Sneak'])->assertForbidden();
        $this->actingAs($team)->get(route('leads.show', $notMine))->assertForbidden();
        $this->actingAs($team)->post(route('leads.assign', $mine), ['assigned_to' => $team->id])->assertForbidden();
        $this->actingAs($team)->post(route('leads.convert', $mine))->assertForbidden();
        $this->actingAs($team)->post(route('leads.merge', $mine), ['target_id' => $notMine->id])->assertForbidden();
        $this->actingAs($team)->get(route('leads.duplicates'))->assertForbidden();
        $this->actingAs($team)->get(route('leads.settings.sources'))->assertForbidden();
        $this->actingAs($team)->get(route('leads.settings.rules'))->assertForbidden();
        $this->actingAs($team)->get(route('leads.settings.forms'))->assertForbidden();
        $this->actingAs($team)->get(route('leads.reports.analytics'))->assertForbidden();
        $this->actingAs($team)->post(route('leads.opportunities.store', $mine))->assertForbidden();

        $this->actingAs($team)->get(route('leads.show', $mine))->assertOk();
        $this->actingAs($team)->post(route('leads.transition', $mine), ['status' => 'contacted'])->assertSessionHasNoErrors();
        $this->assertSame('contacted', $mine->fresh()->status);
        $this->assertSame(2, Lead::count());
    }

    public function test_portal_client_sees_only_own_campaign_leads(): void
    {
        $admin = $this->makeUser('admin');
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $userA = $this->portalUser($clientA);
        $this->portalUser($clientB);

        $a1 = $this->campaignLead($clientA, ['email' => 'a1@example.com'], $admin);
        $this->campaignLead($clientA, [], $admin);
        $b1 = $this->campaignLead($clientB, [], $admin);
        $agency = $this->agencyLead(['company' => 'Agency Prospect Secret'], $admin);

        $this->actingAs($userA)->get(route('portal.leads'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Leads/Index')
                ->has('leads.data', 2)
                ->missing('leads.data.0.email')
                ->missing('leads.data.0.source'));

        $this->actingAs($userA)->get(route('portal.leads.show', $a1))->assertOk();
        $this->actingAs($userA)->get(route('portal.leads.show', $b1))->assertNotFound();
        $this->actingAs($userA)->get(route('portal.leads.show', $agency))->assertNotFound();
        $this->actingAs($userA)->post(route('portal.leads.notes', $b1), ['body' => 'hi'])->assertNotFound();

        $this->actingAs($userA)->get(route('leads.index'))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($userA)->get(route('leads.show', $b1))->assertRedirect(route('portal.dashboard'));
        $this->actingAs($userA)->get(route('pipeline.index'))->assertRedirect(route('portal.dashboard'));
        $this->assertSame(0, Lead::visibleTo($userA)->count());
    }

    public function test_portal_flags_control_leads_contact_details_and_updates(): void
    {
        $admin = $this->makeUser('admin');
        $client = Client::factory()->create();
        $user = $this->portalUser($client, ['show_leads' => false]);
        $lead = $this->campaignLead($client, ['email' => 'visible@example.com'], $admin);

        $this->actingAs($user)->get(route('portal.leads'))->assertForbidden();
        $this->actingAs($user)->get(route('portal.leads.show', $lead))->assertForbidden();

        \App\Models\ClientPortalSetting::forClient($client)->update(['show_leads' => true, 'show_lead_contact_details' => true]);
        $this->actingAs($user)->get(route('portal.leads.show', $lead))
            ->assertInertia(fn (Assert $page) => $page->where('lead.email', 'visible@example.com'));

        $this->actingAs($user)->post(route('portal.leads.notes', $lead), ['body' => 'Called them'])->assertForbidden();
        \App\Models\ClientPortalSetting::forClient($client)->update(['allow_lead_updates' => true]);
        $this->actingAs($user)->post(route('portal.leads.notes', $lead), ['body' => 'Called them'])->assertRedirect();
        $this->assertSame('client', LeadNote::firstOrFail()->visibility);

        LeadNote::create(['lead_id' => $lead->id, 'user_id' => $admin->id, 'body' => 'Internal only', 'visibility' => 'internal']);
        $this->actingAs($user)->get(route('portal.leads.show', $lead))
            ->assertInertia(fn (Assert $page) => $page->has('notes', 1)->where('notes.0.body', 'Called them'));

        $this->actingAs($user)->get(route('portal.leads.summary'))->assertForbidden();
        \App\Models\ClientPortalSetting::forClient($client)->update(['show_lead_reports' => true]);
        $this->actingAs($user)->get(route('portal.leads.summary'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.total', 1)->where('flags.show_ad_spend', false));
    }
}
