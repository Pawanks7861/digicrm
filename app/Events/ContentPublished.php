<?php

namespace App\Events;

use App\Models\ContentItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public ContentItem $item)
    {
    }
}
