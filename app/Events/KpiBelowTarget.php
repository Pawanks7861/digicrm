<?php

namespace App\Events;

use App\Models\CampaignKpi;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KpiBelowTarget
{
    use Dispatchable, SerializesModels;

    public function __construct(public CampaignKpi $kpi, public array $progress)
    {
    }
}
