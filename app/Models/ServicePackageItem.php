<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_package_id',
        'service_name',
        'service_code',
        'description',
        'quantity',
        'unit',
        'overage_rate',
        'sort_order',
        'is_unlimited',
        'included_revisions',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'overage_rate' => 'decimal:2',
        'is_unlimited' => 'boolean',
        'included_revisions' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_package_id');
    }
}
