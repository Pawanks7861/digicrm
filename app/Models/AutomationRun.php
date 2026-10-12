<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    protected $fillable = [
        'automation_rule_id', 'trigger_type', 'entity_type', 'entity_id',
        'status', 'depth', 'context', 'idempotency_key',
        'started_at', 'completed_at', 'error', 'dry_run',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'dry_run' => 'boolean',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function actionRuns(): HasMany
    {
        return $this->hasMany(AutomationActionRun::class);
    }
}
