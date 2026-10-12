<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TaskAllocation;
use App\Models\User;
use App\Services\TaskService;
use App\Services\WorkloadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_allocations_do_not_multiply_estimate(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $a->assignRole('team_member');
        $b = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $b->assignRole('team_member');
        $client = Client::factory()->create();

        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Shared',
            'assignee_id' => $a->id,
            'collaborator_ids' => [$b->id],
            'estimated_minutes' => 600,
            'due_date' => now()->toDateString(),
        ], $admin);

        app(WorkloadService::class)->setAllocations($task, [
            ['user_id' => $a->id, 'allocated_minutes' => 360],
            ['user_id' => $b->id, 'allocated_minutes' => 240],
        ], $admin);

        $this->assertEquals(600, TaskAllocation::where('task_id', $task->id)->sum('allocated_minutes'));

        $from = now()->startOfWeek();
        $to = now()->endOfWeek();
        $wa = app(WorkloadService::class)->userWorkload($a, $from, $to);
        $wb = app(WorkloadService::class)->userWorkload($b, $from, $to);

        $this->assertEquals(360, $wa['allocated_minutes']);
        $this->assertEquals(240, $wb['allocated_minutes']);
    }

    public function test_single_assignee_gets_default_full_estimate(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole('team_member');
        $client = Client::factory()->create();

        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Solo',
            'assignee_id' => $user->id,
            'estimated_minutes' => 180,
            'due_date' => now()->toDateString(),
        ], $admin);

        $this->assertDatabaseHas('task_allocations', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'allocated_minutes' => 180,
        ]);
    }
}
