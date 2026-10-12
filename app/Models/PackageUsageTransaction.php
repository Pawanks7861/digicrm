<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PackageUsageTransaction extends Model
{
    use HasFactory;

    public const DIRECTIONS = ['consume', 'restore', 'adjustment'];

    protected $fillable = [
        'client_id',
        'client_package_id',
        'client_package_item_id',
        'usage_date',
        'period_start',
        'period_end',
        'quantity',
        'direction',
        'source_type',
        'source_id',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'quantity' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ClientPackageItem::class, 'client_package_item_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Signed quantity toward consumption (consume +, restore -, adjustment as-is). */
    public function signedQuantity(): float
    {
        $qty = (float) $this->quantity;

        return match ($this->direction) {
            'consume' => abs($qty),
            'restore' => -abs($qty),
            'adjustment' => $qty,
            default => $qty,
        };
    }
}
