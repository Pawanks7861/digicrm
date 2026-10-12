<?php

namespace App\Events;

use App\Models\MetricSyncRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetricSyncCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public MetricSyncRun $run)
    {
    }
}
