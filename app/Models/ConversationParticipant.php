<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    public const ROLE_MEMBER = 'member';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'conversation_id', 'user_id', 'role', 'joined_at', 'last_read_message_id',
        'muted_until', 'viewing_until', 'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'muted_until' => 'datetime',
        'viewing_until' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN && $this->isActive();
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }
}
