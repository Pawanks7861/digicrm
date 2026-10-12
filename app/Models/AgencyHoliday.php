<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyHoliday extends Model
{
    protected $fillable = ['date', 'name', 'is_working_day_override'];

    protected $casts = [
        'date' => 'date',
        'is_working_day_override' => 'boolean',
    ];
}
