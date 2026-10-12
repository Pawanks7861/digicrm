<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TaskService;
use App\Services\TimeTrackingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RunningTimerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function team(): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $u->assignRole('team_member');

        return $u;
    }

    public function test_start_stop_and_single_timer_enforced(): void
    {
        $user = $this->team();
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Reel',
            'assignee_id' => $user->id,
            'estimated_minutes' => 120,
        ], $admin);

        $svc = app(TimeTrackingService::class);
        $t1 = $svc->startTimer($user, ['task_id' => $task->id]);
        $this->assertTrue($t1->isRunning());

        $this->expectException(ValidationException::class);
        $svc->startTimer($user, ['task_id' => $task->id], false);
    }

    public function test_replace_timer_and_duration(): void
    {
        $user = $this->team();
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'A',
            'assignee_id' => $user->id,
        ], $admin);

        $svc = app(TimeTrackingService::class);
        $first = $svc->startTimer($user, ['task_id' => $task->id]);
        TimeEntry::whereKey($first->id)->update(['started_at' => now()->subMinutes(30)]);
        $second = $svc->startTimer($user, ['task_id' => $task->id], true);

        $this->assertNotNull($first->fresh()->ended_at);
        $this->assertGreaterThan(0, $first->fresh()->duration_seconds);
        $this->assertTrue($second->isRunning());
        $this->assertEquals(1, TimeEntry::where('user_id', $user->id)->running()->count());
    }

    public function test_cannot_stop_another_users_timer(): void
    {
        $user = $this->team();
        $other = $this->team();
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $client = Client::factory()->create();
        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'A',
            'assignee_id' => $user->id,
        ], $admin);

        $svc = app(TimeTrackingService::class);
        $timer = $svc->startTimer($user, ['task_id' => $task->id]);

        $this->expectException(ValidationException::class);
        $svc->stopTimer($timer, $other);
    }
}
