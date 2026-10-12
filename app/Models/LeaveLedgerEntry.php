<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveLedgerEntry extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'entry_type', 'days', 'balance_after',
        'leave_request_id', 'reference', 'notes', 'created_by', 'effective_at',
    ];

    protected $casts = [
        'days' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'effective_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
