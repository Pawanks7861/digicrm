<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'start_unit', 'end_unit', 'days', 'status', 'reason', 'document_path',
        'comp_off_attendance_day_id', 'approval_request_id', 'approved_at', 'cancelled_at',
        'attendance_applied',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'decimal:2',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'attendance_applied' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    public function compOffDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class, 'comp_off_attendance_day_id');
    }
}
