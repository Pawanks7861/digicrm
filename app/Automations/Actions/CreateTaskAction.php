<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Validation\ValidationException;

class CreateTaskAction implements AutomationActionHandler
{
    public function __construct(protected TaskService $tasks)
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

        $clientId = $config['client_id']
            ?? $context->get('client_id')
            ?? (is_object($context->entity) && isset($context->entity->client_id) ? $context->entity->client_id : null);

        if (! $clientId) {
            throw ValidationException::withMessages(['client_id' => 'client_id required']);
        }

        $actor = User::find($config['actor_id'] ?? null)
            ?? User::role(['super_admin', 'admin'])->first();

        $task = $this->tasks->create([
            'client_id' => $clientId,
            'title' => $config['title'],
            'description' => $config['description'] ?? null,
            'priority' => $config['priority'] ?? 'normal',
            'due_date' => $config['due_date'] ?? null,
            'assignee_id' => $config['assignee_id'] ?? null,
            'campaign_id' => $config['campaign_id'] ?? $context->get('campaign_id'),
            'client_request_id' => $config['client_request_id'] ?? ($context->entityType() === \App\Models\ClientRequest::class ? $context->entityId() : null),
        ], $actor);

        return ['task_id' => $task->id];
    }
}
