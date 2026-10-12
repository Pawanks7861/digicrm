<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Models\Lead;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Validation\ValidationException;

class CreateFollowUpAction implements AutomationActionHandler
{
    public function __construct(protected FollowUpService $followUps)
    {
    }

    public function validate(array $config): void
    {
        if (empty($config['title'])) {
            throw ValidationException::withMessages(['title' => 'title required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true, 'title' => $config['title']];
        }

        $entity = $context->entity;
        $isLead = $entity instanceof Lead;
        $clientId = $isLead
            ? ($entity->client_id ?: $entity->converted_client_id)
            : ($config['client_id'] ?? $context->get('client_id'));
        $assignedTo = $config['assigned_to'] ?? ($isLead ? $entity->fresh()?->assigned_to : null) ?? $context->get('assigned_to');
        if (! $assignedTo) {
            $am = $context->get('client.account_manager_id') ?? $context->get('account_manager_id');
            $assignedTo = $am;
        }
        if (! $assignedTo || (! $clientId && ! $isLead)) {
            throw ValidationException::withMessages(['assigned_to' => 'client_id and assigned_to required']);
        }

        $actor = User::find($config['actor_id'] ?? null)
            ?? User::role(['super_admin', 'admin'])->first()
            ?? User::find($assignedTo);

        $fu = $this->followUps->create([
            'client_id' => $clientId,
            'assigned_to' => $assignedTo,
            'title' => $config['title'],
            'notes' => $config['notes'] ?? null,
            'due_at' => $config['due_at'] ?? now()->addDay()->toDateTimeString(),
            'priority' => $config['priority'] ?? 'normal',
            'follow_up_type' => $config['follow_up_type'] ?? 'general',
            'followupable_type' => $context->entityType(),
            'followupable_id' => $context->entityId(),
        ], $actor);

        return ['follow_up_id' => $fu->id];
    }
}
