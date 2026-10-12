<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FollowUp extends Model
{
    protected $fillable = [
        'client_id', 'followupable_type', 'followupable_id', 'assigned_to', 'created_by',
        'title', 'notes', 'follow_up_type', 'due_at', 'priority', 'status',
        'completed_at', 'snoozed_until', 'outcome',
        'google_calendar_event_id', 'google_calendar_synced_at', 'sync_to_google_calendar',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'google_calendar_synced_at' => 'datetime',
        'sync_to_google_calendar' => 'boolean',
    ];

    protected $appends = ['display_status'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function followupable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'completed') {
            return 'completed';
        }
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->status === 'snoozed' && $this->snoozed_until && $this->snoozed_until->isFuture()) {
            return 'snoozed';
        }
        if ($this->due_at->lt(now()->startOfDay())) {
            return 'overdue';
        }
        if ($this->due_at->isToday()) {
            return 'due_today';
        }

        return 'pending';
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled'], true);
    }
}
