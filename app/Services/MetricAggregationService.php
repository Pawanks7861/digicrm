<?php

namespace App\Services;

use App\Marketing\MarketingMetricRegistry;
use App\Models\MarketingMetricSnapshot;
use Illuminate\Support\Collection;

class MetricAggregationService
{
    public const NA = 'Not Available';

    /**
     * Aggregate base metrics for a client (optionally scoped) over a date range.
     *
     * @return array<string, array{code: string, value: float|null, display: string, aggregation: string, currency: ?string, formula_label: ?string}>
     */
    public function aggregate(
        int $clientId,
        string $from,
        string $to,
        ?int $campaignId = null,
        ?int $contentItemId = null,
        ?string $provider = null
    ): array {
        $query = MarketingMetricSnapshot::query()
            ->where('client_id', $clientId)
            ->whereDate('metric_date', '>=', $from)
            ->whereDate('metric_date', '<=', $to);

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        if ($contentItemId) {
            $query->where('content_item_id', $contentItemId);
        }
        if ($provider) {
            $query->where('provider', $provider);
        }

        $snapshots = $query->get();
        $base = $this->aggregateBase($snapshots);
        $derived = $this->computeDerived($base);

        return array_merge($base, $derived);
    }

    /**
     * @param  Collection<int, MarketingMetricSnapshot>  $snapshots
     * @return array<string, array{code: string, value: float|null, display: string, aggregation: string, currency: ?string, formula_label: ?string}>
     */
    public function aggregateBase(Collection $snapshots): array
    {
        $grouped = $snapshots->groupBy('metric_key');
        $out = [];

        foreach ($grouped as $key => $rows) {
            $def = MarketingMetricRegistry::get($key);
            $agg = $def['aggregation'] ?? MarketingMetricRegistry::AGG_SUM;
            if ($agg === MarketingMetricRegistry::AGG_DERIVED) {
                continue;
            }

            $currency = $this->resolveCurrency($rows);
            if ($currency === 'mixed') {
                $out[$key] = $this->result($key, null, self::NA, $agg, null, 'Currency mix not supported');
                continue;
            }

            if ($agg === MarketingMetricRegistry::AGG_LATEST) {
                $latest = $rows->sortByDesc(fn ($r) => $r->metric_date->format('Y-m-d').'-'.$r->id)->first();
                $value = $latest ? (float) $latest->metric_value : null;
            } else {
                $value = (float) $rows->sum(fn ($r) => (float) $r->metric_value);
            }

            $out[$key] = $this->result($key, $value, $this->formatValue($value, $def), $agg, $currency, $def['formula_label'] ?? null);
        }

        return $out;
    }

    /**
     * @param  array<string, array{code: string, value: float|null, display: string, aggregation: string, currency: ?string, formula_label: ?string}>  $base
     * @return array<string, array{code: string, value: float|null, display: string, aggregation: string, currency: ?string, formula_label: ?string}>
     */
    public function computeDerived(array $base): array
    {
        $v = fn (string $code) => $base[$code]['value'] ?? null;
        $currency = $base['spend']['currency'] ?? $base['attributed_revenue']['currency'] ?? null;

        $out = [];
        $out['ctr'] = $this->ratio($v('clicks'), $v('impressions'), true, 'ctr', $currency);
        $out['cpc'] = $this->ratio($v('spend'), $v('clicks'), false, 'cpc', $currency);
        $out['cpl'] = $this->ratio($v('spend'), $v('leads'), false, 'cpl', $currency);
        $out['cpa'] = $this->ratio($v('spend'), $v('conversions'), false, 'cpa', $currency);
        $out['conversion_rate'] = $this->ratio($v('conversions'), $v('clicks'), true, 'conversion_rate', $currency);
        $out['roas'] = $this->ratio($v('attributed_revenue'), $v('spend'), false, 'roas', $currency);
        $out['engagement_rate_by_reach'] = $this->ratio($v('engagements'), $v('reach'), true, 'engagement_rate_by_reach', null);
        $out['engagement_rate_by_impressions'] = $this->ratio($v('engagements'), $v('impressions'), true, 'engagement_rate_by_impressions', null);
        $out['engagement_rate_by_followers'] = $this->ratio($v('engagements'), $v('followers'), true, 'engagement_rate_by_followers', null);

        return $out;
    }

    protected function ratio(?float $num, ?float $den, bool $percent, string $code, ?string $currency): array
    {
        $def = MarketingMetricRegistry::get($code);
        $label = $def['formula_label'] ?? null;

        if ($num === null || $den === null || (float) $den == 0.0) {
            return $this->result($code, null, self::NA, MarketingMetricRegistry::AGG_DERIVED, $currency, $label);
        }

        $value = $percent
            ? ($num / $den) * 100
            : ($num / $den);

        return $this->result($code, $value, $this->formatValue($value, $def), MarketingMetricRegistry::AGG_DERIVED, $currency, $label);
    }

    /**
     * @param  Collection<int, MarketingMetricSnapshot>  $rows
     */
    protected function resolveCurrency(Collection $rows): ?string
    {
        $currencies = $rows->pluck('currency')->filter()->unique()->values();
        if ($currencies->count() > 1) {
            return 'mixed';
        }

        return $currencies->first();
    }

    protected function formatValue(?float $value, ?array $def): string
    {
        if ($value === null) {
            return self::NA;
        }
        if ($def && ($def['is_percent'] ?? false)) {
            return number_format($value, 2).'%';
        }
        if ($def && ($def['is_currency'] ?? false)) {
            return number_format($value, 2);
        }
        if (($def['unit'] ?? '') === 'ratio') {
            return number_format($value, 2).'x';
        }

        return number_format($value, $value == floor($value) ? 0 : 2);
    }

    protected function result(string $code, ?float $value, string $display, string $agg, ?string $currency, ?string $formulaLabel): array
    {
        return [
            'code' => $code,
            'label' => MarketingMetricRegistry::label($code),
            'value' => $value,
            'display' => $display,
            'aggregation' => $agg,
            'currency' => $currency,
            'formula_label' => $formulaLabel,
        ];
    }
}
