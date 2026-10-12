<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCostRate extends Model
{
    protected $fillable = [
        'user_id', 'hourly_cost', 'currency', 'effective_from', 'effective_to',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'hourly_cost' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function covers(\Carbon\CarbonInterface $date): bool
    {
        if ($this->effective_from->gt($date)) {
            return false;
        }
        if ($this->effective_to && $this->effective_to->lt($date)) {
            return false;
        }

        return true;
    }
}
