<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use Illuminate\Validation\ValidationException;

class SetDueDateAction implements AutomationActionHandler
{
    public function validate(array $config): void
    {
        if (empty($config['due_at']) && empty($config['due_date'])) {
            throw ValidationException::withMessages(['due_at' => 'due_at or due_date required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        $due = $config['due_at'] ?? $config['due_date'];
        if ($context->dryRun) {
            return ['dry_run' => true, 'due' => $due];
        }
        $entity = $context->entity;
        if (is_object($entity) && method_exists($entity, 'update')) {
            if (isset($entity->due_at)) {
                $entity->update(['due_at' => $due]);
            } elseif (isset($entity->due_date)) {
                $entity->update(['due_date' => $due]);
            }
        }

        return ['due' => $due];
    }
}
