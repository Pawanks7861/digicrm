<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePackage extends Model
{
    use HasFactory, SoftDeletes;

    public const BILLING_CYCLES = [
        'monthly',
        'quarterly',
        'half_yearly',
        'yearly',
        'one_time',
        'custom',
    ];

    public const STATUSES = ['active', 'inactive'];

    public const UNITS = [
        'Post', 'Reel', 'Story', 'Video', 'Design', 'Campaign',
        'Hour', 'Shoot', 'Article', 'Landing Page', 'Ad Campaign', 'Month', 'Custom',
    ];

    protected $fillable = [
        'name',
        'code',
        'description',
        'billing_cycle',
        'base_price',
        'currency',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clientPackages(): HasMany
    {
        return $this->hasMany(ClientPackage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
