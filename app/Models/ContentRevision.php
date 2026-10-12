<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRevision extends Model
{
    protected $fillable = [
        'content_item_id', 'revision_number', 'client_file_id', 'original_name', 'path',
        'mime_type', 'size', 'caption_snapshot', 'hashtags_snapshot', 'cta_snapshot',
        'notes', 'status', 'is_client_cycle', 'uploaded_by',
    ];

    protected $casts = [
        'revision_number' => 'integer',
        'size' => 'integer',
        'is_client_cycle' => 'boolean',
    ];

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ClientFile::class, 'client_file_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'video/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
