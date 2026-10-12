<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TimeTrackingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manual_entry_and_task_actual_aggregate(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole('team_member');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Design',
            'assignee_id' => $user->id,
            'estimated_minutes' => 60,
        ], $admin);

        $svc = app(TimeTrackingService::class);
        $svc->createManual($user, [
            'task_id' => $task->id,
            'hours' => 1,
            'minutes' => 30,
            'billable' => true,
            'description' => 'Edited 3 reels',
        ]);
        $svc->createManual($user, [
            'task_id' => $task->id,
            'duration_minutes' => 20,
            'billable' => false,
        ]);

        $this->assertEquals(6600, $svc->taskActualSeconds($task)); // 90m + 20m
        $this->actingAs($user)->get(route('timesheet.index'))->assertOk();
    }

    public function test_unauthorized_edit_blocked(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $owner = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $owner->assignRole('team_member');
        $other = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $other->assignRole('team_member');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'X',
            'assignee_id' => $owner->id,
        ], $admin);

        $entry = app(TimeTrackingService::class)->createManual($owner, [
            'task_id' => $task->id,
            'hours' => 1,
            'minutes' => 0,
        ]);

        $this->actingAs($other)->put(route('time-entries.update', $entry), [
            'hours' => 2,
        ])->assertForbidden();
    }

    public function test_client_cannot_access_timesheet(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'client_id' => $client->id,
        ]);
        $user->assignRole('client');

        $this->actingAs($user)->get(route('timesheet.index'))->assertRedirect();
    }
}
