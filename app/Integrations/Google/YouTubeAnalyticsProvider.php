<?php

namespace App\Integrations\Google;

use App\Integrations\Contracts\IntegrationProviderInterface;
use App\Integrations\Contracts\MetricsProviderInterface;
use App\Models\IntegrationConnection;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Http;

class YouTubeAnalyticsProvider implements IntegrationProviderInterface, MetricsProviderInterface
{
    public function key(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube Analytics';
    }

    public function capabilities(): array
    {
        return ['analytics', 'oauth', 'metrics'];
    }

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection
    {
        $conn = IntegrationConnection::updateOrCreate(
            ['provider' => $this->key(), 'client_id' => $clientId],
            ['name' => 'YouTube Analytics', 'status' => 'connected', 'connected_at' => now(), 'is_enabled' => true]
        );
        if (! empty($credentials['access_token'])) {
            $conn->access_token = $credentials['access_token'];
            $conn->save();
        }

        return $conn->fresh();
    }

    public function disconnect(IntegrationConnection $connection): void
    {
        $connection->forceFill(['status' => 'not_connected', 'is_enabled' => false])->save();
    }

    public function healthCheck(IntegrationConnection $connection): array
    {
        return $connection->status === 'connected'
            ? ['status' => 'ok', 'message' => 'Connected']
            : ['status' => 'not_connected', 'message' => 'Not Connected'];
    }

    public function getAccountInfo(IntegrationConnection $connection): array
    {
        return ['capabilities' => $this->capabilities()];
    }

    public function metricsCapabilityStatus(IntegrationConnection $connection): array
    {
        if ($connection->status !== 'connected' || ! $connection->is_enabled) {
            return ['status' => 'not_connected', 'message' => 'Not Connected'];
        }
        if (! $connection->access_token) {
            return ['status' => 'permission_required', 'message' => 'Permission Required'];
        }

        return ['status' => 'ok', 'message' => 'Ready'];
    }

    public function fetchDailyMetrics(
        IntegrationConnection $connection,
        ?array $accountOrEntity,
        CarbonPeriod $dateRange
    ): iterable {
        $status = $this->metricsCapabilityStatus($connection);
        if ($status['status'] !== 'ok') {
            return [];
        }

        $channelId = $accountOrEntity['external_id']
            ?? $connection->meta['channel_id']
            ?? null;

        if (! $channelId) {
            return [];
        }

        $response = Http::withToken($connection->access_token)->get('https://youtubeanalytics.googleapis.com/v2/reports', [
            'ids' => 'channel=='.$channelId,
            'startDate' => $dateRange->getStartDate()->toDateString(),
            'endDate' => $dateRange->getEndDate()->toDateString(),
            'metrics' => 'views,likes,comments,shares,subscribersGained',
            'dimensions' => 'day',
        ]);

        if (! $response->successful()) {
            return [];
        }

        $headers = collect($response->json('columnHeaders') ?? [])->pluck('name')->all();
        $rows = [];
        foreach ($response->json('rows') ?? [] as $row) {
            $date = null;
            foreach ($headers as $i => $name) {
                if ($name === 'day') {
                    $date = $row[$i] ?? null;
                    continue;
                }
                if ($date === null) {
                    continue;
                }
                $rows[] = [
                    'provider_field' => $name,
                    'value' => $row[$i] ?? 0,
                    'date' => $date,
                    'external_entity_type' => 'youtube_video',
                    'external_entity_id' => (string) $channelId,
                    'currency' => null,
                ];
            }
        }

        return $rows;
    }
}
