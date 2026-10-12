<?php

namespace App\Events;

use App\Models\CampaignDeliverable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliverableReadyForClientReview
{
    use Dispatchable, SerializesModels;

    public function __construct(public CampaignDeliverable $deliverable)
    {
    }
}
