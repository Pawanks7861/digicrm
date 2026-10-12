<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWorkCapacity extends Model
{
    protected $fillable = [
        'user_id', 'working_hours_per_day', 'working_days',
        'effective_from', 'effective_to', 'status',
    ];

    protected $casts = [
        'working_hours_per_day' => 'decimal:2',
        'working_days' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActiveOn(\Carbon\CarbonInterface $date): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->effective_from->gt($date)) {
            return false;
        }
        if ($this->effective_to && $this->effective_to->lt($date)) {
            return false;
        }

        return true;
    }
}
