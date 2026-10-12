<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkShift extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'shift_type', 'start_time', 'end_time', 'break_minutes',
        'is_overnight', 'full_day_minutes', 'half_day_minutes', 'grace_minutes', 'timezone', 'is_active',
    ];

    protected $casts = [
        'is_overnight' => 'boolean',
        'is_active' => 'boolean',
        'break_minutes' => 'integer',
        'full_day_minutes' => 'integer',
        'half_day_minutes' => 'integer',
        'grace_minutes' => 'integer',
    ];
}
