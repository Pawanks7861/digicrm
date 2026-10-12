<?php

namespace App\Integrations\Google;

use App\Integrations\Contracts\CalendarProviderInterface;
use App\Integrations\Contracts\IntegrationProviderInterface;
use App\Integrations\Contracts\WebhookCapableInterface;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleCalendarProvider implements IntegrationProviderInterface, CalendarProviderInterface, WebhookCapableInterface
{
    public function __construct(protected ?HttpFactory $http = null)
    {
    }

    public function key(): string
    {
        return 'google_calendar';
    }

    public function label(): string
    {
        return 'Google Calendar';
    }

    public function capabilities(): array
    {
        return ['calendar', 'oauth', 'webhooks'];
    }

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection
    {
        $connection = IntegrationConnection::updateOrCreate(
            ['provider' => $this->key(), 'client_id' => $clientId],
            [
                'name' => $credentials['name'] ?? 'Google Calendar',
                'status' => 'connected',
                'connected_at' => now(),
                'is_enabled' => true,
                'meta' => array_filter([
                    'email' => $credentials['email'] ?? null,
                    'calendar_id' => $credentials['calendar_id'] ?? 'primary',
                ]),
            ]
        );

        if (! empty($credentials['access_token'])) {
            $connection->access_token = $credentials['access_token'];
        }
        if (! empty($credentials['refresh_token'])) {
            $connection->refresh_token = $credentials['refresh_token'];
        }
        if (! empty($credentials['expires_at'])) {
            $connection->token_expires_at = $credentials['expires_at'];
        }
        $connection->save();

        return $connection->fresh();
    }

    public function disconnect(IntegrationConnection $connection): void
    {
        $connection->forceFill([
            'status' => 'not_connected',
            'access_token_encrypted' => null,
            'refresh_token_encrypted' => null,
            'credentials_encrypted' => null,
            'is_enabled' => false,
        ])->save();
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        if ($connection->status !== 'connected' || ! $connection->access_token) {
            return ['status' => 'not_connected', 'message' => 'Not connected'];
        }

        return ['status' => 'ok', 'message' => 'Token present'];
    }

    public function getAccountInfo(IntegrationConnection $connection): array
    {
        return [
            'email' => $connection->meta['email'] ?? null,
            'calendar_id' => $connection->meta['calendar_id'] ?? 'primary',
        ];
    }

    public function oauthRedirectUrl(string $state): string
    {
        $clientId = config('services.google.client_id', 'test-client-id');
        $redirect = urlencode(route('integrations.google.callback'));

        return "https://accounts.google.com/o/oauth2/v2/auth?client_id={$clientId}&redirect_uri={$redirect}&response_type=code&scope=https://www.googleapis.com/auth/calendar.events&state={$state}&access_type=offline&prompt=consent";
    }

    public function createEvent(IntegrationConnection $connection, array $event): array
    {
        $calendarId = $connection->meta['calendar_id'] ?? 'primary';
        $payload = [
            'summary' => $event['title'] ?? 'Follow-up',
            'description' => $event['description'] ?? '',
            'start' => ['dateTime' => $event['start'], 'timeZone' => $event['timezone'] ?? 'UTC'],
            'end' => ['dateTime' => $event['end'] ?? $event['start'], 'timeZone' => $event['timezone'] ?? 'UTC'],
        ];

        $response = $this->client()->withToken($connection->access_token)
            ->post("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Google Calendar create failed: '.$response->body());
        }

        return $response->json();
    }

    public function updateEvent(IntegrationConnection $connection, string $externalEventId, array $event): array
    {
        $calendarId = $connection->meta['calendar_id'] ?? 'primary';
        $payload = array_filter([
            'summary' => $event['title'] ?? null,
            'description' => $event['description'] ?? null,
            'start' => isset($event['start']) ? ['dateTime' => $event['start'], 'timeZone' => $event['timezone'] ?? 'UTC'] : null,
            'end' => isset($event['end']) ? ['dateTime' => $event['end'], 'timeZone' => $event['timezone'] ?? 'UTC'] : null,
        ]);

        $response = $this->client()->withToken($connection->access_token)
            ->patch("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$externalEventId}", $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Google Calendar update failed: '.$response->body());
        }

        return $response->json();
    }

    public function deleteEvent(IntegrationConnection $connection, string $externalEventId): void
    {
        $calendarId = $connection->meta['calendar_id'] ?? 'primary';
        $response = $this->client()->withToken($connection->access_token)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$externalEventId}");

        if ($response->failed() && $response->status() !== 404) {
            throw new \RuntimeException('Google Calendar delete failed: '.$response->body());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = config('services.google.webhook_secret');
        if (! $secret) {
            return $request->header('X-Goog-Channel-Token') === null
                || $request->header('X-Goog-Channel-Token') === 'digicrm';
        }

        return hash_equals((string) $secret, (string) $request->header('X-Goog-Channel-Token'));
    }

    public function parseExternalEventId(Request $request): ?string
    {
        return $request->header('X-Goog-Message-Number')
            ?: $request->input('id')
            ?: Str::uuid()->toString();
    }

    public function parseEventType(Request $request): ?string
    {
        return $request->header('X-Goog-Resource-State') ?: $request->input('type') ?: 'unknown';
    }

    protected function client()
    {
        return $this->http ?: Http::asJson();
    }
}
