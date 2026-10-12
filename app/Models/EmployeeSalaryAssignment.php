<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryAssignment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'salary_structure_id', 'ctc_monthly', 'gross_monthly',
        'component_overrides', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'ctc_monthly' => 'decimal:2',
        'gross_monthly' => 'decimal:2',
        'component_overrides' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }
}
