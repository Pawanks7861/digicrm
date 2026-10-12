<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DelayRecord extends Model
{
    protected $fillable = [
        'delayable_type', 'delayable_id', 'delay_reason_id',
        'started_at', 'ended_at', 'notes', 'source',
        'created_by', 'resolved_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $appends = ['duration_seconds', 'is_active'];

    public function delayable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(DelayReason::class, 'delay_reason_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function durationSeconds(): int
    {
        $end = $this->ended_at ?? now();

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }

    public function getDurationSecondsAttribute(): int
    {
        return $this->durationSeconds();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }
}
