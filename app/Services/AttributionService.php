<?php

namespace App\Services;

use App\Models\MarketingAttribution;
use App\Models\User;

class AttributionService
{
    public const PROVENANCE = [
        'direct_provider' => 'Direct provider',
        'utm_match' => 'UTM match',
        'manual' => 'Manual',
        'crm_source' => 'CRM source',
        'unknown' => 'Unknown',
    ];

    public function store(array $data, ?User $actor = null): MarketingAttribution
    {
        $sourceType = $data['source_type'] ?? 'unknown';
        $data['provenance_label'] = $data['provenance_label']
            ?? (self::PROVENANCE[$sourceType] ?? 'Unknown');

        return MarketingAttribution::create($data);
    }

    public function forClient(int $clientId, ?string $from = null, ?string $to = null): array
    {
        $q = MarketingAttribution::query()->where('client_id', $clientId);
        if ($from) {
            $q->whereDate('metric_date', '>=', $from);
        }
        if ($to) {
            $q->whereDate('metric_date', '<=', $to);
        }

        return $q->orderByDesc('metric_date')->get()->map(fn (MarketingAttribution $a) => [
            'id' => $a->id,
            'campaign_id' => $a->campaign_id,
            'source_type' => $a->source_type,
            'provenance_label' => $a->provenance_label,
            'leads_count' => $a->leads_count,
            'conversions_count' => $a->conversions_count,
            'conversion_value' => $a->conversion_value,
            'currency' => $a->currency,
            'metric_date' => optional($a->metric_date)?->toDateString(),
            'utm_source' => $a->utm_source,
            'utm_campaign' => $a->utm_campaign,
            'provider' => $a->provider,
        ])->all();
    }
}
