<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'max_amount', 'requires_receipt', 'is_active',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'requires_receipt' => 'boolean',
        'is_active' => 'boolean',
    ];
}
