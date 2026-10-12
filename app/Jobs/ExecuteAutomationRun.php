<?php

namespace App\Jobs;

use App\Models\AutomationRun;
use App\Services\AutomationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAutomationRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId)
    {
    }

    public function handle(AutomationEngine $engine): void
    {
        $run = AutomationRun::find($this->runId);
        if (! $run) {
            return;
        }
        $engine->executeRun($run);
    }
}
