<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePolicyAssignment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'hr_policy_id', 'effective_from', 'effective_to', 'assigned_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HrPolicy::class, 'hr_policy_id');
    }
}
