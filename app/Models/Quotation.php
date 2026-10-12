<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft', 'sent', 'viewed', 'changes_requested', 'accepted',
        'rejected', 'expired', 'converted', 'cancelled', 'superseded',
    ];

    public const TRANSITIONS = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['viewed', 'accepted', 'rejected', 'changes_requested', 'cancelled', 'expired'],
        'viewed' => ['accepted', 'rejected', 'changes_requested', 'cancelled', 'expired'],
        'changes_requested' => ['sent', 'cancelled'],
        'accepted' => ['converted', 'cancelled'],
        'rejected' => [],
        'expired' => ['cancelled'],
        'converted' => [],
        'cancelled' => [],
        'superseded' => [],
    ];

    protected $fillable = [
        'quotation_number', 'client_id', 'client_contact_id', 'campaign_id', 'client_request_id',
        'lead_id', 'opportunity_id',
        'currency', 'issue_date', 'valid_until', 'status',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount',
        'taxable_amount', 'tax_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'grand_total',
        'tax_treatment', 'notes', 'terms', 'internal_notes',
        'revision_number', 'parent_quotation_id',
        'accepted_by_user_id', 'accepted_at', 'rejected_at', 'sent_at', 'viewed_at',
        'client_message', 'accepted_ip', 'converted_invoice_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    protected $appends = ['display_number', 'is_expired'];

    public function getDisplayNumberAttribute(): string
    {
        if ($this->revision_number > 1) {
            $base = preg_replace('/-R\d+$/', '', $this->quotation_number) ?: $this->quotation_number;

            return $base.' / R'.$this->revision_number;
        }

        return $this->quotation_number;
    }

    public function getIsExpiredAttribute(): bool
    {
        if (! $this->valid_until) {
            return false;
        }
        if (in_array($this->status, ['accepted', 'rejected', 'converted', 'cancelled', 'superseded'], true)) {
            return false;
        }

        return $this->valid_until->lt(now()->startOfDay());
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_quotation_id');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class)->withTrashed();
    }
}
