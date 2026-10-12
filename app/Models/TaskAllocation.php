<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAllocation extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'allocated_minutes', 'allocation_percentage',
    ];

    protected $casts = [
        'allocated_minutes' => 'integer',
        'allocation_percentage' => 'decimal:2',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
