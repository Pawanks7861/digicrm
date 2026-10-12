<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignDeliverable extends Model
{
    use HasFactory, SoftDeletes;

    public const TRANSITIONS = [
        'brief_pending' => ['ready', 'cancelled'],
        'ready' => ['assigned', 'in_progress', 'waiting_for_client', 'cancelled'],
        'assigned' => ['in_progress', 'waiting_for_client', 'cancelled'],
        'in_progress' => ['internal_review', 'waiting_for_client', 'cancelled'],
        'internal_review' => ['changes_required', 'ready_for_client_review', 'waiting_for_client', 'cancelled'],
        'changes_required' => ['in_progress', 'waiting_for_client', 'cancelled'],
        'ready_for_client_review' => ['waiting_for_client', 'completed', 'cancelled'],
        'waiting_for_client' => ['completed', 'changes_required', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'campaign_id', 'client_id', 'client_package_id', 'client_package_item_id', 'client_request_id',
        'title', 'deliverable_type', 'platform', 'description', 'quantity', 'unit',
        'priority', 'internal_due_date', 'client_due_date', 'status', 'scope_status',
        'package_usage_transaction_id', 'scope_consumed_at', 'completed_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'internal_due_date' => 'date',
        'client_due_date' => 'date',
        'scope_consumed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['progress_percent', 'is_overdue'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function clientPackageItem(): BelongsTo
    {
        return $this->belongsTo(ClientPackageItem::class);
    }

    public function usageTransaction(): BelongsTo
    {
        return $this->belongsTo(PackageUsageTransaction::class, 'package_usage_transaction_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'campaign_deliverable_id');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'campaign_deliverable_id');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(InternalReview::class, 'reviewable')->latest();
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function getProgressPercentAttribute(): int
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        if ($tasks->count() === 0) {
            return match ($this->status) {
                'completed' => 100,
                'ready_for_client_review', 'waiting_for_client' => 90,
                'internal_review', 'changes_required' => 70,
                'in_progress', 'assigned' => 40,
                'ready' => 20,
                default => 0,
            };
        }

        $done = $tasks->where('status', 'completed')->count();

        return (int) round(($done / $tasks->count()) * 100);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return false;
        }
        $due = $this->internal_due_date ?? $this->client_due_date;

        return $due && $due->lt(now()->startOfDay());
    }

    public function hasConsumedScope(): bool
    {
        return $this->scope_consumed_at !== null;
    }
}
