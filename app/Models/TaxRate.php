<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = [
        'name', 'percentage', 'tax_type', 'cgst_percentage', 'sgst_percentage',
        'igst_percentage', 'is_active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'percentage' => 'decimal:3',
        'cgst_percentage' => 'decimal:3',
        'sgst_percentage' => 'decimal:3',
        'igst_percentage' => 'decimal:3',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
