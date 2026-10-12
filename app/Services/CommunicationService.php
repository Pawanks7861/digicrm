<?php

namespace App\Services;

use App\Events\CommunicationLogged;
use App\Models\Client;
use App\Models\ClientCommunication;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommunicationService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
        protected FollowUpService $followUps
    ) {
    }

    public function log(?Client $client, array $data, User $actor, ?Model $subject = null): array
    {
        return DB::transaction(function () use ($client, $data, $actor, $subject) {
            $comm = ClientCommunication::create([
                'client_id' => $client?->id,
                'communicable_type' => $subject?->getMorphClass(),
                'communicable_id' => $subject?->getKey(),
                'client_contact_id' => $client ? ($data['client_contact_id'] ?? null) : null,
                'user_id' => $actor->id,
                'communication_type' => $data['communication_type'],
                'direction' => $data['direction'] ?? 'outbound',
                'subject' => $data['subject'] ?? null,
                'notes' => $data['notes'],
                'occurred_at' => $data['occurred_at'] ?? now(),
                'follow_up_required' => (bool) ($data['follow_up_required'] ?? false),
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'visibility' => $data['visibility'] ?? 'internal',
                'created_by' => $actor->id,
            ]);

            $followUp = null;
            if ($comm->follow_up_required && $comm->next_follow_up_at) {
                $followUp = $this->followUps->create([
                    'client_id' => $client?->id,
                    'followupable_type' => $subject ? $subject->getMorphClass() : $comm->getMorphClass(),
                    'followupable_id' => $subject ? $subject->getKey() : $comm->id,
                    'assigned_to' => $data['follow_up_assigned_to'] ?? $actor->id,
                    'title' => $data['follow_up_title'] ?? 'Follow up: '.($comm->subject ?: $comm->communication_type),
                    'notes' => $data['follow_up_notes'] ?? null,
                    'follow_up_type' => $data['follow_up_type'] ?? $comm->communication_type,
                    'due_at' => $comm->next_follow_up_at,
                    'priority' => $data['priority'] ?? 'normal',
                ], $actor);
            }

            if ($subject instanceof Lead) {
                $leads = app(LeadService::class);
                if ($comm->direction !== 'internal') {
                    $leads->registerHumanContact($subject, $comm->occurred_at);
                }
                $leads->recomputeContactDates($subject->fresh());
            }

            event(new CommunicationLogged($comm));
            $this->activityLogger->log('communication_logged', 'communications', $subject ?? $client, null, [
                'type' => $comm->communication_type,
                'id' => $comm->id,
            ]);

            return ['communication' => $comm->fresh(['contact', 'user']), 'follow_up' => $followUp];
        });
    }

    public function logForLead(Lead $lead, array $data, User $actor): array
    {
        $client = $lead->client_id ? $lead->client : ($lead->converted_client_id ? $lead->convertedClient : null);

        return $this->log($client, $data, $actor, $lead);
    }
}
