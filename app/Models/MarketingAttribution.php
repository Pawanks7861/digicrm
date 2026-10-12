<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAttribution extends Model
{
    protected $fillable = [
        'client_id', 'campaign_id', 'content_item_id', 'source_type',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'provider', 'external_id', 'metric_date', 'leads_count', 'conversions_count',
        'conversion_value', 'currency', 'provenance_label', 'metadata',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'conversion_value' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
