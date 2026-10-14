<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'user_id', 'employee_code', 'first_name', 'last_name', 'work_email', 'phone',
        'branch_id', 'department_id', 'designation_id', 'reporting_manager_id',
        'joining_date', 'exit_date', 'probation_start', 'probation_end', 'probation_status',
        'confirmation_date', 'confirmation_status', 'employment_type', 'employment_status', 'timezone', 'meta',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'exit_date' => 'date',
        'probation_start' => 'date',
        'probation_end' => 'date',
        'confirmation_date' => 'date',
        'meta' => 'array',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reporting_manager_id');
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    public function attendanceDays(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function bankDetail(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeBankDetail::class);
    }

    public function statutoryProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeStatutoryProfile::class);
    }

    public function salaryAssignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryAssignment::class);
    }

    public function isActiveEmployment(): bool
    {
        return in_array($this->employment_status, ['active', 'probation', 'on_notice'], true);
    }
}
