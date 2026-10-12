<?php

namespace Tests\Feature\Phase10;

use App\Automations\AutomationContext;
use App\Automations\AutomationTriggerRegistry;
use App\Events\ClientRequestSubmitted;
use App\Models\AutomationAction;
use App\Models\AutomationCondition;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\FollowUp;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\AutomationEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationRuleExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    public function test_urgent_request_creates_one_follow_up_and_is_idempotent(): void
    {
        $am = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $am->assignRole('account_manager');
        $client = Client::factory()->create(['account_manager_id' => $am->id]);

        $rule = AutomationRule::create([
            'name' => 'Urgent FU',
            'trigger_type' => AutomationTriggerRegistry::CLIENT_REQUEST_SUBMITTED,
            'status' => 'active',
            'group_operator' => 'and',
            'activated_at' => now(),
        ]);
        AutomationCondition::create([
            'automation_rule_id' => $rule->id,
            'field' => 'priority',
            'operator' => 'equals',
            'value' => 'urgent',
        ]);
        AutomationAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => 'create_follow_up',
            'config' => [
                'title' => 'Urgent request follow-up',
                'assigned_to' => $am->id,
                'client_id' => $client->id,
                'due_at' => now()->addDay()->toDateTimeString(),
            ],
        ]);

        $req = ClientRequest::create([
            'client_id' => $client->id,
            'request_number' => 'REQ-TEST-1',
            'title' => 'Urgent reel',
            'request_type' => 'reel',
            'description' => 'asap',
            'priority' => 'urgent',
            'status' => 'submitted',
            'created_by' => $am->id,
            'submitted_by_user_id' => $am->id,
        ]);

        event(new ClientRequestSubmitted($req));
        event(new ClientRequestSubmitted($req)); // double dispatch

        $this->assertEquals(1, FollowUp::where('title', 'Urgent request follow-up')->count());
        $this->assertEquals(1, AutomationRun::where('automation_rule_id', $rule->id)->where('status', 'completed')->count());
    }
}
