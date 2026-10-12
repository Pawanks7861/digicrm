<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const TRANSITIONS = [
        'submitted' => ['under_review', 'need_more_information', 'accepted', 'rejected', 'scope_decision_pending', 'cancelled'],
        'under_review' => ['need_more_information', 'accepted', 'rejected', 'scope_decision_pending', 'quotation_required', 'cancelled'],
        'need_more_information' => ['under_review', 'waiting_for_client', 'cancelled'],
        'waiting_for_client' => ['under_review', 'need_more_information', 'cancelled'],
        'scope_decision_pending' => ['accepted', 'rejected', 'quotation_required', 'cancelled'],
        'quotation_required' => ['accepted', 'rejected', 'cancelled'],
        'accepted' => ['assigned', 'in_progress', 'cancelled'],
        'assigned' => ['in_progress', 'waiting_for_client', 'ready_for_review', 'completed', 'cancelled'],
        'in_progress' => ['waiting_for_client', 'ready_for_review', 'completed', 'cancelled'],
        'ready_for_review' => ['in_progress', 'completed', 'cancelled'],
        'rejected' => [],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'request_number', 'client_id', 'client_contact_id', 'submitted_by_user_id',
        'title', 'request_type', 'description', 'platform', 'priority',
        'requested_due_date', 'internal_due_date', 'status', 'scope_status',
        'assigned_to', 'campaign_id', 'campaign_deliverable_id', 'task_id',
        'client_package_id', 'client_package_item_id', 'quantity', 'unit',
        'reference_url', 'estimated_overage_amount', 'resolution_notes',
        'rejection_reason', 'internal_notes', 'scope_checked_at',
        'completed_at', 'cancelled_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'requested_due_date' => 'date',
        'internal_due_date' => 'date',
        'quantity' => 'decimal:2',
        'estimated_overage_amount' => 'decimal:2',
        'scope_checked_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(CampaignDeliverable::class, 'campaign_deliverable_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function clientPackageItem(): BelongsTo
    {
        return $this->belongsTo(ClientPackageItem::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ClientRequestComment::class)->latest();
    }

    public function clientVisibleComments(): HasMany
    {
        return $this->hasMany(ClientRequestComment::class)->where('visibility', 'client_visible')->latest();
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(ClientFile::class, 'client_request_files')->withTimestamps();
    }

    public static function generateNextNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "REQ-{$year}-";
        $last = static::withTrashed()->where('request_number', 'like', $prefix.'%')->orderByDesc('request_number')->value('request_number');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isConverted(): bool
    {
        return $this->campaign_id || $this->campaign_deliverable_id || $this->task_id;
    }

    public function isOverdue(): bool
    {
        if (in_array($this->status, ['completed', 'rejected', 'cancelled'], true)) {
            return false;
        }
        $due = $this->internal_due_date ?? $this->requested_due_date;

        return $due && $due->lt(now()->startOfDay());
    }

    public function clientMayCancel(): bool
    {
        return in_array($this->status, ['submitted', 'under_review', 'need_more_information'], true);
    }
}
