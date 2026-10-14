<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeEntry extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'attendance_day_id', 'overtime_policy_id', 'work_date',
        'minutes', 'approved_minutes', 'hourly_rate', 'multiplier', 'amount', 'status',
        'payroll_run_id', 'payroll_item_id', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hourly_rate' => 'decimal:2',
        'multiplier' => 'decimal:4',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class, 'overtime_policy_id');
    }
}
