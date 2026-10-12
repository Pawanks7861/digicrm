<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends Model
{
    public const CHANNELS = ['form', 'meta', 'google', 'social', 'messaging', 'manual', 'web', 'import', 'webhook', 'other'];

    protected $fillable = ['name', 'code', 'channel', 'lead_category', 'is_system', 'is_active', 'sort_order'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function idFor(string $code): ?int
    {
        return static::query()->where('code', $code)->value('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
