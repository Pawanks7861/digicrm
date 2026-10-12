<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentItem extends Model
{
    use HasFactory, SoftDeletes;

    public const TRANSITIONS = [
        'draft' => ['in_production', 'internal_review', 'cancelled'],
        'in_production' => ['internal_review', 'changes_required', 'cancelled'],
        'internal_review' => ['changes_required', 'ready_for_client_review', 'cancelled'],
        'changes_required' => ['in_production', 'internal_review', 'cancelled'],
        'ready_for_client_review' => ['sent_to_client', 'cancelled'],
        'sent_to_client' => ['client_approved', 'client_changes_requested', 'ready_for_client_review', 'cancelled'],
        'client_changes_requested' => ['in_production', 'internal_review', 'cancelled'],
        'client_approved' => ['scheduled', 'published', 'cancelled'],
        'scheduled' => ['published', 'client_approved', 'cancelled'],
        'published' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'client_id', 'campaign_id', 'campaign_deliverable_id', 'title', 'content_type',
        'platform', 'caption', 'description', 'hashtags', 'cta', 'target_url',
        'scheduled_at', 'published_at', 'published_url', 'status', 'current_revision_id',
        'client_revision_cycles', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'client_revision_cycles' => 'integer',
    ];

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

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'current_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class)->orderBy('revision_number');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ClientApproval::class)->latest('requested_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContentComment::class)->latest();
    }

    public function clientVisibleComments(): HasMany
    {
        return $this->hasMany(ContentComment::class)->where('visibility', 'client_visible')->latest();
    }

    public function internalReviews(): MorphMany
    {
        return $this->morphMany(InternalReview::class, 'reviewable')->latest('submitted_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['published', 'cancelled'], true);
    }
}
