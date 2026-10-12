<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadAssignmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AssignUserAction implements AutomationActionHandler
{
    public function validate(array $config): void
    {
        if (empty($config['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'user_id required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        $entity = $context->entity;
        if ($context->dryRun) {
            return ['dry_run' => true, 'would_assign' => $config['user_id']];
        }

        if ($entity instanceof Lead) {
            $user = User::find($config['user_id']);
            if (! $user) {
                throw ValidationException::withMessages(['user_id' => 'User not found']);
            }
            app(LeadAssignmentService::class)->assign($entity, $user, null, 'Automation');

            return ['assigned_to' => $user->id];
        }

        if ($entity instanceof Model) {
            if ($this->hasAttribute($entity, 'assigned_to')) {
                $entity->update(['assigned_to' => $config['user_id']]);
            } elseif ($this->hasAttribute($entity, 'assignee_id')) {
                $entity->update(['assignee_id' => $config['user_id']]);
            }
        }

        return ['assigned_to' => $config['user_id']];
    }

    protected function hasAttribute(Model $entity, string $key): bool
    {
        return array_key_exists($key, $entity->getAttributes()) || $entity->isFillable($key);
    }
}
