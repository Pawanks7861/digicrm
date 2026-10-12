<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationAction extends Model
{
    protected $fillable = [
        'automation_rule_id', 'action_type', 'config', 'delay_minutes',
        'continue_on_match', 'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'continue_on_match' => 'boolean',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
