<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPackage extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'upcoming',
        'active',
        'expired',
        'paused',
        'cancelled',
    ];

    protected $fillable = [
        'client_id',
        'service_package_id',
        'package_name',
        'start_date',
        'end_date',
        'billing_cycle',
        'agreed_price',
        'currency',
        'status',
        'auto_renew',
        'renewal_type',
        'notes',
        'assigned_by',
        'renewed_from_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'agreed_price' => 'decimal:2',
        'auto_renew' => 'boolean',
    ];

    protected $appends = [
        'expires_in_days',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientPackageItem::class)->orderBy('sort_order');
    }

    public function usageTransactions(): HasMany
    {
        return $this->hasMany(PackageUsageTransaction::class);
    }

    public function overages(): HasMany
    {
        return $this->hasMany(PackageOverage::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function getExpiresInDaysAttribute(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    public function isConsumable(): bool
    {
        return in_array($this->status, ['active', 'upcoming'], true)
            && (! $this->end_date || $this->end_date->gte(now()->startOfDay()))
            && $this->start_date->lte(now()->startOfDay());
    }

    public function syncStatusFromDates(): void
    {
        if (in_array($this->status, ['cancelled', 'paused'], true)) {
            return;
        }

        $today = now()->startOfDay();

        if ($this->end_date && $this->end_date->lt($today)) {
            $this->status = 'expired';
        } elseif ($this->start_date->gt($today)) {
            $this->status = 'upcoming';
        } else {
            $this->status = 'active';
        }
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }
}
