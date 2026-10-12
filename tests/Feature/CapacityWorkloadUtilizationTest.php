<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\CapacityService;
use App\Services\TaskService;
use App\Services\TimeTrackingService;
use App\Services\UtilizationService;
use App\Services\WorkloadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapacityWorkloadUtilizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_capacity_workload_and_utilization_formulas(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $admin->assignRole('super_admin');
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user->assignRole('team_member');
        $client = Client::factory()->create();

        app(CapacityService::class)->setCapacity($user, [
            'working_hours_per_day' => 8,
            'working_days' => [1, 2, 3, 4, 5],
            'effective_from' => now()->startOfWeek()->toDateString(),
        ], $admin);

        $from = now()->startOfWeek();
        $to = now()->endOfWeek();
        $available = app(CapacityService::class)->availableMinutes($user, $from, $to);
        $this->assertEquals(5 * 8 * 60, $available); // 2400

        $task = app(TaskService::class)->create([
            'client_id' => $client->id,
            'title' => 'Big',
            'assignee_id' => $user->id,
            'estimated_minutes' => 1920, // 32h
            'due_date' => $from->copy()->addDays(2)->toDateString(),
        ], $admin);

        $wl = app(WorkloadService::class)->userWorkload($user, $from, $to);
        $this->assertEquals(1920, $wl['allocated_minutes']);
        $this->assertEquals(80.0, $wl['planned_utilization']);
        $this->assertEquals('normal', $wl['status']);

        app(WorkloadService::class)->setAllocations($task, [
            ['user_id' => $user->id, 'allocated_minutes' => 2640], // 44h
        ], $admin);

        $wl2 = app(WorkloadService::class)->userWorkload($user, $from, $to);
        $this->assertEquals(110.0, $wl2['planned_utilization']);
        $this->assertEquals('overloaded', $wl2['status']);

        $preview = app(WorkloadService::class)->previewAssignment($user, 0, $from, $to);
        $this->assertTrue($preview['exceeds_capacity']);

        // Logged 36h = 2160 minutes, billable 30h = 1800 minutes
        app(TimeTrackingService::class)->createManual($user, [
            'task_id' => $task->id,
            'hours' => 36,
            'minutes' => 0,
            'billable' => true,
            'date' => $from->toDateString(),
        ]);
        // Adjust: create separate non-billable 6h by editing approach — create 30h billable + 6h non-billable
        \App\Models\TimeEntry::where('user_id', $user->id)->delete();
        app(TimeTrackingService::class)->createManual($user, [
            'task_id' => $task->id,
            'hours' => 30,
            'minutes' => 0,
            'billable' => true,
            'date' => $from->toDateString(),
        ]);
        app(TimeTrackingService::class)->createManual($user, [
            'task_id' => $task->id,
            'hours' => 6,
            'minutes' => 0,
            'billable' => false,
            'date' => $from->copy()->addDay()->toDateString(),
        ]);

        $util = app(UtilizationService::class)->forUser($user, $from, $to);
        $this->assertEquals(90.0, $util['actual_utilization']);
        $this->assertEquals(75.0, $util['billable_utilization']);
        $this->assertEquals(110.0, $util['planned_utilization']);
    }
}
