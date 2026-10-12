<?php

namespace App\Events;

use App\Models\ContentRevision;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentRevisionUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(public ContentRevision $revision)
    {
    }
}
