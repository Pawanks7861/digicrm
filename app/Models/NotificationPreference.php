<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id', 'category', 'in_app_enabled', 'email_enabled', 'push_enabled',
        'sound_enabled', 'digest_mode',
    ];

    protected $casts = [
        'in_app_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'sound_enabled' => 'boolean',
    ];

    public const CATEGORIES = [
        'task', 'follow_up', 'client_request', 'approval', 'content',
        'campaign', 'invoice', 'quotation', 'payment', 'renewal', 'lead', 'system', 'chat',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
