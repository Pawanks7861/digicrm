<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class AttendanceCorrection extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'work_date', 'proposed_punches', 'reason', 'status',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'proposed_punches' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }
}
