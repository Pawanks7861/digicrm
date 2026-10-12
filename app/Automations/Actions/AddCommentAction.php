<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Models\ClientRequest;
use App\Models\User;
use App\Services\ClientRequestService;
use Illuminate\Validation\ValidationException;

class AddCommentAction implements AutomationActionHandler
{
    public function __construct(protected ClientRequestService $requests)
    {
    }

    public function validate(array $config): void
    {
        if (empty($config['comment'])) {
            throw ValidationException::withMessages(['comment' => 'comment required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true];
        }
        $entity = $context->entity;
        if ($entity instanceof ClientRequest) {
            $actor = User::find($config['actor_id'] ?? null) ?? User::role(['super_admin', 'admin'])->first();
            $this->requests->addComment($entity, $actor, $config['comment'], $config['visibility'] ?? 'internal');

            return ['commented' => true];
        }

        return ['skipped' => 'unsupported_entity'];
    }
}
