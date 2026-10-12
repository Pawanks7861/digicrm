<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    public const CATEGORY_AGENCY = 'agency_sales';
    public const CATEGORY_CAMPAIGN = 'client_campaign';
    public const CATEGORIES = [self::CATEGORY_AGENCY, self::CATEGORY_CAMPAIGN];

    public const STATUSES = [
        'new', 'unassigned', 'assigned', 'contacted', 'qualified', 'unqualified', 'follow_up',
        'opportunity', 'proposal_sent', 'negotiation', 'won', 'lost', 'on_hold',
    ];

    public const AGENCY_ONLY_STATUSES = ['opportunity', 'proposal_sent', 'negotiation', 'won'];

    public const CLOSED_STATUSES = ['won', 'lost'];

    public const TRANSITIONS = [
        'new' => ['unassigned', 'assigned', 'contacted', 'qualified', 'unqualified', 'follow_up', 'lost', 'on_hold'],
        'unassigned' => ['assigned', 'contacted', 'qualified', 'unqualified', 'follow_up', 'lost', 'on_hold'],
        'assigned' => ['unassigned', 'contacted', 'qualified', 'unqualified', 'follow_up', 'lost', 'on_hold'],
        'contacted' => ['qualified', 'unqualified', 'follow_up', 'opportunity', 'lost', 'on_hold'],
        'qualified' => ['contacted', 'follow_up', 'opportunity', 'proposal_sent', 'unqualified', 'lost', 'on_hold'],
        'unqualified' => ['contacted', 'qualified', 'follow_up', 'lost'],
        'follow_up' => ['contacted', 'qualified', 'unqualified', 'opportunity', 'proposal_sent', 'lost', 'on_hold'],
        'opportunity' => ['follow_up', 'proposal_sent', 'negotiation', 'won', 'lost', 'on_hold'],
        'proposal_sent' => ['follow_up', 'negotiation', 'won', 'lost', 'on_hold'],
        'negotiation' => ['follow_up', 'proposal_sent', 'won', 'lost', 'on_hold'],
        'won' => [],
        'lost' => ['contacted', 'follow_up'],
        'on_hold' => ['assigned', 'contacted', 'qualified', 'follow_up', 'lost'],
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const DUPLICATE_STATUSES = ['possible', 'kept_separate', 'duplicate', 'merged'];

    public const FIRST_TOUCH_FIELDS = [
        'lead_source_id', 'source_detail', 'campaign_id', 'content_item_id',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'gclid', 'fbclid', 'external_campaign_id', 'external_form_id',
        'provider', 'provider_lead_id', 'landing_page_url', 'referrer', 'source_timestamp',
    ];

    public const CONTACT_FIELDS = ['email', 'mobile', 'mobile_normalized', 'alternate_mobile'];

    protected $fillable = [
        'lead_number', 'lead_category', 'client_id', 'prospect_client_id', 'converted_client_id', 'converted_at',
        'name', 'company', 'email', 'mobile', 'mobile_normalized', 'alternate_mobile',
        'industry', 'city', 'state', 'country', 'interested_service', 'requirement',
        'estimated_budget', 'currency', 'lead_source_id', 'source_detail', 'campaign_id', 'content_item_id',
        'assigned_to', 'assigned_at', 'priority', 'status', 'first_contacted_at', 'last_contacted_at',
        'next_follow_up_at', 'qualified_at', 'uncontacted_alerted_at', 'lost_reason_id', 'lost_notes',
        'possible_duplicate_of_id', 'duplicate_status', 'merged_into_id',
        'provider', 'provider_lead_id',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'gclid', 'fbclid', 'external_campaign_id', 'external_form_id',
        'landing_page_url', 'referrer', 'source_timestamp',
        'consent_given', 'consent_text', 'consent_at', 'lead_capture_form_id', 'created_by',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
        'assigned_at' => 'datetime',
        'first_contacted_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'qualified_at' => 'datetime',
        'uncontacted_alerted_at' => 'datetime',
        'source_timestamp' => 'datetime',
        'consent_at' => 'datetime',
        'consent_given' => 'boolean',
        'estimated_budget' => 'decimal:2',
    ];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isClientUser()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('super_admin') || $user->can('leads.view_all')) {
            return $query;
        }

        if (! $user->can('leads.view')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('leads.assigned_to', $user->id)
                ->orWhere(function (Builder $c) use ($user) {
                    $c->where('leads.lead_category', self::CATEGORY_CAMPAIGN)
                        ->whereIn('leads.client_id', Client::query()->select('id')->where('account_manager_id', $user->id));
                });
        });
    }

    public function scopeForPortalClient(Builder $query, int $clientId): Builder
    {
        return $query->where('lead_category', self::CATEGORY_CAMPAIGN)->where('client_id', $clientId);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('lead_category', $category);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED_STATUSES);
    }

    public function isAgencySales(): bool
    {
        return $this->lead_category === self::CATEGORY_AGENCY;
    }

    public function isCampaignLead(): bool
    {
        return $this->lead_category === self::CATEGORY_CAMPAIGN;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function allowedTransitions(): array
    {
        $next = self::TRANSITIONS[$this->status] ?? [];
        if (! $this->isAgencySales()) {
            $next = array_values(array_diff($next, self::AGENCY_ONLY_STATUSES));
        }

        return $next;
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function prospectClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'prospect_client_id');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class);
    }

    public function captureForm(): BelongsTo
    {
        return $this->belongsTo(LeadCaptureForm::class, 'lead_capture_form_id');
    }

    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of_id')->withTrashed();
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id')->withTrashed();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(LeadTouchpoint::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followupable');
    }

    public function communications(): MorphMany
    {
        return $this->morphMany(ClientCommunication::class, 'communicable');
    }
}
