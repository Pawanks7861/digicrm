<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'is_paid', 'requires_document', 'allow_negative_balance',
        'counts_holidays', 'counts_weekly_offs', 'is_comp_off', 'annual_allocation',
        'accrual_method', 'carry_forward', 'max_carry_forward', 'min_notice_days', 'max_consecutive_days',
        'allow_half_day', 'probation_eligible', 'is_active', 'rules',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_document' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'counts_holidays' => 'boolean',
        'counts_weekly_offs' => 'boolean',
        'is_comp_off' => 'boolean',
        'carry_forward' => 'boolean',
        'allow_half_day' => 'boolean',
        'probation_eligible' => 'boolean',
        'is_active' => 'boolean',
        'annual_allocation' => 'decimal:2',
        'max_carry_forward' => 'decimal:2',
        'rules' => 'array',
    ];
}
