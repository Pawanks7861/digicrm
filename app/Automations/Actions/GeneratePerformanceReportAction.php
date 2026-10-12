<?php

namespace App\Automations\Actions;

use App\Automations\AutomationActionHandler;
use App\Automations\AutomationContext;
use App\Models\Client;
use App\Models\User;
use App\Services\PerformanceReportService;
use Illuminate\Validation\ValidationException;

class GeneratePerformanceReportAction implements AutomationActionHandler
{
    public function __construct(protected PerformanceReportService $reports)
    {
    }

    public function validate(array $config): void
    {
        // client_id may come from context
    }

    public function handle(array $config, AutomationContext $context): array
    {
        $this->validate($config);
        if ($context->dryRun) {
            return ['dry_run' => true, 'action' => 'generate_performance_report'];
        }

        $clientId = $config['client_id']
            ?? $context->get('client_id')
            ?? (is_object($context->entity) && isset($context->entity->client_id) ? $context->entity->client_id : null);

        if (! $clientId) {
            throw ValidationException::withMessages(['client_id' => 'client_id required']);
        }

        $client = Client::findOrFail($clientId);
        $actor = User::find($config['actor_id'] ?? null)
            ?? User::role(['super_admin', 'admin'])->first();

        if (! $actor) {
            throw ValidationException::withMessages(['actor' => 'No actor available']);
        }

        $start = $config['period_start'] ?? now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $end = $config['period_end'] ?? now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $report = $this->reports->generateDraft(
            $client,
            $start,
            $end,
            $actor,
            $config['title'] ?? null
        );

        return [
            'report_id' => $report->id,
            'status' => $report->status, // always draft
        ];
    }
}
