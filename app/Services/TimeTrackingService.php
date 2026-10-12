<?php

namespace App\Services;

use App\Events\TimerStarted;
use App\Events\TimerStopped;
use App\Events\TimeEntryCreated;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeTrackingService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function runningTimer(User $user): ?TimeEntry
    {
        return TimeEntry::query()
            ->with(['task:id,title', 'client:id,company_name'])
            ->where('user_id', $user->id)
            ->running()
            ->first();
    }

    public function startTimer(User $user, array $data, bool $stopExisting = false): TimeEntry
    {
        return DB::transaction(function () use ($user, $data, $stopExisting) {
            $existing = TimeEntry::query()
                ->where('user_id', $user->id)
                ->running()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! $stopExisting) {
                    throw ValidationException::withMessages([
                        'timer' => 'You already have a running timer. Stop it or confirm replace.',
                        'active_timer_id' => $existing->id,
                    ]);
                }
                $this->stopTimer($existing, $user);
            }

            $task = ! empty($data['task_id']) ? Task::findOrFail($data['task_id']) : null;
            $entry = TimeEntry::create([
                'user_id' => $user->id,
                'client_id' => $data['client_id'] ?? $task?->client_id,
                'task_id' => $task?->id,
                'campaign_id' => $data['campaign_id'] ?? $task?->campaign_id,
                'campaign_deliverable_id' => $data['campaign_deliverable_id'] ?? $task?->campaign_deliverable_id,
                'content_item_id' => $data['content_item_id'] ?? $task?->content_item_id,
                'client_request_id' => $data['client_request_id'] ?? $task?->client_request_id,
                'started_at' => now(),
                'ended_at' => null,
                'duration_seconds' => 0,
                'entry_type' => 'timer',
                'billable' => array_key_exists('billable', $data) ? (bool) $data['billable'] : ($task !== null),
                'category' => $data['category'] ?? ($task ? 'client_work' : 'other'),
                'description' => $data['description'] ?? null,
                'status' => 'logged',
                'created_by' => $user->id,
            ]);

            event(new TimerStarted($entry));
            $this->activityLogger->log('timer_started', 'time', $entry, null, [
                'task_id' => $entry->task_id,
            ]);

            return $entry->fresh(['task', 'client']);
        });
    }

    public function stopTimer(TimeEntry $entry, User $actor): TimeEntry
    {
        return DB::transaction(function () use ($entry, $actor) {
            $entry = TimeEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->ended_at) {
                return $entry;
            }

            if ((int) $entry->user_id !== (int) $actor->id && ! $actor->can('time_entries.edit_all') && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['timer' => 'Cannot stop another user\'s timer.']);
            }

            $ended = now();
            $seconds = max(0, (int) $entry->started_at->diffInSeconds($ended));
            $entry->update([
                'ended_at' => $ended,
                'duration_seconds' => $seconds,
            ]);

            event(new TimerStopped($entry->fresh()));
            $this->activityLogger->log('timer_stopped', 'time', $entry, null, [
                'duration_seconds' => $seconds,
            ]);

            return $entry->fresh(['task', 'client']);
        });
    }

    public function createManual(User $user, array $data): TimeEntry
    {
        $seconds = $this->resolveDurationSeconds($data);
        if ($seconds <= 0) {
            throw ValidationException::withMessages(['duration' => 'Duration must be greater than zero.']);
        }

        $task = ! empty($data['task_id']) ? Task::findOrFail($data['task_id']) : null;
        $started = isset($data['started_at'])
            ? \Carbon\Carbon::parse($data['started_at'])
            : \Carbon\Carbon::parse(($data['date'] ?? now()->toDateString()).' 09:00:00');
        $ended = $started->copy()->addSeconds($seconds);

        $entry = TimeEntry::create([
            'user_id' => $data['user_id'] ?? $user->id,
            'client_id' => $data['client_id'] ?? $task?->client_id,
            'task_id' => $task?->id,
            'campaign_id' => $data['campaign_id'] ?? $task?->campaign_id,
            'campaign_deliverable_id' => $data['campaign_deliverable_id'] ?? $task?->campaign_deliverable_id,
            'content_item_id' => $data['content_item_id'] ?? null,
            'client_request_id' => $data['client_request_id'] ?? null,
            'started_at' => $started,
            'ended_at' => $ended,
            'duration_seconds' => $seconds,
            'entry_type' => 'manual',
            'billable' => array_key_exists('billable', $data) ? (bool) $data['billable'] : ($task !== null),
            'category' => $data['category'] ?? ($task ? 'client_work' : 'other'),
            'description' => $data['description'] ?? null,
            'status' => 'logged',
            'created_by' => $user->id,
        ]);

        event(new TimeEntryCreated($entry));
        $this->activityLogger->log('time_entry_created', 'time', $entry, null, [
            'duration_seconds' => $seconds,
        ]);

        return $entry->fresh(['task', 'client', 'user']);
    }

    public function update(TimeEntry $entry, array $data, User $actor): TimeEntry
    {
        if ($entry->isRunning()) {
            throw ValidationException::withMessages(['entry' => 'Stop the timer before editing.']);
        }

        $old = $entry->only(['duration_seconds', 'billable', 'description', 'status']);
        $payload = collect($data)->only([
            'description', 'billable', 'category', 'status', 'client_id', 'task_id', 'campaign_id',
        ])->all();

        if (isset($data['duration_seconds']) || isset($data['duration_minutes']) || isset($data['hours'])) {
            $payload['duration_seconds'] = $this->resolveDurationSeconds($data);
            if ($payload['duration_seconds'] <= 0) {
                throw ValidationException::withMessages(['duration' => 'Duration must be greater than zero.']);
            }
            $payload['ended_at'] = $entry->started_at->copy()->addSeconds($payload['duration_seconds']);
        }

        $entry->update($payload);

        if (isset($payload['duration_seconds']) && (int) $payload['duration_seconds'] !== (int) $old['duration_seconds']) {
            $this->activityLogger->log('time_entry_edited', 'time', $entry, $old, $entry->only(['duration_seconds', 'billable', 'description', 'status']));
        }

        return $entry->fresh(['task', 'client', 'user']);
    }

    public function delete(TimeEntry $entry, User $actor): void
    {
        if ($entry->isRunning()) {
            $this->stopTimer($entry, $actor);
        }
        $entry->delete();
        $this->activityLogger->log('time_entry_deleted', 'time', $entry, null, ['id' => $entry->id]);
    }

    public function taskActualSeconds(Task $task): int
    {
        return (int) TimeEntry::query()
            ->where('task_id', $task->id)
            ->valid()
            ->whereNotNull('ended_at')
            ->sum('duration_seconds');
    }

    protected function resolveDurationSeconds(array $data): int
    {
        if (isset($data['duration_seconds'])) {
            return (int) $data['duration_seconds'];
        }
        if (isset($data['duration_minutes'])) {
            return (int) round(((float) $data['duration_minutes']) * 60);
        }
        if (isset($data['hours']) || isset($data['minutes'])) {
            return ((int) ($data['hours'] ?? 0)) * 3600 + ((int) ($data['minutes'] ?? 0)) * 60;
        }

        return 0;
    }
}
