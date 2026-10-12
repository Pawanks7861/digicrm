<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDay extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'work_date', 'work_shift_id', 'status', 'worked_minutes',
        'break_minutes', 'overtime_minutes', 'is_late', 'is_early_departure', 'missing_checkout',
        'is_locked', 'working_arrangement', 'leave_request_id', 'leave_fraction', 'source_snapshot', 'derived_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'is_late' => 'boolean',
        'is_early_departure' => 'boolean',
        'missing_checkout' => 'boolean',
        'is_locked' => 'boolean',
        'leave_fraction' => 'decimal:2',
        'source_snapshot' => 'array',
        'derived_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
