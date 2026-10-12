<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricSyncRun extends Model
{
    protected $fillable = [
        'client_id', 'integration_connection_id', 'provider', 'sync_type',
        'date_from', 'date_to', 'status', 'rows_fetched', 'rows_upserted',
        'rows_skipped', 'errors', 'started_at', 'completed_at', 'triggered_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }
}
