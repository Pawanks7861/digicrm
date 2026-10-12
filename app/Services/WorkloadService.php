<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkloadService
{
    public function __construct(
        protected CapacityService $capacity,
        protected ActivityLogger $activityLogger
    ) {
    }

    public function setAllocations(Task $task, array $allocations, User $actor): Collection
    {
        return DB::transaction(function () use ($task, $allocations, $actor) {
            $rows = [];
            foreach ($allocations as $row) {
                $mins = (int) ($row['allocated_minutes'] ?? 0);
                if ($mins < 0) {
                    throw ValidationException::withMessages(['allocated_minutes' => 'Allocation cannot be negative.']);
                }
                $alloc = TaskAllocation::updateOrCreate(
                    ['task_id' => $task->id, 'user_id' => (int) $row['user_id']],
                    [
                        'allocated_minutes' => $mins,
                        'allocation_percentage' => $row['allocation_percentage'] ?? null,
                    ]
                );
                $rows[] = $alloc;
            }

            $this->activityLogger->log('task_allocation_changed', 'tasks', $task, null, [
                'allocations' => collect($rows)->map->only(['user_id', 'allocated_minutes']),
            ]);

            return collect($rows);
        });
    }

    /** Ensure primary assignee gets full estimate when single assignee and no allocation yet. */
    public function ensureDefaultAllocation(Task $task): void
    {
        if (! $task->estimated_minutes) {
            return;
        }
        $assignees = $task->assignees()->pluck('user_id')->unique();
        if ($assignees->count() !== 1) {
            return;
        }
        $uid = (int) $assignees->first();
        if (TaskAllocation::where('task_id', $task->id)->exists()) {
            return;
        }
        TaskAllocation::create([
            'task_id' => $task->id,
            'user_id' => $uid,
            'allocated_minutes' => (int) $task->estimated_minutes,
            'allocation_percentage' => 100,
        ]);
    }

    public function teamWorkload(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $users = User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('id', $filters['user_id']))
            ->when(! empty($filters['role']), fn ($q) => $q->role($filters['role']))
            ->orderBy('name')
            ->get();

        return $users->map(fn (User $u) => $this->userWorkload($u, $from, $to));
    }

    public function userWorkload(User $user, Carbon $from, Carbon $to): array
    {
        $available = $this->capacity->availableMinutes($user, $from, $to);
        $allocated = (int) TaskAllocation::query()
            ->where('user_id', $user->id)
            ->whereHas('task', function ($q) use ($from, $to) {
                $q->whereNotIn('status', ['completed', 'cancelled'])
                    ->where(function ($qq) use ($from, $to) {
                        $end = $to->copy()->endOfDay()->toDateTimeString();
                        $qq->whereBetween('due_date', [$from->toDateString(), $end])
                            ->orWhere(function ($q2) use ($from, $end) {
                                $q2->whereNull('due_date')
                                    ->where(function ($q3) use ($from, $end) {
                                        $q3->whereBetween('start_date', [$from->toDateString(), $end])
                                            ->orWhereNull('start_date');
                                    });
                            });
                    });
            })
            ->sum('allocated_minutes');

        $activeTasks = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id))
            ->count();

        $overdueTasks = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereHas('assignees', fn ($a) => $a->where('user_id', $user->id))
            ->count();

        $plannedPct = $available > 0 ? round(($allocated / $available) * 100, 1) : ($allocated > 0 ? 100.0 : 0.0);

        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'roles' => $user->getRoleNames()],
            'available_minutes' => $available,
            'allocated_minutes' => $allocated,
            'remaining_minutes' => $available - $allocated,
            'planned_utilization' => $plannedPct,
            'status' => $this->statusFor($plannedPct),
            'active_tasks' => $activeTasks,
            'overdue_tasks' => $overdueTasks,
        ];
    }

    public function previewAssignment(User $user, int $additionalMinutes, Carbon $from, Carbon $to): array
    {
        $current = $this->userWorkload($user, $from, $to);
        $newAllocated = $current['allocated_minutes'] + $additionalMinutes;
        $available = $current['available_minutes'];
        $pct = $available > 0 ? round(($newAllocated / $available) * 100, 1) : 100.0;

        return [
            'current' => $current,
            'after_allocated_minutes' => $newAllocated,
            'after_utilization' => $pct,
            'after_status' => $this->statusFor($pct),
            'exceeds_capacity' => $newAllocated > $available,
            'warning' => $newAllocated > $available
                ? "This assignment will take {$user->name} to {$newAllocated}/{$available} minutes ({$pct}% capacity)."
                : null,
        ];
    }

    public function statusFor(float $pct): string
    {
        $t = config('agency.workload_thresholds');
        if ($pct > ($t['near_capacity'] ?? 100)) {
            return 'overloaded';
        }
        if ($pct >= ($t['normal'] ?? 85)) {
            return 'near_capacity';
        }
        if ($pct >= ($t['available'] ?? 60)) {
            return 'normal';
        }

        return 'available';
    }
}
