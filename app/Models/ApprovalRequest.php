<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'request_type', 'approvable_type', 'approvable_id',
        'requester_employee_id', 'current_approver_employee_id', 'delegated_approver_employee_id',
        'amount_limit', 'status', 'level', 'decided_at', 'notes',
    ];

    protected $casts = [
        'amount_limit' => 'decimal:2',
        'decided_at' => 'datetime',
        'level' => 'integer',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_approver_employee_id');
    }

    public function delegatedApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delegated_approver_employee_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }
}
