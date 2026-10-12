<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft', 'sent', 'partially_paid', 'paid', 'cancelled', 'void',
    ];

    protected $fillable = [
        'invoice_number', 'client_id', 'client_contact_id', 'quotation_id', 'client_package_id', 'campaign_id',
        'currency', 'issue_date', 'due_date', 'status',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount',
        'taxable_amount', 'tax_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'grand_total',
        'amount_paid', 'balance_due', 'tax_treatment', 'notes', 'terms', 'internal_notes',
        'seller_name', 'seller_address', 'seller_state', 'seller_gstin',
        'buyer_name', 'buyer_address', 'buyer_state', 'buyer_gstin',
        'period_label', 'sent_at', 'paid_at', 'cancelled_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['is_overdue', 'display_status'];

    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, ['cancelled', 'void', 'paid'], true)) {
            return false;
        }
        if (! $this->due_date || bccomp((string) $this->balance_due, '0', 2) <= 0) {
            return false;
        }

        return $this->due_date->lt(now()->startOfDay());
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->is_overdue) {
            return 'overdue';
        }

        return $this->status ?: 'draft';
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function validPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->where('status', 'recorded');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }
}
