<?php

namespace App\Services;

use App\Models\CampaignDeliverable;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\DelayRecord;
use App\Models\Task;
use App\Models\TaskAllocation;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryMetricsService
{
    public function onTimeDelivery(?int $clientId, Carbon $from, Carbon $to): array
    {
        $q = CampaignDeliverable::query()
            ->whereIn('status', ['completed'])
            ->whereNotNull('client_due_date')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        if ($clientId) {
            $q->where('client_id', $clientId);
        }

        $items = $q->get(['client_due_date', 'completed_at']);
        $withDue = $items->count();
        $onTime = $items->filter(fn ($d) => $d->completed_at->startOfDay()->lte($d->client_due_date->copy()->startOfDay()))->count();

        return [
            'on_time' => $onTime,
            'with_due_date' => $withDue,
            'late' => max(0, $withDue - $onTime),
            'rate' => $withDue > 0 ? round(($onTime / $withDue) * 100, 2) : null,
        ];
    }

    public function approvalResponseAverage(?int $clientId, Carbon $from, Carbon $to): array
    {
        $q = ClientApproval::query()
            ->whereNotNull('responded_at')
            ->whereIn('status', ['approved', 'changes_requested'])
            ->whereBetween('responded_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        if ($clientId) {
            $q->where('client_id', $clientId);
        }

        $rows = $q->get(['requested_at', 'responded_at', 'status']);
        if ($rows->isEmpty()) {
            return ['average_seconds' => null, 'count' => 0];
        }

        $total = $rows->sum(fn ($r) => $r->requested_at->diffInSeconds($r->responded_at));

        return [
            'average_seconds' => (int) round($total / $rows->count()),
            'count' => $rows->count(),
        ];
    }

    public function plannedVsActual(?int $clientId, Carbon $from, Carbon $to): array
    {
        $taskQ = Task::query()->whereNotNull('estimated_minutes');
        if ($clientId) {
            $taskQ->where('client_id', $clientId);
        }
        $tasks = $taskQ->where(function ($q) use ($from, $to) {
            $q->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
                ->orWhereBetween('created_at', [$from, $to]);
        })->get(['id', 'estimated_minutes', 'title', 'client_id']);

        $rows = [];
        foreach ($tasks as $task) {
            $actualSec = (int) TimeEntry::where('task_id', $task->id)->valid()->whereNotNull('ended_at')->sum('duration_seconds');
            $estSec = (int) $task->estimated_minutes * 60;
            $var = $actualSec - $estSec;
            $pct = $estSec > 0 ? round(($var / $estSec) * 100, 1) : null;
            $rows[] = [
                'task_id' => $task->id,
                'title' => $task->title,
                'estimated_seconds' => $estSec,
                'actual_seconds' => $actualSec,
                'variance_seconds' => $var,
                'variance_percent' => $pct,
            ];
        }

        return $rows;
    }

    public function delayBreakdown(Carbon $from, Carbon $to): array
    {
        return app(DelayService::class)->analytics($from, $to);
    }
}
