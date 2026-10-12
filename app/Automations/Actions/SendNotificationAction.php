<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Models\User;
use App\Services\NotificationDispatchService;
use Illuminate\Validation\ValidationException;

class SendNotificationAction implements AutomationActionHandler
{
    public function __construct(protected NotificationDispatchService $dispatch)
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

        $userIds = $config['user_ids'] ?? [];
        if (empty($userIds) && ! empty($config['user_id'])) {
            $userIds = [$config['user_id']];
        }
        if (empty($userIds) && $context->get('assigned_to')) {
            $userIds = [$context->get('assigned_to')];
        }
        $users = User::whereIn('id', $userIds)->get();
        $this->dispatch->send(
            $users,
            $config['event_key'] ?? 'automation.notification',
            $config['category'] ?? 'system',
            $config['title'],
            $config['body'] ?? '',
            [
                'priority' => $config['priority'] ?? 'normal',
                'route' => $config['route'] ?? null,
                'params' => $config['params'] ?? [],
                'occurrence' => 'auto-'.($context->entityId() ?? 'x').'-'.now()->timestamp,
            ]
        );

        return ['notified' => $users->pluck('id')->all()];
    }
}
