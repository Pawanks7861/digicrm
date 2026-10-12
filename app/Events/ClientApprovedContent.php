<?php

namespace App\Events;

use App\Models\ClientApproval;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientApprovedContent
{
    use Dispatchable, SerializesModels;

    public function __construct(public ClientApproval $approval)
    {
    }
}
