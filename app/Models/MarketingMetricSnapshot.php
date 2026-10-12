<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingMetricSnapshot extends Model
{
    protected $fillable = [
        'client_id', 'campaign_id', 'content_item_id', 'social_account_id',
        'integration_connection_id', 'source', 'provider', 'external_entity_type',
        'external_entity_id', 'metric_key', 'metric_value', 'currency', 'metric_date',
        'dimensions', 'metadata', 'is_manual', 'sync_run_id',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'metric_value' => 'decimal:4',
        'dimensions' => 'array',
        'metadata' => 'array',
        'is_manual' => 'boolean',
    ];

    protected $appends = ['metric_code'];

    public function getMetricCodeAttribute(): string
    {
        return (string) $this->metric_key;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(MetricSyncRun::class, 'sync_run_id');
    }
}
