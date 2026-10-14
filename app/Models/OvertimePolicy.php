<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class OvertimePolicy extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'multiplier', 'minimum_minutes', 'rounding_minutes',
        'max_daily_minutes', 'requires_approval', 'comp_off_allowed', 'hourly_divisor_days',
        'hourly_divisor_hours', 'effective_from', 'effective_to', 'is_active',
    ];

    protected $casts = [
        'multiplier' => 'decimal:4',
        'requires_approval' => 'boolean',
        'comp_off_allowed' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
