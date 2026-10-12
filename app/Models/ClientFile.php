<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientFile extends Model
{
    use HasFactory, SoftDeletes;

    public const FOLDERS = [
        'Brand Assets',
        'Campaigns',
        'Social Media',
        'Videos',
        'Photos',
        'Approvals',
        'Reports',
        'Contracts',
        'Invoices',
        'Documents',
    ];

    protected $fillable = [
        'client_id',
        'folder',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'visibility',
        'version',
        'uploaded_by',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
