<?php

namespace App\Events;

use App\Models\InternalReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewChangesRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public InternalReview $review)
    {
    }
}
