<?php

namespace App\Events;

use App\Models\ClientPerformanceReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PerformanceReportPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public ClientPerformanceReport $report)
    {
    }
}
