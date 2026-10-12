<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Services\ActivityLogger;
use Illuminate\Validation\ValidationException;

class AddInternalNoteAction implements AutomationActionHandler
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function validate(array $config): void
    {
        if (empty($config['note'])) {
            throw ValidationException::withMessages(['note' => 'note required']);
        }
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true];
        }
        if (is_object($context->entity)) {
            $this->logger->log('automation_note', 'automations', $context->entity, null, [
                'note' => $config['note'],
            ], 'internal');
        }

        return ['noted' => true];
    }
}
