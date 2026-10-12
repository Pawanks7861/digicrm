<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use Illuminate\Validation\ValidationException;

class UpdatePriorityAction implements AutomationActionHandler
{
    public function validate(array $config): void
    {
        if (empty($config['priority'])) {
            throw ValidationException::withMessages(['priority' => 'priority required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true, 'priority' => $config['priority']];
        }
        $entity = $context->entity;
        if (is_object($entity) && method_exists($entity, 'update')) {
            $entity->update(['priority' => $config['priority']]);
        }

        return ['priority' => $config['priority']];
    }
}
