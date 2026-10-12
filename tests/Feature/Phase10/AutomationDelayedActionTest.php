<?php

namespace Tests\Feature\Phase10;

use App\Automations\AutomationTriggerRegistry;
use App\Models\AutomationAction;
use App\Models\AutomationActionRun;
use App\Models\AutomationCondition;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ContentItem;
use App\Models\ContentRevision;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\AutomationEngine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationDelayedActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        NotificationSetting::current();
    }

    public function test_delayed_action_skips_when_conditions_no_longer_match(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

        $item = ContentItem::create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Post',
            'content_type' => 'static_post',
            'platform' => 'instagram',
            'status' => 'sent_to_client',
            'created_by' => $admin->id,
        ]);
        $rev = ContentRevision::create([
            'content_item_id' => $item->id,
            'revision_number' => 1,
            'original_name' => 'a.jpg',
            'path' => 'x/a.jpg',
            'uploaded_by' => $admin->id,
        ]);
        $approval = ClientApproval::create([
            'client_id' => $client->id,
            'content_item_id' => $item->id,
            'content_revision_id' => $rev->id,
            'requested_by' => $admin->id,
            'requested_at' => now(),
            'status' => 'pending',
        ]);

        $rule = AutomationRule::create([
            'name' => 'Delayed notify',
            'trigger_type' => AutomationTriggerRegistry::APPROVAL_REQUESTED,
            'status' => 'active',
            'activated_at' => now(),
        ]);
        AutomationCondition::create([
            'automation_rule_id' => $rule->id,
            'field' => 'status',
            'operator' => 'equals',
            'value' => 'pending',
        ]);
        $action = AutomationAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => 'send_notification',
            'config' => [
                'title' => 'Still pending',
                'body' => 'Follow up',
                'user_ids' => [$admin->id],
            ],
            'delay_minutes' => 60,
            'continue_on_match' => true,
        ]);

        $run = AutomationRun::create([
            'automation_rule_id' => $rule->id,
            'trigger_type' => $rule->trigger_type,
            'entity_type' => ClientApproval::class,
            'entity_id' => $approval->id,
            'status' => 'completed',
            'idempotency_key' => 'delayed-test-'.uniqid(),
            'context' => ['data' => [], 'origin_rule_ids' => [$rule->id]],
        ]);

        $actionRun = AutomationActionRun::create([
            'automation_run_id' => $run->id,
            'automation_action_id' => $action->id,
            'action_type' => 'send_notification',
            'status' => 'scheduled',
            'idempotency_key' => 'delayed-action-'.uniqid(),
            'scheduled_for' => now()->addHour(),
        ]);

        $approval->update(['status' => 'approved']);

        app(AutomationEngine::class)->executeActionRun($actionRun->fresh());

        $this->assertEquals('skipped', $actionRun->fresh()->status);
        $this->assertEquals('conditions_no_longer_match', $actionRun->fresh()->error);
    }
}
