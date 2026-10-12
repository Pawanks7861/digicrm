<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'item_type', 'source_type', 'source_id', 'name', 'description', 'hsn_sac',
        'quantity', 'unit', 'unit_rate', 'discount_amount', 'tax_rate_id',
        'tax_percent', 'cgst_percent', 'sgst_percent', 'igst_percent',
        'line_subtotal', 'taxable_amount', 'tax_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'total_amount', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percent' => 'decimal:3',
        'cgst_percent' => 'decimal:3',
        'sgst_percent' => 'decimal:3',
        'igst_percent' => 'decimal:3',
        'line_subtotal' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
