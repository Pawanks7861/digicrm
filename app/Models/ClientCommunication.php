<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClientCommunication extends Model
{
    protected $fillable = [
        'client_id', 'client_contact_id', 'user_id', 'communication_type', 'direction',
        'subject', 'notes', 'occurred_at', 'follow_up_required', 'next_follow_up_at',
        'visibility', 'created_by', 'communicable_type', 'communicable_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'follow_up_required' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function communicable(): MorphTo
    {
        return $this->morphTo();
    }
}
