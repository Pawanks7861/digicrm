<?php

namespace App\Services;

use App\Events\FollowUpCompleted;
use App\Events\FollowUpCreated;
use App\Jobs\SyncGoogleCalendarEvent;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowUpService
{
    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function create(array $data, User $actor): FollowUp
    {
        $lead = ($data['followupable_type'] ?? null) === Lead::class && ! empty($data['followupable_id'])
            ? Lead::find($data['followupable_id'])
            : null;
        $clientId = $data['client_id'] ?? null;
        if (! $clientId && $lead) {
            $clientId = $lead->client_id ?: $lead->converted_client_id;
        }

        $followUp = FollowUp::create([
            'client_id' => $clientId,
            'followupable_type' => $data['followupable_type'] ?? null,
            'followupable_id' => $data['followupable_id'] ?? null,
            'assigned_to' => $data['assigned_to'],
            'created_by' => $actor->id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'follow_up_type' => $data['follow_up_type'] ?? 'general',
            'due_at' => $data['due_at'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'pending',
            'sync_to_google_calendar' => (bool) ($data['sync_to_google_calendar'] ?? false),
        ]);

        event(new FollowUpCreated($followUp));

        $this->activityLogger->log('follow_up_created', 'followups', $followUp, null, [
            'title' => $followUp->title,
            'due_at' => $followUp->due_at,
        ]);

        $this->queueCalendarSync($followUp, 'upsert');
        $this->syncLead($followUp);

        return $followUp->fresh(['assignee', 'client']);
    }

    public function complete(FollowUp $followUp, User $actor, ?string $outcome = null, ?array $next = null): array
    {
        return DB::transaction(function () use ($followUp, $actor, $outcome, $next) {
            if (! $followUp->isOpen()) {
                throw ValidationException::withMessages(['status' => 'Follow-up is already closed.']);
            }

            $followUp->update([
                'status' => 'completed',
                'completed_at' => now(),
                'outcome' => $outcome,
            ]);

            $created = null;
            if ($next) {
                $created = $this->create(array_merge($next, [
                    'client_id' => $followUp->client_id,
                    'assigned_to' => $next['assigned_to'] ?? $followUp->assigned_to,
                    'followupable_type' => $followUp->followupable_type,
                    'followupable_id' => $followUp->followupable_id,
                ]), $actor);
            }

            event(new FollowUpCompleted($followUp->fresh()));
            $this->activityLogger->log('follow_up_completed', 'followups', $followUp, null, [
                'outcome' => $outcome,
            ]);

            $this->queueCalendarSync($followUp->fresh(), 'delete');
            $this->syncLead($followUp);

            return ['follow_up' => $followUp->fresh(), 'next' => $created];
        });
    }

    public function snooze(FollowUp $followUp, \DateTimeInterface $until): FollowUp
    {
        $followUp->update([
            'status' => 'snoozed',
            'snoozed_until' => $until,
            'due_at' => $until,
        ]);
        $this->queueCalendarSync($followUp->fresh(), 'upsert');
        $this->syncLead($followUp);

        return $followUp->fresh();
    }

    public function reschedule(FollowUp $followUp, string $dueAt): FollowUp
    {
        $followUp->update([
            'due_at' => $dueAt,
            'status' => 'pending',
            'snoozed_until' => null,
        ]);
        $this->queueCalendarSync($followUp->fresh(), 'upsert');
        $this->syncLead($followUp);

        return $followUp->fresh();
    }

    public function cancel(FollowUp $followUp): FollowUp
    {
        $followUp->update(['status' => 'cancelled']);
        $this->queueCalendarSync($followUp->fresh(), 'delete');
        $this->syncLead($followUp);

        return $followUp->fresh();
    }

    protected function syncLead(FollowUp $followUp): void
    {
        if ($followUp->followupable_type !== Lead::class || ! $followUp->followupable_id) {
            return;
        }
        $lead = Lead::find($followUp->followupable_id);
        if ($lead) {
            app(LeadService::class)->recomputeContactDates($lead);
        }
    }

    protected function queueCalendarSync(FollowUp $followUp, string $operation): void
    {
        if (! $followUp->sync_to_google_calendar && ! $followUp->google_calendar_event_id) {
            return;
        }
        SyncGoogleCalendarEvent::dispatch($followUp->id, $operation);
    }
}
