<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationActionRun extends Model
{
    protected $fillable = [
        'automation_run_id', 'automation_action_id', 'action_type', 'status',
        'idempotency_key', 'result', 'error', 'scheduled_for',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'result' => 'array',
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AutomationAction::class, 'automation_action_id');
    }
}
