<?php

namespace App\Integrations\Contracts;

use App\Models\IntegrationConnection;

interface CalendarProviderInterface
{
    public function createEvent(IntegrationConnection $connection, array $event): array;

    public function updateEvent(IntegrationConnection $connection, string $externalEventId, array $event): array;

    public function deleteEvent(IntegrationConnection $connection, string $externalEventId): void;
}
