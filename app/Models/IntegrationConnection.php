<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class IntegrationConnection extends Model
{
    protected $fillable = [
        'provider', 'name', 'status', 'connected_by', 'connected_at',
        'last_health_check_at', 'health_status', 'health_message',
        'access_token_encrypted', 'refresh_token_encrypted', 'token_expires_at',
        'credentials_encrypted', 'meta', 'client_id', 'is_enabled',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_health_check_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'meta' => 'array',
        'is_enabled' => 'boolean',
    ];

    protected $hidden = [
        'access_token_encrypted', 'refresh_token_encrypted', 'credentials_encrypted',
    ];

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class, 'connection_id');
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['access_token_encrypted'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(): ?string
    {
        $enc = $this->attributes['access_token_encrypted'] ?? null;
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['refresh_token_encrypted'] = Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute(): ?string
    {
        $enc = $this->attributes['refresh_token_encrypted'] ?? null;
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setCredentialsAttribute(?array $value): void
    {
        if ($value === null) {
            return;
        }
        $this->attributes['credentials_encrypted'] = Crypt::encryptString(json_encode($value));
    }

    public function getCredentialsAttribute(): ?array
    {
        $enc = $this->attributes['credentials_encrypted'] ?? null;
        if (! $enc) {
            return null;
        }
        try {
            return json_decode(Crypt::decryptString($enc), true);
        } catch (\Throwable) {
            return null;
        }
    }

    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->name,
            'status' => $this->status,
            'connected_at' => optional($this->connected_at)?->toIso8601String(),
            'last_health_check_at' => optional($this->last_health_check_at)?->toIso8601String(),
            'health_status' => $this->health_status,
            'health_message' => $this->health_message,
            'meta' => $this->meta,
            'client_id' => $this->client_id,
            'is_enabled' => $this->is_enabled,
            'has_access_token' => (bool) ($this->attributes['access_token_encrypted'] ?? null),
            'token_expires_at' => optional($this->token_expires_at)?->toIso8601String(),
        ];
    }
}
