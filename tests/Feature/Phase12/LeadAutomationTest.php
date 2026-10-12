<?php

namespace Tests\Feature\Phase12;

use App\Automations\AutomationTriggerRegistry;
use App\Events\LeadCreated;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadAutomationTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
    }

    protected function rule(string $trigger, string $actionType, array $config): AutomationRule
    {
        $rule = AutomationRule::create([
            'name' => 'Test '.$trigger,
            'trigger_type' => $trigger,
            'status' => 'active',
            'activated_at' => now(),
            'max_actions_per_hour' => 100,
        ]);
        AutomationAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => $actionType,
            'config' => $config,
        ]);

        return $rule;
    }

    public function test_seeded_lead_automations_are_disabled_examples(): void
    {
        $seeded = AutomationRule::whereIn('trigger_type', [
            AutomationTriggerRegistry::LEAD_CREATED, AutomationTriggerRegistry::LEAD_UNCONTACTED,
        ])->get();

        $this->assertGreaterThanOrEqual(2, $seeded->count());
        $this->assertTrue($seeded->every(fn ($r) => $r->status !== 'active'));

        $lead = $this->agencyLead();
        $this->assertSame(0, AutomationRun::where('entity_id', $lead->id)->count());
    }

    public function test_assign_lead_automation_runs_once_per_lead(): void
    {
        $am = $this->makeUser('account_manager');
        $rule = $this->rule(AutomationTriggerRegistry::LEAD_CREATED, 'assign_lead', ['mode' => 'user', 'user_id' => $am->id]);

        $lead = $this->agencyLead();
        event(new LeadCreated($lead->fresh(), ['channel' => 'manual']));

        $lead->refresh();
        $this->assertSame($am->id, (int) $lead->assigned_to);
        $this->assertSame('assigned', $lead->status);
        $this->assertSame(1, AutomationRun::where('automation_rule_id', $rule->id)->where('entity_id', $lead->id)->count());
    }

    public function test_assign_lead_rules_mode_uses_assignment_rules(): void
    {
        $a = $this->makeUser('account_manager');
        $b = $this->makeUser('account_manager');
        LeadAssignmentRule::create([
            'name' => 'RR', 'priority' => 1, 'is_active' => true, 'strategy' => 'round_robin', 'user_pool' => [$a->id, $b->id],
        ]);
        $this->rule(AutomationTriggerRegistry::LEAD_CREATED, 'assign_lead', ['mode' => 'rules']);

        $first = $this->agencyLead();
        $second = $this->agencyLead();

        $this->assertSame($a->id, (int) $first->fresh()->assigned_to);
        $this->assertSame($b->id, (int) $second->fresh()->assigned_to);
    }

    public function test_uncontacted_follow_up_automation_is_idempotent(): void
    {
        $am = $this->makeUser('account_manager');
        $rule = $this->rule(AutomationTriggerRegistry::LEAD_UNCONTACTED, 'create_follow_up', [
            'title' => 'Contact uncontacted lead',
            'assigned_to' => $am->id,
            'due_at' => now()->addHours(6)->toDateTimeString(),
        ]);
        $lead = $this->agencyLead(['assigned_to' => $am->id], $am);

        $this->travel(5)->hours();
        Artisan::call('leads:check-uncontacted');
        Artisan::call('leads:check-uncontacted');

        $this->assertSame(1, AutomationRun::where('automation_rule_id', $rule->id)->count());
        $followUps = FollowUp::where('title', 'Contact uncontacted lead')->get();
        $this->assertCount(1, $followUps);
        $this->assertSame(Lead::class, $followUps[0]->followupable_type);
        $this->assertSame($lead->id, (int) $followUps[0]->followupable_id);
    }

    public function test_lead_triggers_are_registered_for_the_automation_builder(): void
    {
        $labels = AutomationTriggerRegistry::labels();

        foreach ([
            AutomationTriggerRegistry::LEAD_CREATED, AutomationTriggerRegistry::LEAD_ASSIGNED,
            AutomationTriggerRegistry::LEAD_STATUS_CHANGED, AutomationTriggerRegistry::LEAD_QUALIFIED,
            AutomationTriggerRegistry::LEAD_UNCONTACTED, AutomationTriggerRegistry::LEAD_CONVERTED,
            AutomationTriggerRegistry::LEAD_LOST,
        ] as $trigger) {
            $this->assertArrayHasKey($trigger, $labels);
        }
    }
}
