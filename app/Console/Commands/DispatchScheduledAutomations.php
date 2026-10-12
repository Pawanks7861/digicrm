<?php

namespace App\Console\Commands;

use App\Automations\AutomationContext;
use App\Automations\AutomationTriggerRegistry;
use App\Models\AutomationRule;
use App\Services\AutomationEngine;
use Illuminate\Console\Command;

class DispatchScheduledAutomations extends Command
{
    protected $signature = 'automations:dispatch-scheduled';

    protected $description = 'Dispatch automation rules with scheduled trigger';

    public function handle(AutomationEngine $engine): int
    {
        $rules = AutomationRule::query()
            ->where('status', 'active')
            ->where('trigger_type', AutomationTriggerRegistry::SCHEDULED)
            ->get();

        foreach ($rules as $rule) {
            $engine->dispatchForTrigger(AutomationTriggerRegistry::SCHEDULED, new AutomationContext(
                entity: $rule,
                triggerType: AutomationTriggerRegistry::SCHEDULED,
                data: ['occurrence' => now()->format('Y-m-d-H-i')],
            ));
        }

        $this->info('Dispatched '.$rules->count().' scheduled rules.');

        return self::SUCCESS;
    }
}
