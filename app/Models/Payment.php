<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = [
        'bank_transfer', 'upi', 'cheque', 'cash', 'credit_card', 'debit_card',
        'payment_gateway', 'neft', 'rtgs', 'imps', 'other',
    ];

    protected $fillable = [
        'payment_number', 'client_id', 'invoice_id', 'currency', 'amount',
        'payment_date', 'payment_method', 'transaction_reference', 'notes',
        'status', 'reversed_at', 'reversed_by', 'reversal_reason',
        'received_by', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
