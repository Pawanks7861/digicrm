<?php

namespace App\Integrations\Contracts;

use App\Models\IntegrationConnection;

interface MessagingProviderInterface
{
    public function sendMessage(IntegrationConnection $connection, string $to, string $body, array $options = []): array;
}
