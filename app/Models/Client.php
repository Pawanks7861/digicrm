<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'prospect',
        'onboarding',
        'active',
        'on_hold',
        'completed',
        'lost',
        'cancelled',
    ];

    protected $fillable = [
        'company_name',
        'billing_legal_name',
        'contact_person',
        'accounts_contact',
        'email',
        'billing_email',
        'mobile',
        'alternate_mobile',
        'website',
        'industry',
        'address',
        'billing_address',
        'city',
        'billing_city',
        'state',
        'billing_state',
        'country',
        'billing_pin',
        'gst_number',
        'pan',
        'purchase_order_number',
        'social_urls',
        'account_manager_id',
        'start_date',
        'renewal_date',
        'status',
        'notes',
        'logo_path',
    ];

    protected $casts = [
        'social_urls' => 'array',
        'start_date' => 'date',
        'renewal_date' => 'date',
    ];

    protected $appends = [
        'onboarding_progress',
    ];

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function brandProfile(): HasOne
    {
        return $this->hasOne(ClientBrandProfile::class);
    }

    public function onboardingItems(): HasMany
    {
        return $this->hasMany(ClientOnboardingItem::class)->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClientFile::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ClientPackage::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function packageOverages(): HasMany
    {
        return $this->hasMany(PackageOverage::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function campaignLeads(): HasMany
    {
        return $this->hasMany(Lead::class)->where('lead_category', Lead::CATEGORY_CAMPAIGN);
    }

    public function getOnboardingProgressAttribute(): array
    {
        $total = $this->relationLoaded('onboardingItems')
            ? $this->onboardingItems->count()
            : $this->onboardingItems()->count();

        $completed = $this->relationLoaded('onboardingItems')
            ? $this->onboardingItems->where('is_completed', true)->count()
            : $this->onboardingItems()->where('is_completed', true)->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }
}
