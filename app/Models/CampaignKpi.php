<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignKpi extends Model
{
    protected $fillable = [
        'client_id', 'campaign_id', 'metric_code', 'target_value', 'target_type',
        'period', 'period_start', 'period_end', 'is_active',
    ];

    protected $casts = [
        'target_value' => 'decimal:4',
        'period_start' => 'date',
        'period_end' => 'date',
        'is_active' => 'boolean',
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
