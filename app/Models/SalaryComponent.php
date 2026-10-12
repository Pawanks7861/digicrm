<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryComponent extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'salary_structure_id', 'code', 'name', 'component_type', 'calc_type',
        'amount', 'percentage', 'percentage_of', 'formula_key', 'is_taxable', 'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:4',
        'is_taxable' => 'boolean',
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }
}
