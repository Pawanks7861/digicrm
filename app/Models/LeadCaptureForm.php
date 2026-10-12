<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class LeadCaptureForm extends Model
{
    use SoftDeletes;

    public const FIELDS = [
        'name', 'email', 'mobile', 'company', 'city', 'state', 'country',
        'interested_service', 'requirement', 'estimated_budget',
    ];

    protected $fillable = [
        'name', 'form_key', 'lead_category', 'client_id', 'campaign_id', 'lead_source_id', 'is_active',
        'allowed_origins', 'require_consent', 'consent_text', 'min_fill_seconds', 'honeypot_field',
        'fields', 'success_message', 'redirect_url', 'submissions_count', 'last_submission_at', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_origins' => 'array',
        'require_consent' => 'boolean',
        'fields' => 'array',
        'min_fill_seconds' => 'integer',
        'last_submission_at' => 'datetime',
    ];

    protected $hidden = ['hmac_secret_encrypted'];

    protected $appends = ['has_hmac_secret'];

    public static function generateKey(): string
    {
        do {
            $key = Str::random(40);
        } while (static::withTrashed()->where('form_key', $key)->exists());

        return $key;
    }

    public function setHmacSecretAttribute(?string $value): void
    {
        $this->attributes['hmac_secret_encrypted'] = ($value === null || $value === '') ? null : Crypt::encryptString($value);
    }

    public function getHmacSecretAttribute(): ?string
    {
        $enc = $this->attributes['hmac_secret_encrypted'] ?? null;
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getHasHmacSecretAttribute(): bool
    {
        return ! empty($this->attributes['hmac_secret_encrypted'] ?? null);
    }

    public function enabledFields(): array
    {
        $fields = $this->fields ?: ['name', 'email', 'mobile', 'requirement'];

        return array_values(array_intersect(self::FIELDS, $fields));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
