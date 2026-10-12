<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;

class UtilizationService
{
    public function __construct(protected CapacityService $capacity)
    {
    }

    public function forUser(User $user, Carbon $from, Carbon $to): array
    {
        $available = $this->capacity->availableMinutes($user, $from, $to);

        $logged = (int) TimeEntry::query()
            ->where('user_id', $user->id)
            ->valid()
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('duration_seconds');

        $billable = (int) TimeEntry::query()
            ->where('user_id', $user->id)
            ->valid()
            ->where('billable', true)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('duration_seconds');

        $loggedMinutes = (int) floor($logged / 60);
        $billableMinutes = (int) floor($billable / 60);

        $workload = app(WorkloadService::class)->userWorkload($user, $from, $to);

        return [
            'user' => ['id' => $user->id, 'name' => $user->name],
            'available_minutes' => $available,
            'logged_minutes' => $loggedMinutes,
            'billable_minutes' => $billableMinutes,
            'non_billable_minutes' => max(0, $loggedMinutes - $billableMinutes),
            'allocated_minutes' => $workload['allocated_minutes'],
            'actual_utilization' => $available > 0 ? round(($loggedMinutes / $available) * 100, 1) : 0.0,
            'billable_utilization' => $available > 0 ? round(($billableMinutes / $available) * 100, 1) : 0.0,
            'planned_utilization' => $workload['planned_utilization'],
        ];
    }

    public function team(Carbon $from, Carbon $to, array $filters = []): array
    {
        $users = User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('id', $filters['user_id']))
            ->orderBy('name')
            ->get();

        return $users->map(fn (User $u) => $this->forUser($u, $from, $to))->all();
    }
}
