<?php

namespace App\Jobs;

use App\Models\AutomationActionRun;
use App\Services\AutomationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteDelayedAutomationAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $actionRunId)
    {
    }

    public function handle(AutomationEngine $engine): void
    {
        $actionRun = AutomationActionRun::with('action')->find($this->actionRunId);
        if (! $actionRun) {
            return;
        }
        $engine->executeActionRun($actionRun);
    }
}
