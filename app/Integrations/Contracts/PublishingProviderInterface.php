<?php

namespace App\Integrations\Contracts;

use App\Models\ContentPublication;
use App\Models\IntegrationConnection;

interface PublishingProviderInterface
{
    public function publish(IntegrationConnection $connection, ContentPublication $publication): array;
}
