<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id', 'campaign_id', 'campaign_deliverable_id', 'content_item_id', 'client_request_id', 'title', 'description',
        'status', 'priority', 'start_date', 'due_date', 'estimated_minutes',
        'completed_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['is_overdue'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(CampaignDeliverable::class, 'campaign_deliverable_id');
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(InternalReview::class, 'reviewable')->latest();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(TaskAllocation::class);
    }

    public function delayRecords(): MorphMany
    {
        return $this->morphMany(DelayRecord::class, 'delayable')->latest();
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->lt(now()->startOfDay())
            && ! in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }

    public function primaryAssignee(): ?TaskAssignee
    {
        return $this->assignees->firstWhere('role', 'assignee')
            ?? $this->assignees->first();
    }
}
