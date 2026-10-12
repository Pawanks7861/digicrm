<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'client_id', 'task_id', 'campaign_id', 'campaign_deliverable_id',
        'content_item_id', 'client_request_id', 'started_at', 'ended_at', 'duration_seconds',
        'entry_type', 'billable', 'category', 'description', 'status',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'approved_at' => 'datetime',
        'billable' => 'boolean',
        'duration_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(CampaignDeliverable::class, 'campaign_deliverable_id');
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function liveDurationSeconds(): int
    {
        if ($this->ended_at) {
            return (int) $this->duration_seconds;
        }

        return max(0, (int) $this->started_at->diffInSeconds(now()));
    }

    public function scopeValid($query)
    {
        return $query->where('status', '!=', 'rejected');
    }

    public function scopeRunning($query)
    {
        return $query->whereNull('ended_at');
    }
}
