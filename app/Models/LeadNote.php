<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    protected $fillable = [
        'lead_id', 'user_id', 'body', 'visibility',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    protected $hidden = ['attachment_path'];

    protected $appends = ['has_attachment'];

    public function getHasAttachmentAttribute(): bool
    {
        return ! empty($this->attributes['attachment_path'] ?? null);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
