<?php

namespace App\Services;

use App\Models\IntegrationConnection;
use App\Models\MarketingMetricSnapshot;
use App\Models\MetricSyncRun;
use Carbon\Carbon;

class MarketingPerformanceService
{
    public function __construct(
        protected MetricAggregationService $aggregation
    ) {
    }

    public function clientDashboard(int $clientId, string $from, string $to, ?string $compareFrom = null, ?string $compareTo = null): array
    {
        $current = $this->aggregation->aggregate($clientId, $from, $to);
        $previous = null;
        if ($compareFrom && $compareTo) {
            $previous = $this->aggregation->aggregate($clientId, $compareFrom, $compareTo);
        }

        return [
            'metrics' => $current,
            'comparison' => $previous,
            'freshness' => $this->freshness($clientId),
            'coverage' => $this->coverage($clientId, $from, $to),
        ];
    }

    public function campaignDashboard(int $clientId, int $campaignId, string $from, string $to): array
    {
        return [
            'metrics' => $this->aggregation->aggregate($clientId, $from, $to, $campaignId),
            'freshness' => $this->freshness($clientId, $campaignId),
            'coverage' => $this->coverage($clientId, $from, $to, $campaignId),
        ];
    }

    public function contentDashboard(int $clientId, int $contentItemId, string $from, string $to): array
    {
        return [
            'metrics' => $this->aggregation->aggregate($clientId, $from, $to, null, $contentItemId),
            'freshness' => $this->freshness($clientId, null, $contentItemId),
            'coverage' => $this->coverage($clientId, $from, $to, null, $contentItemId),
        ];
    }

    public function topContent(int $clientId, string $from, string $to, string $metric = 'engagements', int $limit = 10): array
    {
        $rows = MarketingMetricSnapshot::query()
            ->where('client_id', $clientId)
            ->where('metric_key', $metric)
            ->whereNotNull('content_item_id')
            ->whereDate('metric_date', '>=', $from)
            ->whereDate('metric_date', '<=', $to)
            ->selectRaw('content_item_id, SUM(metric_value) as total')
            ->groupBy('content_item_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'content_item_id' => $r->content_item_id,
            'value' => (float) $r->total,
            'display' => number_format((float) $r->total, 0),
        ])->all();
    }

    public function freshness(int $clientId, ?int $campaignId = null, ?int $contentItemId = null): array
    {
        $connections = IntegrationConnection::query()
            ->where('client_id', $clientId)
            ->whereIn('provider', ['meta', 'google_ads', 'google_analytics', 'youtube'])
            ->get();

        if ($connections->isEmpty()) {
            return ['status' => 'not_connected', 'label' => 'Not Connected', 'last_sync_at' => null];
        }

        $lastRun = MetricSyncRun::query()
            ->where('client_id', $clientId)
            ->whereIn('status', ['completed', 'partial', 'failed'])
            ->orderByDesc('completed_at')
            ->first();

        if ($lastRun && $lastRun->status === 'failed') {
            return [
                'status' => 'sync_failed',
                'label' => 'Sync Failed',
                'last_sync_at' => optional($lastRun->completed_at)?->toIso8601String(),
            ];
        }

        $latestSnapshot = MarketingMetricSnapshot::query()
            ->where('client_id', $clientId)
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->when($contentItemId, fn ($q) => $q->where('content_item_id', $contentItemId))
            ->where('is_manual', false)
            ->orderByDesc('metric_date')
            ->orderByDesc('updated_at')
            ->first();

        if (! $latestSnapshot && ! $lastRun) {
            return ['status' => 'no_data', 'label' => 'No Data', 'last_sync_at' => null];
        }

        $ref = $lastRun?->completed_at ?? $latestSnapshot?->updated_at ?? now();
        $hours = Carbon::parse($ref)->diffInHours(now());

        if ($hours <= 6) {
            $status = 'fresh';
            $label = 'Fresh';
        } elseif ($hours <= 36) {
            $status = 'delayed';
            $label = 'Delayed';
        } else {
            $status = 'stale';
            $label = 'Stale';
        }

        return [
            'status' => $status,
            'label' => $label,
            'last_sync_at' => optional($ref)->toIso8601String(),
        ];
    }

    public function coverage(int $clientId, string $from, string $to, ?int $campaignId = null, ?int $contentItemId = null): array
    {
        $providersExpected = ['meta', 'google_ads', 'google_analytics', 'youtube'];
        $connected = IntegrationConnection::query()
            ->where('client_id', $clientId)
            ->where('status', 'connected')
            ->whereIn('provider', $providersExpected)
            ->pluck('provider')
            ->unique()
            ->values();

        $hasManual = MarketingMetricSnapshot::query()
            ->where('client_id', $clientId)
            ->where('is_manual', true)
            ->whereDate('metric_date', '>=', $from)
            ->whereDate('metric_date', '<=', $to)
            ->exists();

        $providersWithData = MarketingMetricSnapshot::query()
            ->where('client_id', $clientId)
            ->whereDate('metric_date', '>=', $from)
            ->whereDate('metric_date', '<=', $to)
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->when($contentItemId, fn ($q) => $q->where('content_item_id', $contentItemId))
            ->whereNotNull('provider')
            ->pluck('provider')
            ->unique();

        if ($connected->isEmpty() && $hasManual) {
            return ['status' => 'manual_only', 'label' => 'Manual Only', 'warning' => null];
        }
        if ($connected->isEmpty()) {
            return ['status' => 'missing_provider', 'label' => 'Missing Provider', 'warning' => 'Not Connected'];
        }

        $missing = $connected->diff($providersWithData);
        if ($missing->isNotEmpty() && $providersWithData->isNotEmpty()) {
            return [
                'status' => 'partial',
                'label' => 'Partial',
                'warning' => 'Partial data — some connected providers have no metrics in this range.',
                'missing_providers' => $missing->values()->all(),
            ];
        }

        if ($hasManual && $providersWithData->isNotEmpty()) {
            return ['status' => 'mixed', 'label' => 'Mixed Sources', 'warning' => null];
        }

        if ($providersWithData->isEmpty()) {
            return ['status' => 'no_data', 'label' => 'No Data', 'warning' => 'No Data'];
        }

        return ['status' => 'complete', 'label' => 'Complete', 'warning' => null];
    }
}
