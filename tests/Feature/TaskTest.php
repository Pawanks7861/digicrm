<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function admin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_create_assign_checklist_comment_dependency(): void
    {
        $admin = $this->admin();
        $designer = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $designer->assignRole('team_member');
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);

        $svc = app(TaskService::class);
        $task = $svc->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Design Reel',
            'assignee_id' => $designer->id,
        ], $admin);

        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $designer->id]);

        $item = $svc->addChecklistItem($task, 'Export MP4', $admin);
        $svc->toggleChecklist($item, $admin, true);
        $this->assertTrue($item->fresh()->is_completed);

        $comment = $svc->addComment($task, 'Looks good internally', $admin);
        $this->assertEquals('internal', $comment->visibility);

        $dep = $svc->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'title' => 'Write Caption',
        ], $admin);
        $svc->addDependency($task, $dep->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->addDependency($task, $task->id);
    }

    public function test_team_cannot_view_unrelated_task(): void
    {
        $admin = $this->admin();
        $team = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $team->assignRole('team_member');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Secret',
        ], $admin);

        $this->actingAs($team)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_circular_dependency_blocked(): void
    {
        $admin = $this->admin();
        $client = Client::factory()->create();
        $svc = app(TaskService::class);
        $a = $svc->create(['client_id' => $client->id, 'title' => 'A'], $admin);
        $b = $svc->create(['client_id' => $client->id, 'title' => 'B'], $admin);
        $svc->addDependency($a, $b->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->addDependency($b, $a->id);
    }
}
