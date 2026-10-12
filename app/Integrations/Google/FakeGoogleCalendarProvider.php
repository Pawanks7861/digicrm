<?php

namespace App\Integrations\Google;

use App\Integrations\Contracts\CalendarProviderInterface;
use App\Integrations\Contracts\IntegrationProviderInterface;
use App\Models\IntegrationConnection;

/**
 * Test double — records calendar mutations without HTTP.
 */
class FakeGoogleCalendarProvider implements IntegrationProviderInterface, CalendarProviderInterface
{
    /** @var list<array{op: string, payload: mixed}> */
    public array $calls = [];

    public function key(): string
    {
        return 'google_calendar';
    }

    public function label(): string
    {
        return 'Google Calendar (Fake)';
    }

    public function capabilities(): array
    {
        return ['calendar'];
    }

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection
    {
        return IntegrationConnection::updateOrCreate(
            ['provider' => $this->key(), 'client_id' => $clientId],
            ['name' => 'Fake Google Calendar', 'status' => 'connected', 'connected_at' => now(), 'is_enabled' => true, 'meta' => ['calendar_id' => 'primary']]
        );
    }

    public function disconnect(IntegrationConnection $connection): void
    {
        $connection->forceFill(['status' => 'not_connected', 'is_enabled' => false])->save();
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        return ['status' => 'ok', 'message' => 'fake'];
    }

    public function getAccountInfo(IntegrationConnection $connection): array
    {
        return ['fake' => true];
    }

    public function createEvent(IntegrationConnection $connection, array $event): array
    {
        $id = 'evt_'.count($this->calls);
        $this->calls[] = ['op' => 'create', 'payload' => $event];

        return ['id' => $id];
    }

    public function updateEvent(IntegrationConnection $connection, string $externalEventId, array $event): array
    {
        $this->calls[] = ['op' => 'update', 'id' => $externalEventId, 'payload' => $event];

        return ['id' => $externalEventId];
    }

    public function deleteEvent(IntegrationConnection $connection, string $externalEventId): void
    {
        $this->calls[] = ['op' => 'delete', 'id' => $externalEventId];
    }
}
