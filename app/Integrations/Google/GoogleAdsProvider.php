<?php

namespace App\Integrations\Google;

use App\Integrations\Contracts\IntegrationProviderInterface;
use App\Integrations\Contracts\MetricsProviderInterface;
use App\Models\IntegrationConnection;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Http;

class GoogleAdsProvider implements IntegrationProviderInterface, MetricsProviderInterface
{
    public function key(): string
    {
        return 'google_ads';
    }

    public function label(): string
    {
        return 'Google Ads';
    }

    public function capabilities(): array
    {
        return ['ads', 'oauth', 'metrics'];
    }

    public function connect(array $credentials, ?int $clientId = null): IntegrationConnection
    {
        $conn = IntegrationConnection::updateOrCreate(
            ['provider' => $this->key(), 'client_id' => $clientId],
            ['name' => 'Google Ads', 'status' => 'connected', 'connected_at' => now(), 'is_enabled' => true]
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

        // Live GAQL call only when configured; otherwise empty (never fabricate).
        $customerId = $accountOrEntity['external_id']
            ?? $connection->meta['customer_id']
            ?? null;

        if (! $customerId || ! config('services.google_ads.developer_token')) {
            return [];
        }

        $from = $dateRange->getStartDate()->format('Y-m-d');
        $to = $dateRange->getEndDate()->format('Y-m-d');

        $response = Http::withToken($connection->access_token)
            ->withHeaders([
                'developer-token' => config('services.google_ads.developer_token'),
            ])
            ->post("https://googleads.googleapis.com/v16/customers/{$customerId}/googleAds:searchStream", [
                'query' => "SELECT segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM campaign WHERE segments.date BETWEEN '{$from}' AND '{$to}'",
            ]);

        if (! $response->successful()) {
            return [];
        }

        $rows = [];
        foreach ($response->json() ?? [] as $batch) {
            foreach ($batch['results'] ?? [] as $result) {
                $date = $result['segments']['date'] ?? $from;
                $metrics = $result['metrics'] ?? [];
                foreach (['impressions', 'clicks', 'cost_micros', 'conversions', 'conversions_value'] as $field) {
                    if (! array_key_exists($field, $metrics)) {
                        continue;
                    }
                    $rows[] = [
                        'provider_field' => 'metrics.'.$field,
                        'value' => $metrics[$field],
                        'date' => $date,
                        'external_entity_type' => 'ad_campaign',
                        'external_entity_id' => (string) ($result['campaign']['id'] ?? $customerId),
                        'external_entity_name' => $result['campaign']['name'] ?? null,
                        'currency' => $connection->meta['currency'] ?? null,
                    ];
                }
            }
        }

        return $rows;
    }
}
