<?php

namespace App\Services;

use App\Events\DelayResolved;
use App\Events\DelayStarted;
use App\Models\DelayReason;
use App\Models\DelayRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DelayService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function start(Model $delayable, string $reasonCode, User $actor, ?string $notes = null, string $source = 'manual'): DelayRecord
    {
        $reason = DelayReason::where('code', $reasonCode)->where('active', true)->first();
        if (! $reason) {
            throw ValidationException::withMessages(['delay_reason' => 'Invalid delay reason.']);
        }

        return DB::transaction(function () use ($delayable, $reason, $actor, $notes, $source) {
            $existing = DelayRecord::query()
                ->where('delayable_type', $delayable->getMorphClass())
                ->where('delayable_id', $delayable->id)
                ->where('delay_reason_id', $reason->id)
                ->active()
                ->first();

            if ($existing) {
                return $existing;
            }

            $record = DelayRecord::create([
                'delayable_type' => $delayable->getMorphClass(),
                'delayable_id' => $delayable->id,
                'delay_reason_id' => $reason->id,
                'started_at' => now(),
                'notes' => $notes,
                'source' => $source,
                'created_by' => $actor->id,
            ]);

            event(new DelayStarted($record));
            $this->activityLogger->log('delay_started', 'delays', $delayable, null, [
                'reason' => $reason->code,
                'record_id' => $record->id,
            ]);

            return $record->fresh(['reason']);
        });
    }

    public function resolve(DelayRecord $record, User $actor, ?string $notes = null): DelayRecord
    {
        return DB::transaction(function () use ($record, $actor, $notes) {
            if ($record->ended_at) {
                return $record;
            }

            $record->update([
                'ended_at' => now(),
                'resolved_by' => $actor->id,
                'notes' => $notes ? trim(($record->notes ? $record->notes."\n" : '').$notes) : $record->notes,
            ]);

            event(new DelayResolved($record->fresh()));
            $this->activityLogger->log('delay_resolved', 'delays', $record->delayable, null, [
                'record_id' => $record->id,
                'duration_seconds' => $record->fresh()->duration_seconds,
            ]);

            return $record->fresh(['reason']);
        });
    }

    public function resolveActiveFor(Model $delayable, User $actor, ?string $reasonCode = null): int
    {
        $query = DelayRecord::query()
            ->where('delayable_type', $delayable->getMorphClass())
            ->where('delayable_id', $delayable->id)
            ->active();

        if ($reasonCode) {
            $reason = DelayReason::where('code', $reasonCode)->first();
            if ($reason) {
                $query->where('delay_reason_id', $reason->id);
            }
        }

        $count = 0;
        foreach ($query->get() as $record) {
            $this->resolve($record, $actor);
            $count++;
        }

        return $count;
    }

    public function analytics(\Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $records = DelayRecord::query()
            ->with('reason')
            ->where('started_at', '<=', $to->copy()->endOfDay())
            ->where(function ($q) use ($from) {
                $q->whereNull('ended_at')->orWhere('ended_at', '>=', $from->copy()->startOfDay());
            })
            ->get();

        $byReason = [];
        $byCategory = [];
        $totalSeconds = 0;

        foreach ($records as $r) {
            $sec = $r->duration_seconds;
            $totalSeconds += $sec;
            $code = $r->reason?->code ?? 'unknown';
            $cat = $r->reason?->category ?? 'other';
            $byReason[$code] = ($byReason[$code] ?? 0) + $sec;
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $sec;
        }

        $pct = function (array $map) use ($totalSeconds) {
            if ($totalSeconds <= 0) {
                return [];
            }
            $out = [];
            foreach ($map as $k => $sec) {
                $out[] = [
                    'key' => $k,
                    'seconds' => $sec,
                    'percent' => round(($sec / $totalSeconds) * 100, 1),
                    'count' => null,
                ];
            }
            usort($out, fn ($a, $b) => $b['seconds'] <=> $a['seconds']);

            return $out;
        };

        $counts = $records->groupBy(fn ($r) => $r->reason?->code ?? 'unknown')->map->count();

        $reasonRows = $pct($byReason);
        foreach ($reasonRows as &$row) {
            $row['count'] = $counts[$row['key']] ?? 0;
        }

        return [
            'total_seconds' => $totalSeconds,
            'total_records' => $records->count(),
            'active_count' => $records->whereNull('ended_at')->count(),
            'by_reason' => $reasonRows,
            'by_category' => $pct($byCategory),
            'client_seconds' => $byCategory['client'] ?? 0,
            'internal_seconds' => ($byCategory['internal'] ?? 0) + ($byCategory['resource'] ?? 0) + ($byCategory['dependency'] ?? 0),
            'external_seconds' => ($byCategory['external'] ?? 0) + ($byCategory['client'] ?? 0),
        ];
    }

    public function activeBlockers(int $limit = 50)
    {
        return DelayRecord::query()
            ->with(['reason', 'delayable', 'creator:id,name'])
            ->active()
            ->latest('started_at')
            ->limit($limit)
            ->get();
    }
}
