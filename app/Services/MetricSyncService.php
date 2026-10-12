<?php

namespace App\Services;

use App\Events\MetricSyncCompleted;
use App\Events\MetricSyncFailed;
use App\Integrations\Contracts\MetricsProviderInterface;
use App\Integrations\ProviderRegistry;
use App\Marketing\Mappers\Ga4MetricMapper;
use App\Marketing\Mappers\GoogleAdsMetricMapper;
use App\Marketing\Mappers\MetaMetricMapper;
use App\Marketing\Mappers\YouTubeMetricMapper;
use App\Models\Client;
use App\Models\ExternalMarketingEntity;
use App\Models\IntegrationConnection;
use App\Models\MarketingMetricSnapshot;
use App\Models\MetricSyncRun;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class MetricSyncService
{
    public function __construct(
        protected ProviderRegistry $registry
    ) {
    }

    public function syncClient(
        Client $client,
        ?string $provider = null,
        int $days = 3,
        string $syncType = 'incremental',
        ?User $actor = null,
        ?MetricsProviderInterface $overrideProvider = null
    ): array {
        $from = now()->subDays(max(1, $days - 1))->startOfDay();
        $to = now()->endOfDay();
        $period = CarbonPeriod::create($from, $to);

        $query = IntegrationConnection::query()
            ->where('client_id', $client->id)
            ->where('status', 'connected')
            ->where('is_enabled', true);

        if ($provider) {
            $query->where('provider', $provider);
        } else {
            $query->whereIn('provider', ['meta', 'google_ads', 'google_analytics', 'youtube']);
        }

        $runs = [];
        foreach ($query->get() as $connection) {
            $runs[] = $this->syncConnection($connection, $period, $syncType, $actor, $overrideProvider);
        }

        return $runs;
    }

    public function syncConnection(
        IntegrationConnection $connection,
        CarbonPeriod $period,
        string $syncType = 'incremental',
        ?User $actor = null,
        ?MetricsProviderInterface $metricsProvider = null
    ): MetricSyncRun {
        $run = MetricSyncRun::create([
            'client_id' => $connection->client_id,
            'integration_connection_id' => $connection->id,
            'provider' => $connection->provider,
            'sync_type' => $syncType,
            'date_from' => $period->getStartDate()->toDateString(),
            'date_to' => $period->getEndDate()->toDateString(),
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => $actor?->id,
        ]);

        try {
            if (! $metricsProvider) {
                $provider = $this->registry->get($connection->provider);
                if (! $provider instanceof MetricsProviderInterface) {
                    $run->update([
                        'status' => 'failed',
                        'errors' => [['message' => 'Provider does not support metrics']],
                        'completed_at' => now(),
                    ]);
                    event(new MetricSyncFailed($run));

                    return $run->fresh();
                }
                $metricsProvider = $provider;
            }

            $cap = $metricsProvider->metricsCapabilityStatus($connection);
            if ($cap['status'] !== 'ok') {
                $run->update([
                    'status' => 'failed',
                    'errors' => [['message' => $cap['message'] ?? 'Not Connected']],
                    'completed_at' => now(),
                ]);
                event(new MetricSyncFailed($run));

                return $run->fresh();
            }

            $entities = ExternalMarketingEntity::query()
                ->where('client_id', $connection->client_id)
                ->where('provider', $connection->provider)
                ->where('status', 'active')
                ->get();

            $accountOrEntities = $entities->isEmpty()
                ? [null]
                : $entities->map(fn (ExternalMarketingEntity $e) => [
                    'external_id' => $e->external_id,
                    'entity_type' => $e->entity_type,
                    'external_name' => $e->external_name,
                    'campaign_id' => $e->campaign_id,
                    'content_item_id' => $e->content_item_id,
                    'mapping_id' => $e->id,
                ])->all();

            $fetched = 0;
            $upserted = 0;
            $skipped = 0;

            foreach ($accountOrEntities as $entity) {
                $rows = $metricsProvider->fetchDailyMetrics($connection, $entity, $period);
                foreach ($rows as $row) {
                    $fetched++;
                    $mapped = $this->mapRow($connection->provider, $row);
                    if (! $mapped) {
                        $skipped++;
                        continue;
                    }

                    $result = $this->upsertSnapshot(
                        clientId: (int) $connection->client_id,
                        connection: $connection,
                        run: $run,
                        mapped: $mapped,
                        row: $row,
                        entity: $entity
                    );

                    if ($result === 'skipped') {
                        $skipped++;
                    } else {
                        $upserted++;
                    }
                }
            }

            $run->update([
                'status' => 'completed',
                'rows_fetched' => $fetched,
                'rows_upserted' => $upserted,
                'rows_skipped' => $skipped,
                'completed_at' => now(),
            ]);

            event(new MetricSyncCompleted($run));

            return $run->fresh();
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'errors' => [['message' => 'Sync Failed', 'detail' => mb_substr($e->getMessage(), 0, 300)]],
                'completed_at' => now(),
            ]);
            event(new MetricSyncFailed($run));

            return $run->fresh();
        }
    }

    /**
     * @param  array{metric_key: string, metric_value: float, metric_date: string, currency: ?string}  $mapped
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $entity
     */
    protected function upsertSnapshot(
        int $clientId,
        IntegrationConnection $connection,
        MetricSyncRun $run,
        array $mapped,
        array $row,
        ?array $entity
    ): string {
        $externalId = $row['external_entity_id'] ?? $entity['external_id'] ?? null;
        $provider = $connection->provider;
        $source = $provider;

        return DB::transaction(function () use ($clientId, $connection, $run, $mapped, $row, $entity, $externalId, $provider, $source) {
            $existing = MarketingMetricSnapshot::query()
                ->where('client_id', $clientId)
                ->where('source', $source)
                ->where('provider', $provider)
                ->where('metric_key', $mapped['metric_key'])
                ->whereDate('metric_date', $mapped['metric_date'])
                ->where(function ($q) use ($externalId) {
                    if ($externalId === null) {
                        $q->whereNull('external_entity_id');
                    } else {
                        $q->where('external_entity_id', $externalId);
                    }
                })
                ->first();

            if ($existing && $existing->is_manual) {
                return 'skipped';
            }

            $attrs = [
                'campaign_id' => $entity['campaign_id'] ?? $existing?->campaign_id,
                'content_item_id' => $entity['content_item_id'] ?? $existing?->content_item_id,
                'integration_connection_id' => $connection->id,
                'provider' => $provider,
                'external_entity_type' => $row['external_entity_type'] ?? $entity['entity_type'] ?? null,
                'external_entity_id' => $externalId,
                'metric_value' => $mapped['metric_value'],
                'currency' => $mapped['currency'] ?? $row['currency'] ?? null,
                'dimensions' => $row['dimensions'] ?? null,
                'metadata' => $row['metadata'] ?? null,
                'is_manual' => false,
                'sync_run_id' => $run->id,
            ];

            if ($existing) {
                $existing->update($attrs);
            } else {
                MarketingMetricSnapshot::create(array_merge($attrs, [
                    'client_id' => $clientId,
                    'source' => $source,
                    'metric_key' => $mapped['metric_key'],
                    'metric_date' => $mapped['metric_date'],
                ]));
            }

            return 'upserted';
        });
    }

    protected function mapRow(string $provider, array $row): ?array
    {
        return match ($provider) {
            'meta' => MetaMetricMapper::mapRow($row),
            'google_ads' => GoogleAdsMetricMapper::mapRow($row),
            'google_analytics' => Ga4MetricMapper::mapRow($row),
            'youtube' => YouTubeMetricMapper::mapRow($row),
            default => null,
        };
    }

    public function backfill(Client $client, int $days = 30, ?string $provider = null, ?User $actor = null): array
    {
        return $this->syncClient($client, $provider, $days, 'backfill', $actor);
    }
}
