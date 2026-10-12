<?php

namespace App\Services;

use App\Events\KpiBelowTarget;
use App\Models\CampaignKpi;
use App\Models\User;

class KpiService
{
    public function __construct(
        protected MetricAggregationService $aggregation
    ) {
    }

    public function create(array $data, User $actor): CampaignKpi
    {
        return CampaignKpi::create($data);
    }

    public function update(CampaignKpi $kpi, array $data): CampaignKpi
    {
        $kpi->update($data);

        return $kpi->fresh();
    }

    /**
     * Progress = actual/target. Status rules are deterministic.
     *
     * @return array{actual: float|null, target: float, progress_pct: float|null, status: string, label: string, display_actual: string}
     */
    public function progress(CampaignKpi $kpi, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?? ($kpi->period_start?->toDateString() ?? now()->startOfMonth()->toDateString());
        $to = $to ?? ($kpi->period_end?->toDateString() ?? now()->toDateString());

        $metrics = $this->aggregation->aggregate(
            (int) $kpi->client_id,
            $from,
            $to,
            (int) $kpi->campaign_id
        );

        $actual = $metrics[$kpi->metric_code]['value'] ?? null;
        $display = $metrics[$kpi->metric_code]['display'] ?? MetricAggregationService::NA;
        $target = (float) $kpi->target_value;

        if ($actual === null) {
            return [
                'actual' => null,
                'target' => $target,
                'progress_pct' => null,
                'status' => 'no_data',
                'label' => 'No Data',
                'display_actual' => $display,
            ];
        }

        $progressPct = $target == 0.0 ? null : round(($actual / $target) * 100, 2);

        $status = match ($kpi->target_type) {
            'max' => $actual <= $target ? 'on_track' : 'above_maximum',
            'reference' => abs($actual - $target) / max(abs($target), 0.0001) <= 0.1 ? 'on_track' : 'below_target',
            default => $actual >= $target ? 'on_track' : 'below_target', // min
        };

        $label = match ($status) {
            'on_track' => 'On Track',
            'above_maximum' => 'Above Maximum',
            'below_target' => 'Below Target',
            default => 'No Data',
        };

        $result = [
            'actual' => $actual,
            'target' => $target,
            'progress_pct' => $progressPct,
            'status' => $status,
            'label' => $label,
            'display_actual' => $display,
        ];

        if ($status === 'below_target') {
            event(new KpiBelowTarget($kpi, $result));
        }

        return $result;
    }
}
