<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use Illuminate\Validation\ValidationException;

class ChangeStatusAction implements AutomationActionHandler
{
    public function validate(array $config): void
    {
        if (empty($config['status'])) {
            throw ValidationException::withMessages(['status' => 'status required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true, 'status' => $config['status']];
        }
        $entity = $context->entity;
        if (is_object($entity) && method_exists($entity, 'update')) {
            $entity->update(['status' => $config['status']]);
        }

        return ['status' => $config['status']];
    }
}
