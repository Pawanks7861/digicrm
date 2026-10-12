<?php

namespace App\Integrations\Google;

use App\Integrations\Contracts\IntegrationProviderInterface;
use App\Integrations\Contracts\MetricsProviderInterface;
use App\Models\IntegrationConnection;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Http;

class GoogleAnalyticsProvider implements IntegrationProviderInterface, MetricsProviderInterface
{
    public function key(): string
    {
        return 'google_analytics';
    }

    public function label(): string
    {
        return 'Google Analytics';
    }

    public function capabilities(): array
    {
        return ['analytics', 'oauth', 'metrics'];
    }

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection
    {
        $conn = IntegrationConnection::updateOrCreate(
            ['provider' => $this->key(), 'client_id' => $clientId],
            ['name' => 'Google Analytics', 'status' => 'connected', 'connected_at' => now(), 'is_enabled' => true]
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

        $propertyId = $accountOrEntity['external_id']
            ?? $connection->meta['property_id']
            ?? null;

        if (! $propertyId) {
            return [];
        }

        $response = Http::withToken($connection->access_token)
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", [
                'dateRanges' => [[
                    'startDate' => $dateRange->getStartDate()->toDateString(),
                    'endDate' => $dateRange->getEndDate()->toDateString(),
                ]],
                'dimensions' => [['name' => 'date']],
                'metrics' => [
                    ['name' => 'sessions'],
                    ['name' => 'totalUsers'],
                    ['name' => 'screenPageViews'],
                    ['name' => 'conversions'],
                ],
            ]);

        if (! $response->successful()) {
            return [];
        }

        $metricHeaders = collect($response->json('metricHeaders') ?? [])->pluck('name')->all();
        $rows = [];
        foreach ($response->json('rows') ?? [] as $row) {
            $rawDate = $row['dimensionValues'][0]['value'] ?? null;
            $date = $rawDate ? substr($rawDate, 0, 4).'-'.substr($rawDate, 4, 2).'-'.substr($rawDate, 6, 2) : null;
            if (! $date) {
                continue;
            }
            foreach ($row['metricValues'] ?? [] as $i => $mv) {
                $field = $metricHeaders[$i] ?? null;
                if (! $field) {
                    continue;
                }
                $rows[] = [
                    'provider_field' => $field,
                    'value' => $mv['value'] ?? 0,
                    'date' => $date,
                    'external_entity_type' => 'ga4_property',
                    'external_entity_id' => (string) $propertyId,
                    'currency' => null,
                ];
            }
        }

        return $rows;
    }
}
