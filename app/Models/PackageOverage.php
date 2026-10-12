<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PackageOverage extends Model
{
    use HasFactory;

    public const DECISIONS = [
        'pending',
        'chargeable',
        'complimentary',
        'rejected',
        'quotation_required',
    ];

    public const BILLING_STATUSES = [
        'unbilled',
        'added_to_quotation',
        'added_to_invoice',
        'paid',
        'waived',
    ];

    protected $fillable = [
        'client_id',
        'client_package_id',
        'client_package_item_id',
        'source_type',
        'source_id',
        'requested_quantity',
        'included_remaining',
        'overage_quantity',
        'overage_rate',
        'extra_amount',
        'decision',
        'billing_status',
        'decision_notes',
        'approved_by',
        'approved_at',
        'usage_transaction_id',
        'quotation_id',
        'invoice_id',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:2',
        'included_remaining' => 'decimal:2',
        'overage_quantity' => 'decimal:2',
        'overage_rate' => 'decimal:2',
        'extra_amount' => 'decimal:2',
        'approved_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function usageTransaction(): BelongsTo
    {
        return $this->belongsTo(PackageUsageTransaction::class, 'usage_transaction_id');
    }
}
