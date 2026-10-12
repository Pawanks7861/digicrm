<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Jobs\SyncClientMetricsJob;
use App\Models\Client;
use Illuminate\Validation\ValidationException;

class RetryMetricSyncAction implements AutomationActionHandler
{
    public function validate(array $config): void
    {
    }

    public function handle(array $config, AutomationContext $context): array
    {
        if ($context->dryRun) {
            return ['dry_run' => true];
        }

        $clientId = $config['client_id']
            ?? $context->get('client_id')
            ?? (is_object($context->entity) && isset($context->entity->client_id) ? $context->entity->client_id : null);

        if (! $clientId || ! Client::find($clientId)) {
            throw ValidationException::withMessages(['client_id' => 'client_id required']);
        }

        SyncClientMetricsJob::dispatch(
            (int) $clientId,
            $config['provider'] ?? null,
            (int) ($config['days'] ?? 3)
        );

        return ['queued' => true, 'client_id' => $clientId];
    }
}
