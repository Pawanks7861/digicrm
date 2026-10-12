<?php

namespace Tests\Feature\Phase10;

use App\Automations\AutomationContext;
use App\Automations\AutomationTriggerRegistry;
use App\Events\TaskAssigned;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\NotificationSetting;
use App\Models\Task;
use App\Models\User;
use App\Services\AutomationEngine;
use App\Services\TaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationRecursionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    public function test_recursion_depth_stops_at_max(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $assignee = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $assignee->assignRole('team_member');
        $client = Client::factory()->create(['account_manager_id' => $admin->id]);

        // Rule: on task.assigned → create another task (would recurse via TaskAssigned if assign fires)
        $rule = AutomationRule::create([
            'name' => 'Recursive tasks',
            'trigger_type' => AutomationTriggerRegistry::TASK_ASSIGNED,
            'status' => 'active',
            'activated_at' => now(),
            'max_actions_per_hour' => 100,
        ]);
        AutomationAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => 'create_task',
            'config' => [
                'title' => 'Spawned task',
                'client_id' => $client->id,
                'assignee_id' => $assignee->id,
                'actor_id' => $admin->id,
            ],
        ]);

        $engine = app(AutomationEngine::class);
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Root',
            'assignee_id' => $assignee->id,
        ], $admin);

        // Manually drive with increasing depth to verify guard
        $ctx = new AutomationContext($task, AutomationTriggerRegistry::TASK_ASSIGNED, [
            'client_id' => $client->id,
            'occurrence' => 'depth-test-'.uniqid(),
        ], depth: AutomationContext::MAX_DEPTH);
        $engine->dispatchForTrigger(AutomationTriggerRegistry::TASK_ASSIGNED, $ctx);

        // At max depth, no new runs should be created for this occurrence
        $this->assertEquals(0, \App\Models\AutomationRun::where('idempotency_key', 'like', '%depth-test%')->count());
    }
}
