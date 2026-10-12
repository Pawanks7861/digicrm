<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTouchpoint extends Model
{
    public const PROVENANCES = ['direct_provider', 'utm_match', 'manual', 'crm_source', 'unknown'];

    protected $fillable = [
        'lead_id', 'touch_type', 'provenance', 'lead_source_id', 'source_detail', 'campaign_id', 'content_item_id',
        'lead_capture_form_id', 'provider', 'provider_lead_id',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid',
        'external_campaign_id', 'external_form_id', 'landing_page_url', 'referrer',
        'occurred_at', 'recorded_by', 'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
