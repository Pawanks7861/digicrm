<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientPackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_package_id',
        'source_package_item_id',
        'service_name',
        'service_code',
        'description',
        'included_quantity',
        'unit',
        'overage_rate',
        'is_unlimited',
        'included_revisions',
        'sort_order',
    ];

    protected $casts = [
        'included_quantity' => 'integer',
        'overage_rate' => 'decimal:2',
        'is_unlimited' => 'boolean',
        'included_revisions' => 'integer',
    ];

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(ServicePackageItem::class, 'source_package_item_id');
    }

    public function usageTransactions(): HasMany
    {
        return $this->hasMany(PackageUsageTransaction::class);
    }

    public function overages(): HasMany
    {
        return $this->hasMany(PackageOverage::class);
    }
}
