<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id', 'payable_days', 'lop_days',
        'ctc', 'gross', 'employer_contributions', 'deductions', 'net_pay',
        'calculation', 'payment_status',
    ];

    protected $casts = [
        'payable_days' => 'decimal:2',
        'lop_days' => 'decimal:2',
        'ctc' => 'decimal:2',
        'gross' => 'decimal:2',
        'employer_contributions' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'calculation' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
