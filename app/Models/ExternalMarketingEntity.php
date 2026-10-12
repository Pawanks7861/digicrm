<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalMarketingEntity extends Model
{
    protected $fillable = [
        'client_id', 'campaign_id', 'content_item_id', 'integration_connection_id',
        'provider', 'entity_type', 'external_id', 'external_name', 'status', 'metadata',
    ];

    protected $casts = [
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

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }
}
