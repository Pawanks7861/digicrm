<?php

namespace Tests\Feature\Phase12;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\LeadStatusHistory;
use App\Services\LeadAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadAssignmentTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    public function test_round_robin_rotates_through_active_pool_and_skips_inactive(): void
    {
        $a = $this->makeUser('account_manager');
        $b = $this->makeUser('account_manager');
        $c = $this->makeUser('team_member');
        $inactive = $this->makeUser('account_manager', ['is_active' => false]);

        LeadAssignmentRule::create([
            'name' => 'Agency RR',
            'priority' => 10,
            'is_active' => true,
            'match_category' => Lead::CATEGORY_AGENCY,
            'strategy' => 'round_robin',
            'user_pool' => [$a->id, $inactive->id, $b->id, $c->id],
        ]);

        $assignees = [];
        foreach (range(1, 5) as $i) {
            $assignees[] = (int) $this->agencyLead(['name' => "Lead {$i}"], null, ['auto_assign' => true])->assigned_to;
        }

        $this->assertSame([$a->id, $b->id, $c->id, $a->id, $b->id], $assignees);
        $this->assertSame(0, Lead::where('assigned_to', $inactive->id)->count());
        $this->assertSame(5, Lead::where('status', 'assigned')->count());
    }

    public function test_rules_are_evaluated_by_priority_and_category(): void
    {
        $specific = $this->makeUser('account_manager');
        $manager = $this->makeUser('account_manager');
        $client = Client::factory()->forManager($manager)->create();

        LeadAssignmentRule::create([
            'name' => 'Campaign leads to client AM', 'priority' => 5, 'is_active' => true,
            'match_category' => Lead::CATEGORY_CAMPAIGN, 'strategy' => 'client_account_manager',
        ]);
        LeadAssignmentRule::create([
            'name' => 'Agency to specific', 'priority' => 10, 'is_active' => true,
            'match_category' => Lead::CATEGORY_AGENCY, 'strategy' => 'specific_user', 'user_id' => $specific->id,
        ]);

        $campaignLead = $this->campaignLead($client, [], null, ['auto_assign' => true]);
        $agencyLead = $this->agencyLead([], null, ['auto_assign' => true]);

        $this->assertSame($manager->id, (int) $campaignLead->assigned_to);
        $this->assertSame($specific->id, (int) $agencyLead->assigned_to);
    }

    public function test_unmatched_lead_is_marked_unassigned(): void
    {
        $lead = $this->agencyLead([], null, ['auto_assign' => true]);

        $this->assertNull($lead->assigned_to);
        $this->assertSame('unassigned', $lead->status);
        $this->assertTrue(LeadStatusHistory::where('lead_id', $lead->id)->where('to_status', 'unassigned')->exists());
    }

    public function test_manual_assignment_permissions_and_client_users_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $am = $this->makeUser('account_manager');
        $team = $this->makeUser('team_member');
        $portal = $this->makeUser('client', ['client_id' => Client::factory()->create()->id]);
        $lead = $this->agencyLead(['assigned_to' => $team->id], $admin);

        $this->actingAs($team)->post(route('leads.assign', $lead), ['assigned_to' => $team->id])->assertForbidden();

        $this->actingAs($admin)->post(route('leads.assign', $lead), ['assigned_to' => $am->id, 'reason' => 'Territory'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($am->id, (int) $lead->fresh()->assigned_to);

        $this->actingAs($admin)->post(route('leads.assign', $lead), ['assigned_to' => $portal->id])
            ->assertSessionHasErrors('assigned_to');
        $this->assertSame($am->id, (int) $lead->fresh()->assigned_to);

        $this->expectException(ValidationException::class);
        app(LeadAssignmentService::class)->assign($lead->fresh(), $portal, $admin);
    }

    public function test_account_manager_cannot_assign_leads_they_cannot_see(): void
    {
        $am1 = $this->makeUser('account_manager');
        $am2 = $this->makeUser('account_manager');
        $lead = $this->agencyLead(['assigned_to' => $am2->id], $am2);

        $this->actingAs($am1)->post(route('leads.assign', $lead), ['assigned_to' => $am1->id])->assertForbidden();
        $this->assertSame($am2->id, (int) $lead->fresh()->assigned_to);
    }
}
