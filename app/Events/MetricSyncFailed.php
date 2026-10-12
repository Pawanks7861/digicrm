<?php

namespace App\Events;

use App\Models\MetricSyncRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetricSyncFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public MetricSyncRun $run)
    {
    }
}
